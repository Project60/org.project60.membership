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
 * It requires the mapping {[financial_type_id] => [membership_type_id]}
 * as an input, but this mapping should usually be very static.
 *
 * @param mapping  a comma separated string of <financial_type_id>:<membership_type_id> mappings,
 *                    defaults to system setting 
 * @param rebuild  if set to true or 1, the ill assigend contributions with the given financial type
 *                   will be detached from the membership and rematched wrt the given matching. 
 *					 USE WITH CAUTION!
 */
function civicrm_api3_membership_payment_synchronize($params) {
  if (empty($params['mapping'])) {
    $mapping = CRM_Membership_Settings::getSyncMapping();
  } else {
    if (is_array($params['mapping'])) {
      $mapping = $params['mapping'];
    } else {
      $mapping = CRM_Membership_Settings::_string2syncmap($params['mapping']);
    }
  }

  // NEXT: read the 'rangeback' parameter
  if (empty($params['rangeback'])) {
  	$rangeback = (int) CRM_Membership_Settings::getSyncRange();
  } else {
  	$rangeback = (int) $params['rangeback'];
  }

  // NEXT: read the 'gracedays' parameter
  if (empty($params['gracedays'])) {
    $gracedays = (int) CRM_Membership_Settings::getSyncGracePeriod();
  } else {
    $gracedays = (int) $params['gracedays'];
  }

  foreach ($mapping as $financial_type_id => $membership_type_id) {
  	if (!is_numeric($financial_type_id) || !is_numeric($membership_type_id))
  		return civicrm_api3_create_error("The parameter 'mapping' should contain only IDs.");
  }

  // if required, detach all ill assigned memberships for the given financial types first
  if (!empty($params['rebuild']) && ($params['rebuild']==1 || strtolower($params['rebuild'])=='true')) {
  	foreach ($mapping as $financial_type_id => $membership_type_id) {
  		$remove_bad_assignments_sql = "
  			DELETE civicrm_membership_payment
  			FROM civicrm_membership_payment
  			LEFT JOIN civicrm_contribution ON civicrm_contribution.id = civicrm_membership_payment.contribution_id
  			LEFT JOIN civicrm_membership   ON civicrm_membership.id = civicrm_membership_payment.membership_id
  			WHERE
  			 civicrm_contribution.financial_type_id = $financial_type_id
  			AND
  			 civicrm_membership.membership_type_id <> $membership_type_id;";
  		CRM_Core_DAO::singleValueQuery($remove_bad_assignments_sql);
  	}
  } 

  // start synchronization
  $results = array('mapped'=>array(), 'no_membership' => array(), 'ambibiguous'=>array(), 'errors'=>array());
  foreach ($mapping as $financial_type_id => $membership_type_id) {
  	$new_results = _membership_payment_synchronize($financial_type_id, $membership_type_id, $rangeback, $gracedays);
  	foreach ($new_results as $key => $new_values)
  		$results[$key] += $new_values;
  }

  $null = NULL;
  return civicrm_api3_create_success(array_keys($results['mapped']), $params, $null, $null, $null, 
  	array('no_membership'=>$results['no_membership'], 'ambibiguous'=>$results['ambibiguous'], 'errors'=>$results['errors']));
}



/**
 * this function will execute the synchronization 
 *   for ONE financial_type_id => membership_type_id mapping
 */
function _membership_payment_synchronize($financial_type_id, $membership_type_id, $rangeback=0, $gracedays=0) {
  error_log("org.project60.membership - query financial type $financial_type_id on memebership type $membership_type_id");
  $results = array('mapped'=>array(), 'no_membership' => array(), 'ambibiguous'=>array(), 'errors'=>array());
  $contribution_receive_date = array();
  $membership_start_date = array();
  $membership_join_date = array();
  $eligible_states = "1";		// completed

  // first: find all contributions that are not yet connected to a membership
  $find_new_payments_sql = "
  SELECT
  	civicrm_contribution.id    			    AS contribution_id,
  	civicrm_contribution.contact_id     AS contact_id,
  	civicrm_contribution.receive_date   AS contribution_date
  FROM
  	civicrm_contribution
  LEFT JOIN
  	civicrm_membership_payment ON civicrm_contribution.id = civicrm_membership_payment.contribution_id
  WHERE
  	civicrm_contribution.financial_type_id = $financial_type_id
  AND civicrm_contribution.contribution_status_id IN ($eligible_states)
  AND civicrm_membership_payment.membership_id IS NULL;
  ";
  $new_payments = CRM_Core_DAO::executeQuery($find_new_payments_sql);
  while ($new_payments->fetch()) {
  	$contact_id = $new_payments->contact_id;
  	$contribution_id = $new_payments->contribution_id;
  	$date = date('Ymdhis', strtotime($new_payments->contribution_date));

  	// now, try to find a valid membership
    // TODO: optimize by building a membership list in memory instead of individual queries?
  	$find_corresponding_membership_sql = "
  	SELECT
  		id         AS membership_id,
  		COUNT(id)  AS membership_count,
  		start_date AS membership_start_date,
  		join_date  AS membership_join_date
  	FROM
  		civicrm_membership
  	WHERE
  		contact_id = $contact_id
  	AND membership_type_id = $membership_type_id
  	AND ((start_date <= (DATE('$date') + INTERVAL $rangeback DAY)) OR (join_date <= (DATE('$date') + INTERVAL $rangeback DAY)))
  	AND ((end_date   >  (DATE('$date') - INTERVAL $gracedays DAY)) OR (end_date IS NULL))
  	GROUP BY
  		civicrm_membership.contact_id;
  	";
  	$corresponding_membership = CRM_Core_DAO::executeQuery($find_corresponding_membership_sql);
  	if (!$corresponding_membership->fetch()) {
  		// NO MEMBERSHIP FOUND
  		$results['no_membership'][] = $contribution_id;
  	} elseif ($corresponding_membership->membership_count == 1) {
  		// MEMBERSHIP FOUND
  		$results['mapped'][$contribution_id] = $corresponding_membership->membership_id;
  		$contribution_receive_date[$contribution_id] = $date;
  		$membership_start_date[$corresponding_membership->membership_id] = 
  			date('Ymdhis', strtotime($corresponding_membership->membership_start_date));
  		$membership_join_date[$corresponding_membership->membership_id] = 
  			date('Ymdhis', strtotime($corresponding_membership->membership_join_date));
  	} else {
  		// MEMBERSHIP AMBIGUOUS
  		$results['ambibiguous'][] = $contribution_id;
  	}
  }
  $new_payments->free();

  // EXECUTE 
  foreach ($results['mapped'] as $contribution_id => $membership_id) {
  	// create contribution -> membership connections
  	$create_result = civicrm_api('MembershipPayment', 'create', 
  		array('contribution_id'=>$contribution_id, 'membership_id'=>$membership_id, 'version'=>3));
  	if (!empty($create_result['is_error'])) {
  		// ERROR HANDLING
  		$results['errors'][$contribution_id] = $create_result['is_error'];
  		continue;
  	}

  	// adjust memberships if wanted
  	if ($rangeback) {
  		$contribution_date = $contribution_receive_date[$contribution_id];
  		$start_date = $membership_start_date[$membership_id];
  		$join_date = $membership_join_date[$membership_id];
  		if ($contribution_date < $start_date) {
  			$adjust_query = array("version" => 3, "id" => $membership_id, "start_date" => $contribution_date);
  			if ($contribution_date < $join_date) {
  				$adjust_query["join_date"] = $contribution_date;
  			}
  			$adjust_result = civicrm_api('Membership', 'create', $adjust_query);
  			if (!empty($adjust_result['is_error'])) {
  				// ERROR HANDLING
  				$results['errors'][$contribution_id] = $adjust_result['is_error'];
  			}
  		}
  	}
  }

  return $results;
}
