<?php
/*-------------------------------------------------------+
| Project 60 - Membership Extension                      |
| Copyright (C) 2019 SYSTOPIA                            |
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
declare(strict_types = 1);

/**
 * This action will try to assign all unassigned membership fees to a matching membership,
 *  according to the sync mapping.
 *
 * It will, however, override the following sync settings:
 *  - from/to time frame
 *  - contribution status IDs (default: all)
 *  - live membership status IDs (default: all)
 *
 * This should only be used for assigning a backlog of contributions to the memberships,
 *   for a daily run the MembershipPayment.synchronize is the better job
 *
 * @return array API result
 */
function civicrm_api3_membership_payment_assign_all($params) {
  $settings = CRM_Membership_Settings::getSettings();
  $mapping  = $settings->getSyncMapping();

  $date_from = strtotime((string) $params['date_from']);
  $date_to = strtotime((string) $params['date_to']);
  if ($date_from === FALSE || $date_to === FALSE) {
    return civicrm_api3_create_error("The parameters 'date_from' and 'date_to' must be parsable dates.");
  }

  // build parameters
  $settings_override = [
    'sync_minimum_date'            => date('Y-m-d H:i:s', $date_from),
    'sync_maximum_date'            => date('Y-m-d H:i:s', $date_to),
    'sync_range'                   => 0,
    'grace_period'                 => 0,
    'eligible_contribution_states' => [],
    'live_statuses'                => [],
  ];

  // add contribution / membership status IDs
  if (isset($params['contribution_status_ids']) && $params['contribution_status_ids'] !== '') {
    $settings_override['eligible_contribution_states'] = array_map('intval',
      explode(',', (string) $params['contribution_status_ids']));
  }
  if (isset($params['membership_status_ids']) && $params['membership_status_ids'] !== '') {
    $settings_override['live_statuses'] = array_map('intval', explode(',', (string) $params['membership_status_ids']));
  }

  // start synchronization
  $results = ['mapped' => [], 'no_membership' => [], 'ambiguous' => [], 'errors' => []];
  foreach ($mapping as $financial_type_id => $membership_type_ids) {
    if ($membership_type_ids === []) {
      continue;
    }
    $new_results = CRM_Membership_SynchroniseLogic::synchronizePayments($financial_type_id, $membership_type_ids,
      $settings_override);
    foreach ($new_results as $key => $new_values) {
      $results[$key] += $new_values;
    }
  }

  $null = NULL;
  return civicrm_api3_create_success(array_keys($results['mapped']), $params, $null, $null, $null, [
    'no_membership' => $results['no_membership'],
    'ambiguous'     => $results['ambiguous'],
    'errors'        => $results['errors'],
  ]);
}

/**
 * Adding specs
 */
function _civicrm_api3_membership_payment_assign_all_spec(&$params) {
  $params['date_from'] = [
    'name'         => 'date_from',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Minimum date',
    'description'  => 'Only contributions received on or after this date are considered',
  ];
  $params['date_to'] = [
    'name'         => 'date_to',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Maximum date',
    'description'  => 'Only contributions received on or before this date are considered',
  ];
  $params['contribution_status_ids'] = [
    'name'         => 'contribution_status_ids',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Contribution Status IDs',
    'description'  => 'Only contributions with the given status IDs will be considered. Default is: all',
  ];
  $params['membership_status_ids'] = [
    'name'         => 'membership_status_ids',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Membership Status IDs',
    'description'  => 'Only memberships with the given status IDs will be considered. Default is: all',
  ];
}
