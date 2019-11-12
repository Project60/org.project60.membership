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

/**
 * This job can perform various tasks wrt to memberships:
 *  - calculate outstanding amounts
 *  - extend memberships that do not have an outstanding amount
 */
function civicrm_api3_membership_process($params) {
  $settings = CRM_Membership_Settings::getSettings();
  // set defaults
  $params['extend_if_paid'] = 1;
  $logic = new CRM_Membership_MembershipFeeLogic($params);

  // get membership IDs
  if (empty($params['membership_id'])) {
    $membership_ids = [];
  } else {
    $membership_ids = explode(',', $params['membership_id']);
    unset($params['limit']);
  }

  // Find membership IDs if not given:
  // - LIVE (by status ID)
  // - Within <end_date_offset> days after current end_date
  if (empty($membership_ids)) {
    // build query
    $AND_STATUS_ID_LIVE = $AND_SKIP_LAST_PROCESSED = $LIMIT = '';

    // restrict by status
    $live_status_ids = $settings->getLiveStatusIDs();
    if (!empty($live_status_ids)) {
      $live_status_list = implode(',', $live_status_ids);
      $AND_STATUS_ID_LIVE = "AND status_id IN ($live_status_list)";
    }

    // work with limits
    if ($params['limit']) {
      $LIMIT = "LIMIT " . abs((int) $params['limit']);
      $AND_SKIP_LAST_PROCESSED = "AND id > " . $settings->getLastProcessedMembershipID();
    }

    $membership2process_sql = "
      SELECT id AS membership_id
      FROM civicrm_membership
      WHERE DATE(end_date) <= DATE(NOW() + INTERVAL {$params['end_date_offset']} DAY)
        {$AND_STATUS_ID_LIVE}
        {$AND_SKIP_LAST_PROCESSED}
      ORDER BY id ASC
      {$LIMIT}";
    //Civi::log()->debug("query: {$membership2process_sql}");
    $membership2process = CRM_Core_DAO::executeQuery($membership2process_sql);
    while ($membership2process->fetch()) {
      $membership_ids[] = (int) $membership2process->membership_id;
    }
  }


  // run membership IDs
  $processor = new CRM_Membership_MembershipFeeLogic($params);
  $processed_counter = 0;
  $last_processed_id = 0;
  $result_class      = NULL;
  $stats_reply       = [];
  foreach ($membership_ids as $membership_id_raw) {
    $membership_id = (int) $membership_id_raw;
    if ($membership_id) {
      try {
        $result_class = $processor->process($membership_id, !empty($params['dry_run']));
      } catch (Exception $ex) {
        $result_class = 'exception';
        $processor->log("Failed to process membership ID '{$membership_id_raw}': " . $ex->getMessage());
      }
    } else {
      $result_class = 'bad_id';
      $processor->log("Skipped illegal membership ID '{$membership_id_raw}'");
    }
    $processed_counter += 1;
    $last_processed_id = $membership_id;
    $stats_reply[$result_class] = CRM_Utils_Array::value($result_class, $stats_reply, 0) + 1;
  }

  // mark processed
  if ($params['limit']) {
    if ($processed_counter == $params['limit']) {
      $settings->setLastProcessedMembershipID($last_processed_id);
    } else {
      $settings->setLastProcessedMembershipID(0);
    }
  }

  return civicrm_api3_create_success($stats_reply);
}

/**
 * API3 action specs
 */
function _civicrm_api3_membership_process_spec(&$params) {
  $params['membership_id'] = array(
      'name'         => 'membership_id',
      'api.required' => 0,
      'type'         => CRM_Utils_Type::T_STRING,
      'title'        => 'Membership ID(s)',
      'description'  => 'ID of the membership to process, or a comma-separated list of such membership',
  );
  $params['dry_run'] = array(
      'name'         => 'dry_run',
      'api.required' => 0,
      'type'         => CRM_Utils_Type::T_INT,
      'title'        => 'Dry Run?',
      'description'  => 'If active, no changes will be performed',
  );
  $params['end_date_offset'] = array(
      'name'         => 'end_date_offset',
      'api.default'  => 0,
      'type'         => CRM_Utils_Type::T_INT,
      'title'        => 'Selection offset for membership end date',
      'description'  => 'Will investigate memberships [end_date_offset] days after their current end date. If negative _before_ end date',
  );
  $params['limit'] = array(
      'name'         => 'limit',
      'api.default'  => 0,
      'type'         => CRM_Utils_Type::T_INT,
      'title'        => 'Processing Limit',
      'description'  => 'If given, only this amount of memberships will be investigated. The last membership processed will be stored, and the processing will be picked up with the next (limited) call',
  );
}

