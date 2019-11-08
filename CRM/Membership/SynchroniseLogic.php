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

/**
 * This class contains the Payment -> Membership sunchronisation algorithm
 */
class CRM_Membership_SynchroniseLogic {

  /**
   * this function will execute the synchronization
   *   for ONE financial_type_id => membership_type_id mapping
   */
  public static function synchronizePayments($financial_type_id, $membership_type_ids, $rangeback=0, $gracedays=0, $contribution_ids = []) {
    $settings = CRM_Membership_Settings::getSettings();
    $results = array('mapped'=>[], 'no_membership' => [], 'ambiguous'=>[], 'errors'=>[]);
    $membership_type_id_list = implode(',', $membership_type_ids);
    $membership_status_ids = $settings->getLiveStatusIDs();
    $membership_status_id_list = implode(',', $membership_status_ids);
    $contribution_receive_date = [];
    $membership_start_date = [];
    $membership_join_date = [];
    $eligible_states = "1";   // completed

    // get a mapping of memberships that are linked to recuring-contributions
    $paid_via_field = $settings->getPaidViaField();
    $paid_via_column = $paid_via_field['column_name'];
    $paid_via_mapping = [];
    if ($paid_via_field) {
      $paid_via_mapping_sql = "
      SELECT entity_id, {$paid_via_field['column_name']}
      FROM {$paid_via_field['table_name']}
      WHERE {$paid_via_column} IS NOT NULL;";
      $result = CRM_Core_DAO::executeQuery($paid_via_mapping_sql);
      while ($result->fetch()) {
        $paid_via_mapping[$result->{$paid_via_column}] = $result->entity_id;
      }
    }

    // include 'paid_by' information
    $JOIN_PAID_BY_TABLE = $OR_CONTACT_IS_PAID_BY = '';
    $paid_by_field = $settings->getPaidByField();
    if ($paid_by_field) {
      // there is a paid_by field set up -> use it
      $JOIN_PAID_BY_TABLE = "LEFT JOIN {$paid_by_field['table_name']} paid_by_table ON paid_by_table.entity_id = civicrm_membership.id";
    }

    // add contribution restrictions
    $AND_IN_CONTRIBUTION_ID_LIST = $AND_CONTRIBUTION_MIN_DATE = $AND_CONTRIBUTION_MAX_DATE = '';
    if (!empty($contribution_ids)) {
      $contribution_id_list = implode(',', $contribution_ids);
      $AND_IN_CONTRIBUTION_ID_LIST = "AND civicrm_contribution.id IN ({$contribution_id_list})";
    }

    $minimum_date = $settings->getStrtotimeDate('sync_minimum_date');
    if ($minimum_date) {
      $AND_CONTRIBUTION_MIN_DATE = "AND DATE(civicrm_contribution.receive_date) >= DATE('{$minimum_date}') ";
    }
    $maximum_date = $settings->getStrtotimeDate('sync_maximum_date');
    if ($maximum_date) {
      $AND_CONTRIBUTION_MAX_DATE = "AND DATE(civicrm_contribution.receive_date) <= DATE('{$maximum_date}') ";
    }

    // first: find all contributions that are not yet connected to a membership
    $find_new_payments_sql = "
    SELECT
      civicrm_contribution.id                     AS contribution_id,
      civicrm_contribution.contact_id             AS contact_id,
      civicrm_contribution.receive_date           AS contribution_date,
      civicrm_contribution.contribution_recur_id  AS contribution_recur_id
    FROM
      civicrm_contribution
    LEFT JOIN
      civicrm_membership_payment ON civicrm_contribution.id = civicrm_membership_payment.contribution_id
    WHERE civicrm_contribution.financial_type_id = $financial_type_id
      AND civicrm_contribution.contribution_status_id IN ($eligible_states)
      AND civicrm_membership_payment.membership_id IS NULL
      {$AND_IN_CONTRIBUTION_ID_LIST}
      {$AND_CONTRIBUTION_MIN_DATE}
      {$AND_CONTRIBUTION_MAX_DATE};";
    Civi::log()->debug($find_new_payments_sql);
    $new_payments = CRM_Core_DAO::executeQuery($find_new_payments_sql);
    while ($new_payments->fetch()) {
      $contact_id = $new_payments->contact_id;
      $contribution_id = $new_payments->contribution_id;
      $contribution_recur_id = $new_payments->contribution_recur_id;
      $date = date('Ymdhis', strtotime($new_payments->contribution_date));

      // first check if we got a paid_via-mapping for the contribution
      if (array_key_exists($contribution_recur_id, $paid_via_mapping)) {
        $results['mapped'][$contribution_id] = $paid_via_mapping[$contribution_recur_id];
        continue;
      }

      if ($paid_by_field) {
        // there is a paid_by field set up -> use it
        $OR_CONTACT_IS_PAID_BY = "OR paid_by_table.{$paid_by_field['column_name']} = {$contact_id}";
      }

      // add a subquery for the oldest membership ID
      $oldest_membership_id = "(SELECT MIN(id) FROM civicrm_membership WHERE contact_id = {$contact_id} AND membership_type_id IN ($membership_type_id_list))";

      // now, try to find a valid membership
      // TODO: optimize by building a membership list in memory instead of individual queries?

      $find_corresponding_membership_sql = "
      SELECT
        civicrm_membership.id                  AS membership_id,
        COUNT(DISTINCT(civicrm_membership.id)) AS membership_count,
        start_date                             AS membership_start_date,
        join_date                              AS membership_join_date
      FROM civicrm_membership
      {$JOIN_PAID_BY_TABLE}
      WHERE (civicrm_membership.contact_id = {$contact_id} {$OR_CONTACT_IS_PAID_BY})
      AND status_id IN ($membership_status_id_list)
      AND membership_type_id IN ($membership_type_id_list)
      AND ((start_date <= (DATE('{$date}') + INTERVAL {$rangeback} DAY)) OR (civicrm_membership.id = {$oldest_membership_id} AND join_date <= (DATE('{$date}') + INTERVAL {$rangeback} DAY)))
      AND ((end_date   >  (DATE('{$date}') - INTERVAL {$gracedays} DAY)) OR (end_date IS NULL))
      ";
      $corresponding_membership = CRM_Core_DAO::executeQuery($find_corresponding_membership_sql);
      if (  !$corresponding_membership->fetch()
          || $corresponding_membership->membership_count == 0) {
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
        $results['ambiguous'][] = $contribution_id;
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

  /**
   * detach all ill assigned memberships for the given financial types first
   */
  public static function resetPayments($financial_type_id, $membership_type_ids, $contribution_ids = array()) {
    if (empty($membership_type_ids)) {
      return;
    }

    // add contribution restriction
    $AND_IN_CONTRIBUTION_ID_LIST = '';
    if (!empty($contribution_ids)) {
      $contribution_id_list = implode(',', $contribution_ids);
      $AND_CONTRIBUTION_IN_LIST = "AND civicrm_contribution.id IN ({$contribution_id_list})";
    }

    $membership_type_id_list = implode(',', $membership_type_ids);
    $remove_bad_assignments_sql = "
      DELETE civicrm_membership_payment
      FROM civicrm_membership_payment
      LEFT JOIN civicrm_contribution ON civicrm_contribution.id = civicrm_membership_payment.contribution_id
      LEFT JOIN civicrm_membership   ON civicrm_membership.id = civicrm_membership_payment.membership_id
      WHERE civicrm_contribution.financial_type_id = {$financial_type_id}
        AND civicrm_membership.membership_type_id NOT IN ({$membership_type_id_list})
        {$AND_CONTRIBUTION_IN_LIST};";
    CRM_Core_DAO::executeQuery($remove_bad_assignments_sql);
  }
}
