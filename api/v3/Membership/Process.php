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
  $now = strtotime("now");
  $logic = new CRM_Membership_MembershipFeeLogic($params);

  // get membership IDs
  if (!empty($params['membership_id'])) {

  }


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
  $params['limit'] = array(
      'name'         => 'limit',
      'api.required' => 0,
      'type'         => CRM_Utils_Type::T_INT,
      'title'        => 'Processing Limit',
      'description'  => 'If given, only this amount of memberships will be investigated. The last membership processed will be stored, and the processing will be picked up with the next (limited) call',
  );
}

