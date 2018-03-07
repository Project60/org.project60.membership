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

require_once 'membership.civix.php';

/**
* Add an action for creating donation receipts after doing a search
*
* @access public
*/
function membership_civicrm_searchTasks($objectType, &$tasks) {
  if ($objectType == 'contribution') {
    if (CRM_Core_Permission::check('access CiviMember')) {
      $tasks[] = array(
          'title' => ts('Assign to Membership'),
          'class' => 'CRM_Membership_Form_Task_AssignTask',
          'result' => false);
    }
  }
}

/**
 * Implementation of hook_civicrm_config
 */
function membership_civicrm_config(&$config) {
  _membership_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function membership_civicrm_xmlMenu(&$files) {
  _membership_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function membership_civicrm_install() {
  return _membership_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function membership_civicrm_uninstall() {
  return _membership_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function membership_civicrm_enable() {
  return _membership_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function membership_civicrm_disable() {
  return _membership_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function membership_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _membership_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function membership_civicrm_managed(&$entities) {
  return _membership_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_caseTypes
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 */
function membership_civicrm_caseTypes(&$caseTypes) {
  _membership_civix_civicrm_caseTypes($caseTypes);
}

/**
 * CiviSEPA Hook - called whenever a new CiviSEPA installment is created
 *
 * @access public
 */
function membership_installment_created($mandate_id, $contribution_recur_id, $contribution_id) {
  //see if this installment should be assigned to a membership
  $sepa_logic = CRM_Membership_Sepa::getSingleton();
  $sepa_logic->assignInstallment($mandate_id, $contribution_id,$contribution_recur_id);
}