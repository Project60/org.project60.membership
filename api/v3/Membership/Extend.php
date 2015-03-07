<?php
/*-------------------------------------------------------+
| Project 60 - Membership Extension                      |
| Copyright (C) 2013-2015 SYSTOPIA                       |
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

/**
 * This job will check all memberships for new membership payments. It will
 * look for payments for each cycle and stop if one is missing. It will, however
 * not go back beyond the last payment 
 * 
 * @param membership_type_ids      only check memberships with the given membership types
 *                           default is ALL
 * @param precision        date range in which to accept membership payments
 *                           payments within <precision> days of the expected
 *                           will be accepted.
 *                           default is 7
 * @param status_ids       define the membership status IDs that will be considered. 
 *                           DEFAULT is 1,2,3 (new, current, grace)
 * @param look_ahead       only check memberships with an end_date up to look_ahead 
 *                           days in the future. This value can be negative.
 *                           DEFAULT is 14
 * @param custom_fee       if set, this should be a custom field of the membership
 *                           containing a yearly(!) fee override. If not set or not present with
 *                           one particular membership, the default will still be used
 * @param custom_interval  if set, this should be a custom field of the membership
 *                           containing a payemnt interval override
 * @param change_status    if "1" update the membership status when extending the membership
 *                           DEFAULT is 1
 * @param test_run         if "1" no actual changes are performed. DEFAULT is 0
 */
function civicrm_api3_membership_extend($params) {
  $now = strtotime("now");
  $stats = array(
    'memberships_checked'      => 0,
    'memberships_extended'     => 0,
    'memberships_irregular'    => 0,
    'extended_membership_ids'  => array(),
    'irregular_membership_ids' => array(),
    );

  // 0. Sanitize parameters
  $membership_type_ids   = _mebership_extend_helper_extract_intlist($params['membership_type_ids']);
  if (empty($membership_type_ids)) {
    $membership_clause = "civicrm_membership_type.is_active = 1";
  } else {
    $membership_clause = "civicrm_membership.membership_type_id IN ($membership_type_ids)";
  }

  $membership_status_ids = _mebership_extend_helper_extract_intlist($params['status_ids']);
  if (empty($membership_status_ids)) {
    $membership_status_ids = "1,2,3";
  }

  if (isset($params['look_ahead'])) {
    $look_ahead = (int) $params['look_ahead'];
  } else {
    $look_ahead = 14;
  }

  $contribution_status_completed = (int) CRM_Core_OptionGroup::getValue('contribution_status', 'Completed', 'name', 'String', 'value');
  if (!$contribution_status_completed) {
    return civicrm_api3_create_error("Cannot find contribution status 'Completed'.");
  }

  $membership_status_current = civicrm_api3('MembershipStatus', 'getsingle', array('name' => 'Current'));
  $membership_status_current_id = $membership_status_current['id'];





  // 1. identify and load all memberships
  $memberships = array();
  $find_memberships_sql = "
  SELECT 
    civicrm_membership.id                      AS membership_id,
    civicrm_membership.start_date              AS start_date,
    civicrm_membership.end_date                AS end_date,
    civicrm_membership_type.minimum_fee        AS minimum_fee,
    civicrm_membership_type.duration_unit      AS p_unit,
    civicrm_membership_type.duration_interval  AS p_interval
  FROM civicrm_membership
  LEFT JOIN civicrm_membership_type ON civicrm_membership_type.id = civicrm_membership.membership_type_id
  WHERE $membership_clause
    AND civicrm_membership.status_id IN ($membership_status_ids)
    AND (civicrm_membership.is_override IS NULL OR civicrm_membership.is_override = 0)
    AND civicrm_membership.end_date < (NOW() + INTERVAL $look_ahead DAY);
  ";
  $membership_query = CRM_Core_DAO::executeQuery($find_memberships_sql);
  while ($membership_query->fetch()) {
    $memberships[$membership_query->membership_id] = array(
      'id'           => $membership_query->membership_id,
      'minimum_fee'  => $membership_query->minimum_fee,
      'p_unit'       => $membership_query->p_unit,
      'p_interval'   => $membership_query->p_interval,
      'end_date'     => $membership_query->end_date,
      'start_date'   => $membership_query->start_date);
  }
  $stats['memberships_checked'] = count($memberships);

  foreach ($memberships as $membership_id => $membership) {

    // 2. gather payment information
    
    $expected_payment_amount    = $membership['minimum_fee'];
    $payment_unit               = $membership['p_unit'];
    $payment_interval           = $membership['p_interval'];
    $precision                  = $params['precision'] * (24 * 60 * 60);

    if (!empty($params['custom_fee']) || !empty($params['custom_interval'])) {
      // custom field/value override
      $membership_data = civicrm_api3('Membership', 'getsingle', array('id' => $membership_id));

      if (!empty($params['custom_interval']) && !empty($membership_data[$params['custom_interval']])) {
        $payment_interval = $membership_data[$params['custom_interval']];
        $payment_unit     = 'month';
      }

      if (!empty($params['custom_fee']) && !empty($membership_data[$params['custom_fee']])) {
        $expected_payment_amount = $membership_data[$params['custom_fee']];
        error_log($expected_payment_amount);
        $expected_payment_amount = CRM_Utils_Rule::cleanMoney($expected_payment_amount);
        error_log($expected_payment_amount);
        //error_log(sprintf("MONEY: RAW '%s%' CLEAN: '%s'", $params['custom_fee'], $expected_payment_amount));

        // this is interpreted as a YEARLY fee, needs to be broken down to individual payments
        if ($payment_unit == 'month') {
          $expected_payment_amount = (float) $expected_payment_amount * 12.0 / (float) $payment_interval;
        } elseif ($payment_unit == 'year') {
          $expected_payment_amount = (float) $expected_payment_amount * (float) $payment_interval;
        } elseif ($payment_unit == 'week') {
          $expected_payment_amount = (float) $expected_payment_amount * 52.3 / (float) $payment_interval;
        }
        error_log(sprintf("EXPECTED: '%s'", $expected_payment_amount));
      }
    }

    // 3. load all payments
    $payment_query_sql = "
    SELECT
      civicrm_contribution.id           AS contribution_id,
      civicrm_contribution.receive_date AS contribution_date,
      civicrm_contribution.total_amount AS contribution_amount,
      civicrm_contribution.currency     AS contribution_currency
    FROM civicrm_contribution
    LEFT JOIN civicrm_membership_payment ON civicrm_membership_payment.contribution_id = civicrm_contribution.id
    WHERE  civicrm_membership_payment.membership_id = $membership_id
      AND  civicrm_contribution.contribution_status_id = $contribution_status_completed
    ORDER BY contribution_date ASC
    ;";
    $membership_payments = array();
    $payment_query = CRM_Core_DAO::executeQuery($payment_query_sql);
    while ($payment_query->fetch()) {
      $membership_payments[] = array(
        'id'                    => $payment_query->contribution_id,
        'contribution_date'     => $payment_query->contribution_date,
        'contribution_amount'   => $payment_query->contribution_amount,
        'contribution_currency' => $payment_query->contribution_currency);
    }

    // 4. iterate through all expected membership payments
    $date = strtotime($membership['start_date']);
    foreach ($membership_payments as $payment_id => $payment) {
      // see if the payment matches the date
      // error_log('Expected: ' . date('Y-m-d', $date));
      // error_log('Payment:  ' . date('Y-m-d', strtotime($payment['contribution_date'])));
      $date_diff = abs($date - strtotime($payment['contribution_date']));

      if (($date_diff < $precision) &&  ($payment['contribution_amount']+1.0 >= $expected_payment_amount)) {
        // this payment checks out, advance to next cycle
        $date = strtotime("+$payment_interval $payment_unit", $date);
      } else {
        // this is an odd payment => exit
        error_log("KOMISCH: $membership_id");
        error_log("EXPECTED $expected_payment_amount ON ". date('Y-m-d', $date));
        error_log("FOUND    {$payment['contribution_amount']} ON ". $payment['contribution_date']);
        $stats['irregular_membership_ids'][] = $membership_id;
        break;
      }
    }

    // finally, go back one day since we want to use this as end_date (last day of membership)
    $date = strtotime("-1 day", $date);
    $end_date = strtotime($membership['end_date']);
    if ($end_date < $date) {
      // here's something we can extend...
      // error_log("EXTEND TO: " . date('Y-m-d', $date));
      $update = array(
          'id'        => $membership_id,
          'end_date'  => date('Ymdhis', $date));

      if (!empty($params['change_status'])) {
        // add a status update:
        if ($date > $now) {
          $update['status_id'] = $membership_status_current_id;
        }
      }

      // execute the update
      if (empty($params['test_run'])) {
        civicrm_api3('Membership', 'create', $update);        
      }

      $stats['memberships_extended'] += 1;
      $stats['extended_membership_ids'][] = $membership_id;
    }
  }
  
  $stats['memberships_irregular'] = count($stats['irregular_membership_ids']);
  if (!empty($params['test_run'])) $stats['test_run'] = 1;

  return civicrm_api3_create_success($stats);
}

function _civicrm_api3_membership_extend_spec(&$params) {
  $params['type_ids'] =  array('title' => "Check memberships with the given membership types. DEFAULT is ALL",
                                'api.default' => "");
  $params['precision'] =  array('title' => "Membership payments within <precision> days of the expected date will be accepted. DEFAULT is 7.",
                                'api.default' => "7");
  $params['status_ids'] = array('title' => "Defines the membership status IDs that will be considered. DEFAULT is 1,2,3 (new, current, grace)",
                                'api.default' => "1,2,3");
  $params['look_ahead'] = array('title' => "Only check memberships with an end_date up to look_ahead days in the future. This value can be negative. DEFAULT is 14.",
                                'api.default' => "14");
  $params['change_status'] = array('title' => "Update the membership status when extending the membership. Default is '1' (yes)",
                                'api.default' => "1");
}


function _mebership_extend_helper_extract_intlist($raw_value) {
  if (empty($raw_value)) return '';
  $bits = split(',', $raw_value);
  $elements = array();
  foreach ($bits as $value) {
    if ((int) $value) {
      $elements[] = (int) $value;
    }
  }
  return implode(',', $elements);
}
