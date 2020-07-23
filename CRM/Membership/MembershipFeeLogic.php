<?php
/*-------------------------------------------------------+
| Project 60 - Membership Extension                      |
| Copyright (C) 2018 SYSTOPIA                            |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

use CRM_Membership_ExtensionUtil as E;

/**
 * This class contains the logic to automatically extend memberships
 *
 * @see https://github.com/Project60/org.project60.membership/issues/28
 */
class CRM_Membership_MembershipFeeLogic {

  protected $parameters = NULL;
  protected $cached_membership = NULL;
  protected $membership_types = NULL;
  protected $log_file = NULL;

  /**
   * CRM_Membership_MembershipFeeLogic constructor.
   *
   * Possible parameters:
   *  extend_if_paid - should the membership be extended by one period if enough money was paid?
   *  create_missing - should a missing fee contribution be created?
   *
   * @param $parameters array
   */
  public function __construct($parameters = []) {
    // set defaults
    $this->parameters = [
        'extend_if_paid'                 => 0,
        'create_invoice'                 => 0,
        'contribution_status'            => '1',
        'membership_period_shift'        => -10, // shift the membership period the contribution is assigned to. With -10 a payment made at the end of december would be counted towards the next year
        'missing_fee_grace'              => 0.99,
        'missing_fee_payment_instrument' => 5, // EFT
        'missing_fee_update'             => 1, // YES
        'cutoff_today'                   => FALSE,
        'log_level'                      => 'info',
        'log_target'                     => 'civicrm',
        'time_unit'                      => 'month',
        'change_status_ids'              => '1,2',
    ];

    // overwrite with passed parameters
    foreach ($parameters as $parameter => $value) {
      $this->parameters[$parameter] = $value;
    }
  }

  /**
   * Check the given membership can be extended,
   *  based on the fees paid
   *
   * @param $membership_id
   * @return string action: extended - membership was extended
   *                        paid     - membership was paid for, but not extended due to settings
   *                        invoiced - membership was not fully paid for, and an invoice contribution was created
   *                        not_paid - membership was not fully paid for
   */
  public function process($membership_id, $dry_run = FALSE) {
    $this->log("Processing membership [{$membership_id}]", 'debug');
    $fees_expected = $this->calculateExpectedFeeForCurrentPeriod($membership_id);
    $fees_paid     = $this->receivedFeeForCurrentPeriod($membership_id);
    $this->log("Membership [{$membership_id}] paid {$fees_paid} of {$fees_expected}.", 'debug');

    $missing = $fees_expected - $fees_paid;
    if ($missing < $this->parameters['missing_fee_grace']) {
      // paid enough
      if (!empty($this->parameters['extend_if_paid'])) {
        $this->extendMembership($membership_id, $dry_run);
        return 'extended';
      } else {
        return 'paid';
      }

    } else {
      // paid too little
      $this->log("Membership [{$membership_id}] is missing fee amount of: {$missing}");
      if (!empty($this->parameters['create_invoice'])) {
        $this->updatedMissingFeeContribution($membership_id, $missing, $dry_run);
        return 'invoiced';
      } else {
        return 'not_paid';
      }
    }
  }

  /**
   * Calculate the fee expected for the current membership period
   *
   * @param $membership_id
   * @return float expected amount for current period
   */
  public function calculateExpectedFeeForCurrentPeriod($membership_id) {
    $membership = $this->getMembership($membership_id);
    list($from_date, $to_date) = $this->getCurrentPeriod($membership_id);
    $changes = [];
    $phase_start = $from_date;
    $annual_amount = $this->getAnnualAmount($membership_id);

    // collect the phases (e.g. amount changes)
    $change_activity_type_id = CRM_Membership_FeeChangeLogic::getSingleton()->getActivityTypeID();
    if ($change_activity_type_id) {
      // get field info
      $field_before = CRM_Membership_CustomData::getCustomField('p60membership_fee_update', 'annual_amount_before');
      $field_after  = CRM_Membership_CustomData::getCustomField('p60membership_fee_update', 'annual_amount_after');
      $before_after_table = CRM_Membership_CustomData::getGroupTable('p60membership_fee_update');
      $change_query_sql = "
        SELECT
          change_record.activity_date_time            AS change_date,
          before_after.{$field_before['column_name']} AS amount_before,
          before_after.{$field_after['column_name']}  AS amount_after
        FROM civicrm_activity change_record
        LEFT JOIN {$before_after_table}  before_after ON before_after.entity_id = change_record.id
        WHERE change_record.activity_type_id = {$change_activity_type_id}
          AND change_record.status_id IN ({$this->parameters['change_status_ids']})
          AND change_record.source_record_id = {$membership_id}
          AND DATE(change_record.activity_date_time) >= DATE('{$from_date}')
          AND DATE(change_record.activity_date_time) <= DATE('{$to_date}') 
        ORDER BY change_record.activity_date_time ASC;";
      $change_query = CRM_Core_DAO::executeQuery($change_query_sql);
      while ($change_query->fetch()) {
        // changes only take effect on the beginning of the next phase
        $next_phase_start = $this->alignDate($change_query->change_date, TRUE, TRUE);
        $changes[] = [
            $phase_start,
            $next_phase_start,
            $change_query->amount_before,
            $change_query->amount_after];
        $phase_start = $next_phase_start;
      }
    }
    $changes[] = [$phase_start, $membership['end_date'], $annual_amount, $annual_amount];

    // accumulate the amounts for each phase
    $expected_amount = 0.0;
    foreach ($changes as $change) {
      $aligned_time_units  = $this->getAlignedTimeUnitCountPerYear();
      $phase_from          = $change[0];
      $phase_to            = $change[1];
      $phase_annual_amount = $change[2];

      // calculate expected amount and add
      $unit_diff = $this->getDateUnitDiff($phase_from, $phase_to);
      $expected_phase_amount = ((float) $phase_annual_amount / (float) $aligned_time_units) * (float) $unit_diff;
      $expected_amount += $expected_phase_amount;
    }

    return max(0.0, $expected_amount);
  }

  /**
   * Get the distance in whole time units
   *
   * @param $from_date string start date
   * @param $to_date   string end date
   * @return int number of time units
   */
  public function getDateUnitDiff($from_date, $to_date) {
    $date = strtotime($from_date);
    $target = strtotime($to_date);
    $counter = 0;
    while ($date < $target) {
      $counter += 1;
      $date = strtotime("+1 {$this->parameters['time_unit']}", $date);
    }
    return $counter;
  }


  /**
   * Align the given date based on the 'time_unit' setting
   * @param $date          string date
   * @param $forward       bool align forwards? otherwise backwards.
   * @param $one_more_day  bool add one day in the end? To tip it over the edge to the next period
   *
   * @return  string aligned date
   */
  public function alignDate($date, $forward, $one_more_day = FALSE) {
    $sign = $forward ? '+' : '-';

    switch ($this->parameters['time_unit']) {
      case 'day':
        $modifier = 'd';
        break;
      case 'week':
        $modifier = 'W';
        break;
      case 'year':
        $modifier = 'Y';
        break;
      default:
      case 'month':
        $modifier = 'm';
        break;
    }

    // now move forward (or backward) as long as we're in the zone
    $last_valid = is_string($date) ? strtotime($date) : $date;
    $frame_target = date($modifier, $last_valid);
    while (TRUE) {
      $candidate = strtotime("{$sign}1 day", $last_valid);
      if ($frame_target == date($modifier, $candidate)) {
        // still in the same frame, we take it!
        $last_valid = $candidate;
      } else {
        // we left the frame, break!
        if ($one_more_day) {
          $last_valid = $candidate;
        }
        break;
      }
    }

    return date('Y-m-d', $last_valid);
  }

  /**
   * Get the amount of time units per year,
   *  based on the 'time_unit' setting
   * @return int time units per year
   */
  protected function getAlignedTimeUnitCountPerYear() {
    switch ($this->parameters['time_unit']) {
      case 'day':
        return 365;
      case 'week':
        return 52;
      case 'year':
        return 1;
      default:
      case 'month':
        return 12;
    }
  }

  /**
   * Determine the received fee payments during the current period
   *
   * @param $membership_id
   * @return float received amount for current period
   */
  public function receivedFeeForCurrentPeriod($membership_id) {
    list($from_date, $to_date) = $this->getCurrentPeriod($membership_id);

    // get contribution type
    $membership = $this->getMembership($membership_id);

    // get the payment identifier stuff
    $identifier = $this->getOutstandingPaymentIdentifier($membership['id'], $membership['end_date']);
    $identifier_pattern = $this->getOutstandingPaymentIdentifier('%', '%');
    $assignment_shift_days = (int) $this->parameters['membership_period_shift'];

    // sum up all current payments
    $amount_sql = "
      SELECT SUM(payment.total_amount)
      FROM civicrm_contribution payment
      LEFT JOIN civicrm_membership_payment cp ON cp.contribution_id = payment.id
      WHERE cp.membership_id = {$membership_id}
        AND payment.contribution_status_id IN ({$this->parameters['contribution_status']})
        AND (payment.trxn_id IS NULL OR payment.trxn_id NOT LIKE '{$identifier_pattern}')
        AND DATE(payment.receive_date) >= (DATE('{$from_date}') + INTERVAL {$assignment_shift_days} DAY)
        AND DATE(payment.receive_date) <= (DATE('{$to_date}') + INTERVAL {$assignment_shift_days} DAY)";
    //Civi::log()->debug($amount_sql);
    $amount = CRM_Core_DAO::singleValueQuery($amount_sql);

    // add the outstanding payment contribution if found
    $amount += CRM_Core_DAO::singleValueQuery("
      SELECT total_amount
      FROM civicrm_contribution
      WHERE trxn_id = '{$identifier}'
        AND contribution_status_id IN ({$this->parameters['contribution_status']})");

    return $amount;
  }

  /**
   * Get the current membership period
   *
   * @param $membership_id integer membership ID
   *
   * @return array [from_date, to_date]
   */
  public function getCurrentPeriod($membership_id) {
    $membership = $this->getMembership($membership_id);
    $mtype = $this->getMembershipType($membership['membership_type_id']);

    // get from_date
    $start_date   = strtotime($membership['start_date']);
    $period_start = strtotime("-{$mtype['duration_interval']} {$mtype['duration_unit']}", strtotime($membership['end_date']));
    $from_date    = max($start_date, $period_start);

    // get to_date
    $to_date = strtotime($membership['end_date']);
    if ($this->parameters['cutoff_today']) {
      $to_date = min(strtotime('now'), $to_date);
    }

    // TODO: check empty range?
    return [date('Y-m-d', $from_date), date('Y-m-d', $to_date)];
  }

  /**
   * Generate an outstanding membership payment identifier
   *
   * @param $membership_id  string membership ID value
   * @param $end_date       string end date of the current period
   * @return string
   */
  public function getOutstandingPaymentIdentifier($membership_id, $end_date) {
    if ($end_date != '%') {
      $end_date = date('Ymd', strtotime($end_date));
    }
    return "P60M-{$membership_id}-{$end_date}";
  }

  /**
   * Create a contribution with the missing amount
   * The txrn_id of that contribution is 'P60M-[membership_id]-[end_date]', e.g. 'P60M-1234-20181231'
   */
  public function updatedMissingFeeContribution($membership_id, $missing_amount, $dry_run = FALSE) {
    $membership = $this->getMembership($membership_id);
    $identifier = $this->getOutstandingPaymentIdentifier($membership_id, $membership['end_date']);
    try {
      $contribution = civicrm_api3('Contribution', 'getsingle', ['trxn_id' => $identifier]);
      if ($contribution['total_amount'] == $missing_amount) {
        $this->log("Contribution {$identifier} found, already has the right amount.", 'debug');
      } else {
        if ($contribution['contribution_status_id'] == 2) {
          if ($dry_run) {
            $this->log("DRY RUN: Contribution {$identifier} found, would be adjusted.", 'debug');
          } else {
            $this->log("Contribution {$identifier} found, will be adjusted.", 'debug');
            civicrm_api3('Contribution', 'create', [
                'id'           => $contribution['id'],
                'total_amount' => $missing_amount,
            ]);
            $this->log("Contribution {$identifier} was found and adjusted.", 'debug');
          }
        }
      }
    } catch (Exception $ex) {
      // not found -> create
      $this->log("Contribution {$identifier} doesn't exist yet.", 'debug');
      $mtype = $this->getMembershipType($membership['membership_type_id']);
      $contact_id = $this->getPayingContactID($membership_id);
      if ($dry_run) {
        $this->log("DRY RUN: Contribution {$identifier} would be created.", 'debug');
      } else {
        $contribution = civicrm_api3('Contribution', 'create', [
            'contact_id'             => $contact_id,
            'total_amount'           => $missing_amount,
            'financial_type_id'      => $mtype['contribution_type_id'],
            'payment_instrument_id'  => $this->parameters['missing_fee_payment_instrument'],
            'receive_date'           => date('YmdHis'),
            'contribution_status_id' => 2, // Pending
        ]);
        $this->log("Contribution {$identifier} created.", 'debug');

        // connect to membership
        CRM_Core_DAO::executeQuery("INSERT IGNORE INTO civicrm_membership_payment (contribution_id,membership_id) VALUES ({$contribution['id']}, {$membership_id});");
      }
    }
  }

  /**
   * Get the annual amount for the given membership ID
   *
   * @param $membership_id
   */
  public function getAnnualAmount($membership_id) {
    $membership = $this->getMembership($membership_id);

    $settings = CRM_Membership_Settings::getSettings();
    $annual_amount_field_id = $settings->getSetting('annual_amount_field');
    if ($annual_amount_field_id) {
      $annual_amount_field = $settings->getFieldInfo($annual_amount_field_id);
      if (isset($membership[$annual_amount_field['key']])) {
        // value already provided by API result
        $amount = $membership[$annual_amount_field['key']];
        return $amount;

      } else {
        // value not provided by API
        $amount = CRM_Core_DAO::singleValueQuery("SELECT `{$annual_amount_field['column_name']}` FROM `{$annual_amount_field['table_name']}` WHERE entity_id = {$membership_id};");
        if ($amount !== NULL) {
          return $amount;
        }
      }
    }

    // if no specific amount is provided, we use the membership type's
    $mtype = $this->getMembershipType($membership['membership_type_id']);
    if (!empty($mtype['minimum_fee'])) {
      return $mtype['minimum_fee'];
    }

    return 0.00;
  }

  /**
   * Extend the membership by one period
   *
   * @param $membership_id integer membership ID
   * @param $dry_run       bool    set TRUE to only log changes, but not execute them
   */
  public function extendMembership($membership_id, $dry_run = FALSE) {
    $membership = $this->getMembership($membership_id);
    $mtype = $this->getMembershipType($membership['membership_type_id']);

    // extend membership
    $current_end_date = strtotime($membership['end_date']);
    $next_end_date = strtotime("+{$mtype['duration_interval']} {$mtype['duration_unit']}", $current_end_date);
    $next_end_date = $this->alignDate($next_end_date, TRUE);

    if ($dry_run) {
      $this->log("Would extend membership [{$membership_id}] of contact [{$membership['contact_id']}] until {$next_end_date}");
    } else {
      civicrm_api3('Membership', 'create', [
        'id'            => $membership_id,
        'end_date'      => $next_end_date,
        // the following 3 fields are needed to re-calculate the status
        'skipStatusCal' => 0,
        'start_date'    => date('Y-m-d', strtotime($membership['start_date'])),
        'join_date'     => date('Y-m-d', strtotime($membership['join_date'])),
      ]);
      $this->log("Extended membership [{$membership_id}] of contact [{$membership['contact_id']}] until {$next_end_date}");

      // add activity??
      // TODO: add activity
    }
  }

  /**
   * Get the contact_id of the contact paying for the given membership
   *
   * @param $membership_id integer membership ID
   * @return integer contact_id
   */
  protected function getPayingContactID($membership_id) {
    $settings = CRM_Membership_Settings::getSettings();
    $membership = $this->getMembership($membership_id);
    $paid_by_contact_id = $membership['contact_id'];

    // see if there is another contact paying for this membership
    $paid_by_field = $settings->getPaidByField();
    if ($paid_by_field) {
      $paid_by_field_value = CRM_Core_DAO::singleValueQuery("SELECT {$paid_by_field['column_name']} FROM {$paid_by_field['table_name']} WHERE entity_id = {$membership_id}");
      if ($paid_by_field_value) {
        $paid_by_contact_id  = $paid_by_field_value;
      }
    }

    return $paid_by_contact_id;
  }


  /**
   * @param string $level
   */
  public function log($message, $level = 'info') {
    static $levels = ['debug' => 10, 'info' => 20, 'error' => 30, 'off' => 100];
    $req_level = CRM_Utils_Array::value($level, $levels, 10);
    $min_level = $this->parameters['log_level'];
    if ($req_level >= $min_level) {
      // we want to log this
      if ($this->parameters['log_target'] == 'civicrm') {
        CRM_Core_Error::debug_log_message("P60.FeeLogic: " . $message);
      } else {
        if ($this->log_file == NULL) {
          $this->log_file = fopen($this->parameters['log_target'], 'a');
          fwrite($this->log_file, "Timestamp: " . date('Y-m-d H:i:s'));
        }
        fwrite($this->log_file, $message);
      }
    }
  }


  /**
   * Load membership (cached)
   *
   * @param $membership_id integer membership ID
   * @return array|null
   */
  protected function getMembership($membership_id) {
    // check if we have it cached
    if (!empty($this->cached_membership['id']) && $this->cached_membership['id'] == $membership_id) {
      return $this->cached_membership;
    }

    // load membership
    $this->cached_membership = civicrm_api3('Membership', 'getsingle', ['id' => $membership_id]);
    return $this->cached_membership;
  }

  /**
   * Load membership type (cached)
   *
   * @param $membership_id integer membership ID
   * @return array|null
   */
  protected function getMembershipType($membership_type_id) {
    if ($this->membership_types === NULL) {
      $this->membership_types = [];
      $types = civicrm_api3('MembershipType', 'get', ['option.limit' => 0]);
      foreach ($types['values'] as $type) {
        $this->membership_types[$type['id']] = $type;
      }
    }
    return $this->membership_types[$membership_type_id];
  }
}