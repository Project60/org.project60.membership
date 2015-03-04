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
 *                        default is ALL
 * @param precision     date range in which to accept membership payments
 *                        payments within <precision> days of the expected
 *                        will be accepted.
 *                        default is 7
 * @param status_ids    define the membership status IDs that will be considered. 
 *                        DEFAULT is 1,2,3 (new, current, grace)
 * @param look_ahead    only check memberships with an end_date up to look_ahead 
 *                        days in the future. This value can be negative.
 *                        DEFAULT is 14
 * @param change_status if "1" update the membership status when extending the membership
 *                        DEFAULT is 1
 */
function civicrm_api3_membership_extend($params) {
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



  // 1. identify and load all memberships
  $memberships = array();
  $find_memberships_sql = "
  SELECT 
    civicrm_membership.id                      AS membership_id,
    civicrm_membership_type.minimum_fee        AS minimum_fee,
    civicrm_membership_type.duration_unit      AS p_unit,
    civicrm_membership_type.duration_interval  AS p_interval
  FROM civicrm_membership
  LEFT JOIN civicrm_membership_type ON civicrm_membership_type.id = civicrm_membership.membership_type_id
  WHERE $membership_clause
    AND civicrm_membership.status_id IN ($membership_status_ids)
    AND (civicrm_membership.is_override = NULL OR civicrm_membership.is_override = 0)
    AND civicrm_membership.end_date < (NOW() + INTERVAL $look_ahead DAY);
  ";
  error_log($find_memberships_sql);
  $membership_query = CRM_Core_DAO::executeQuery($find_memberships_sql);
  while ($membership_query->fetch()) {
    $memberships[$membership_query->id] = array();
    CRM_Core_DAO::storeValues($membership_query, $memberships[$membership_query->id]);
  }



  // 2. gather payment information
  foreach ($memberships as $membership_id => $membership) {
    error_log(print_r($membership,1));
    
    # code...
  }


  // 3. load all payments

  // 4. map payments to membership

  // 5. adjust end_date and status

  return $results;
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
