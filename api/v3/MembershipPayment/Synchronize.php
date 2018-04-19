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
 * this job will connect all payments of a certain financial_type with the
 * corresponding membership.
 *
 * @param rebuild  if set to true or 1, the ill assigend contributions with the given financial type
 *                   will be detached from the membership and rematched wrt the given matching.
 *					 USE WITH CAUTION!
 * @return array API result
 */
function civicrm_api3_membership_payment_synchronize($params) {
  $settings = CRM_Membership_Settings::getSettings();
  $mapping  = $settings->getSyncMapping();

  // NEXT: read the 'rangeback' parameter
  if (empty($params['rangeback'])) {
  	$rangeback = (int) $settings->getSyncRange();
  } else {
  	$rangeback = (int) $params['rangeback'];
  }

  // NEXT: read the 'gracedays' parameter
  if (empty($params['gracedays'])) {
    $gracedays = (int) $settings->getSyncGracePeriod();
  } else {
    $gracedays = (int) $params['gracedays'];
  }

  // check if contribution_ids are given
  $contribution_ids = array();
  if (!empty($params['contribution_ids'])) {
    $cid_data = $params['contribution_ids'];
    if (is_string($cid_data)) {
      $cid_data = explode(',', $cid_data);
    }
    if (is_array($cid_data)) {
      foreach ($cid_data as $contribution_id) {
        $contribution_ids[] = (int) $contribution_id;
      }
    }
  }

  // verify mapping
  foreach ($mapping as $financial_type_id => $membership_type_ids) {
    foreach ($membership_type_ids as $membership_type_id) {
      if (!is_numeric($financial_type_id) || !is_numeric($membership_type_id))
        return civicrm_api3_create_error("The parameter 'mapping' should contain only IDs.");
    }
  }

  // if required, detach all ill assigned memberships for the given financial types first
  if (!empty($params['rebuild']) && ($params['rebuild']==1 || strtolower($params['rebuild'])=='true')) {
  	foreach ($mapping as $financial_type_id => $membership_type_ids) {
      CRM_Membership_SynchroniseLogic::resetPayments($financial_type_id, $membership_type_ids, $contribution_ids);
  	}
  }

  // start synchronization
  $results = array('mapped'=>array(), 'no_membership' => array(), 'ambiguous'=>array(), 'errors'=>array());
  foreach ($mapping as $financial_type_id => $membership_type_ids) {
    if (empty($membership_type_ids)) continue;
  	$new_results = CRM_Membership_SynchroniseLogic::synchronizePayments($financial_type_id, $membership_type_ids, $rangeback, $gracedays, $contribution_ids);
  	foreach ($new_results as $key => $new_values)
  		$results[$key] += $new_values;
  }

  $null = NULL;
  return civicrm_api3_create_success(array_keys($results['mapped']), $params, $null, $null, $null,
  	array('no_membership'=>$results['no_membership'], 'ambiguous'=>$results['ambiguous'], 'errors'=>$results['errors']));
}

/**
 * Adding specs
 */
function _civicrm_api3_membership_payment_synchronize_spec(&$params) {
  $params['rangeback'] = array(
    'name'         => 'rangeback',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Range',
    'description'  => 'Backward horizon (in days). Defaults to value in settings.',
    );
  $params['gracedays'] = array(
    'name'         => 'gracedays',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Grace',
    'description'  => 'Grace Period (in days). Defaults to value in settings.',
    );
  $params['rebuild'] = array(
    'name'         => 'rebuild',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Rebuild Mapping',
    'description'  => 'Caution: Will first remove the existing assignments!',
    );
  $params['contribution_ids'] = array(
    'name'         => 'contribution_ids',
    'api.required' => 0,
    'title'        => 'List of contribution IDs to process',
    'description'  => 'If not given, all contribution IDs will be processed.',
    );
}
