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
 * This is the end point for the CRM_Membership_Form_Task_AssignTask autocomplete
 *
 * This will only find contacts with memberships
 * 
 * @todo I know it's quite a hack, and there should be a better way, 
 *            but I couldn't find it. PRs very welcome!
 */
function civicrm_api3_membership_payment_getlist($params) {
  // first, finde contacts via API (respecting permissions, ACLs, etc.)
  if (is_numeric($params['term'])) {
    // if it's an ID, us that
    $contact_search = civicrm_api3('Contact', 'get', array('id' => $params['term']));
  } else {
    // otherwise, use getlist
    $contact_search = civicrm_api3('Contact', 'getlist', array('input' => $params['term']));
  }

  // now extract ids
  $contact_ids = array();
  $all_contacts = array();
  foreach ($contact_search['values'] as $contact) {
    $contact_ids[] = $contact['id'];
    $all_contacts[$contact['id']] = $contact;
  }

  // now restrict to the ones with memberships
  if (!empty($contact_ids)) {
    $contact_id_list = implode(',', $contact_ids);
    $contact_ids = array();
    $filtered_contacts = CRM_Core_DAO::executeQuery("SELECT DISTINCT(contact_id) FROM civicrm_membership WHERE contact_id IN ({$contact_id_list});");
    while ($filtered_contacts->fetch()) {
      $contact_ids[] = $filtered_contacts->contact_id;
    }
  }

  // finally, compile results
  $result = array();
  foreach ($contact_ids as $contact_id) {
    $contact = $all_contacts[$contact_id];
    $name = isset($contact['sort_name']) ? $contact['sort_name'] : $contact['label'];
    $result[] = array(
      'id'   => $contact_id,
      'text' => "[{$contact_id}] {$name}");
  }

  return $result;
}
