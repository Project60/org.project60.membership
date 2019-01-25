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
  protected $membership_types  = NULL;
  protected $log_file  = NULL;

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
        'missing_fee_grace'              => 0.99,
        'missing_fee_payment_instrument' => 5, // EFT
        'missing_fee_update'             => 1, // YES
        'cutoff_today'                   => 1,
        'log_level'                      => 'info',
        'log_target'                     => 'civicrm',
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
   */
  public function process($membership_id) {
    $this->log("Processing membership [{$membership_id}]", 'debug');
    $fees_expected = $this->calculateExpectedFeeForCurrentPeriod($membership_id);
    $fees_paid     = $this->receivedFeeForCurrentPeriod($membership_id);
    $this->log("Membership [{$membership_id}] paid {$fees_paid} of {$fees_expected}.", 'debug');

    $missing = $fees_expected - $fees_paid;
    if ($missing < $this->parameters['missing_fee_grace']) {
      // paid enough
      if (!empty($this->parameters['extend_if_paid'])) {
        $this->extendMembership($membership_id);
      }

    } else {
      // paid too little
      if (!empty($this->parameters['create_invoice'])) {
        $this->updatedMissingFeeContribution($membership_id, $missing);
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
      $change_query = CRM_Core_DAO::executeQuery("
        SELECT
          change.activity_date_time             AS change_date,
          before.{$field_before['column_name']} AS amount_before,
          after.{$field_after['column_name']}   AS amount_after
        FROM civicrm_activity change
        LEFT JOIN {$field_before['table_name']} before ON before.entity_id = change.id
        LEFT JOIN {$field_after['table_name']}  after  ON after.entity_id  = change.id
        WHERE change.activity_type_id = {$change_activity_type_id}
          AND change.activity_status_id IN ()
          AND DATE(change.activity_date_time) >= DATE('{$from_date}')
          AND DATE(change.activity_date_time) <= DATE('{$to_date}') 
        ORDER BY change.activity_date_time ASC;");
      while ($change_query->fetch()) {
        // changes only take effect the next phase
        $next_phase_start = $this->alignDate($change_query->change_date, TRUE);
        $changes[] = [
            $phase_start,
            $next_phase_start,
            $change_query->amount_before,
            $change_query->amount_after];
        $phase_start = $next_phase_start;
      }
    }
    $changes[] = [$phase_start, $membership['end_date'], $annual_amount, $annual_amount];

    // todo accumulate the amounts for each phase
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

    return $expected_amount;
  }

  /**
   * Get the distance in whole time units
   *
   * @param $from_date string start date
   * @param $to_date   string end date
   * @return int number of time units
   */
  protected function getDateUnitDiff($from_date, $to_date) {
    $date = strtotime($from_date);
    $target = strtotime($to_date);
    $counter = 0;
    while ($date < $target) {
      $counter += 1;
      $target = strtotime("+1 {$this->parameters['time_unit']}", $target);
    }
    return $counter;
  }


  /**
   * Align the given date based on the 'time_unit' setting
   * @param $date string date
   * @param $forward bool align forwards? otherwise backwards.
   *
   * @return  string aligned date
   */
  protected function alignDate($date, $forward) {
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
    $last_valid = strtotime($date);
    $frame_target = date($modifier, $last_valid);
    while (TRUE) {
      $candidate = strtotime("{$sign}1 day", $last_valid);
      if ($frame_target == date($modifier, $candidate)) {
        // still in the same frame, we take it!
        $last_valid = $candidate;
      } else {
        // we left the frame, break!
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
    $mtype = $this->getMembershipType($membership['membership_type_id']);
    $contribution_type_id = $mtype['contribution_type_id'];
    if (empty($contribution_type_id)) {
      $contribution_type_id = 2; // membership fee
    }

    // run the query
    $amount = CRM_Core_DAO::singleValueQuery("
      SELECT SUM(payment.total_amount)
      FROM civicrm_contribution payment
      LEFT JOIN civicrm_membership_payment cp ON cp.contribution_id = payment.id
      WHERE cp.membership_id = {$membership_id}
        AND payment.contribution_status_id IN ({$this->parameters['contribution_status']})
        AND payment.financial_type_id IN ({$contribution_type_id})
        AND DATE(payment.receive_date) >= DATE('{$from_date}')
        AND DATE(payment.receive_date) <= DATE('{$to_date}')");

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
    $to_date = strtotime($membership['start_date']);
    if ($this->parameters['cutoff_today']) {
      $to_date = min(strtotime('now'), $to_date);
    }

    // TODO: check empty range?
    return [date('Y-m-d', $from_date), date('Y-m-d', $to_date)];
  }

  /**
   * Create a contribution with the missing amount
   * The txrn_id of that contribution is 'P60M-[membership_id]-[end_date]', e.g. 'P60M-1234-20181231'
   */
  public function updatedMissingFeeContribution($membership_id, $missing_amount) {
    $membership = $this->getMembership($membership_id);
    $identifier = "P60M-{$membership_id}-" . date('Ymd', strtotime($membership['end_date']));
    try {
      $contribution = civicrm_api3('Contribution', 'getsingle', ['trxn_id' => $identifier]);
      if ($contribution['total_amount'] == $missing_amount) {
        $this->log("Contribution {$identifier} found, already has the right amount.", 'debug');
      } else {
        if ($contribution['contribution_status_id'] == 2) {
          $this->log("Contribution {$identifier} found, will be adjusted.", 'debug');
          civicrm_api3('Contribution', 'create', [
              'id'           => $contribution['id'],
              'total_amount' => $missing_amount,
          ]);
          $this->log("Contribution {$identifier} was found and adjusted.", 'debug');
        }
      }
    } catch (Exception $ex) {
      // not found -> create
      $this->log("Contribution {$identifier} doesn't exist yet.", 'debug');
      $mtype = $this->getMembershipType($membership['membership_type_id']);
      $contact_id = $this->getPayingContactID($membership_id);
      civicrm_api3('Contribution', 'create', [
          'contact_id'             => $contact_id,
          'total_amount'           => $missing_amount,
          'financial_type_id'      => $mtype['contribution_type_id'],
          'payment_instrument_id'  => $this->parameters['missing_fee_payment_instrument'],
          'receive_date'           => date('YmdHis'),
          'contribution_status_id' => 2, // Pending
          ]);
      $this->log("Contribution {$identifier} created.", 'debug');
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
  protected function log($message, $level = 'info') {
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
      foreach ($types as $type) {
        $this->membership_types[$type['id']] = $type;
      }
    }
    return $this->membership_types[$membership_type_id];
  }
}