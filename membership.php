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
use CRM_Membership_ExtensionUtil as E;

/**
* Add an action for creating donation receipts after doing a search
*
* @access public
*/
function membership_civicrm_searchTasks($objectType, &$tasks) {
  if ($objectType == 'contribution') {
    if (CRM_Core_Permission::check('access CiviMember')) {
      $tasks[] = array(
          'title' => E::ts('Assign to Membership'),
          'class' => 'CRM_Membership_Form_Task_AssignTask',
          'result' => false);
      $tasks[] = array(
          'title' => E::ts('Detach from Membership'),
          'class' => 'CRM_Membership_Form_Task_DetachTask',
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
  $paid_by_logic = CRM_Membership_PaidByLogic::getSingleton();
  $paid_by_logic->assignSepaInstallment($mandate_id, $contribution_id,$contribution_recur_id);
}

/**
 * CiviCRM PRE Hook - generate new membership number when new membership is created
 *
 * @access public
 */
function membership_civicrm_pre($op, $objectName, $id, &$params) {
    if ($op == 'create' && $objectName == 'Membership' && empty($id)) {
        // this might be one for us
        CRM_Membership_NumberLogic::generateNewNumber($params);
    }
}


/**
 * Implements hook_civicrm_buildForm().
 *
 * Insertj
 *
 * @param string $formName
 * @param CRM_Core_Form $form
 */
function membership_civicrm_buildForm($formName, &$form) {
  if ($formName == 'CRM_Member_Form_MembershipView') {
    $paid_by_logic = CRM_Membership_PaidByLogic::getSingleton();
    $paid_by_logic->extendForm($formName, $form);
  }
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 */
function membership_civicrm_navigationMenu(&$menu) {
  _membership_civix_insert_navigation_menu($menu, 'Memberships', array(
    'label'      => E::ts('Synchronise Payments'),
    'name'       => 'p60_payment_sync',
    'url'        => 'civicrm/membership/payments',
    'permission' => 'access CiviContribute',
    'operator'   => 'OR',
    'separator'  => 0,
  ));
  _membership_civix_navigationMenu($menu);
}


/**
 * Hook implementation: New Tokens
 */
function membership_civicrm_tokens( &$tokens ) {
  $membership_types = civicrm_api3('MembershipType', 'get', array(
      'is_active' => 1,
      'return'    => 'id,name'));
  if (!empty($membership_types['values'])) {
    $membership_tokens = array();
    foreach ($membership_types['values'] as $membership_type) {
      $membership_tokens["membership_number.membership_number_{$membership_type['id']}"] = E::ts("%1 Number", array(1 => $membership_type['name']));
    }
    $tokens['membership_number'] = $membership_tokens;
  }
}

/**
 * Hook implementation: New Tokens
 */
function membership_civicrm_tokenValues(&$values, $cids, $job = null, $tokens = array(), $context = null) {
  // error_log("CALL: " . json_encode($cids) . ' / ' . json_encode($tokens));
  if (empty($tokens['membership_number'])) return;

  // collect membership types
  foreach ($tokens['membership_number'] as $token) {
    if (substr($token, 0, 18) == 'membership_number_') {
      $membership_type_id = substr($token, 18);
      $tvalues = CRM_Membership_NumberLogic::getCurrentMembershipNumbers($cids, array($membership_type_id));
      foreach ($tokens['membership_number'] as $key => $value) {
        // there seems to be a difference between indivudual and mass mailings:
        $token = $job ? $key : $value;

        foreach ($tvalues as $cid => $number) {
          $values[$cid]["membership_number.{$token}"] = $number;
        }
      }
    }
  }
}

/**
 * Hook implementation: New Tokens
 */
function membership_civicrm_summary( $contactID, &$content, &$contentPlacement ) {
  CRM_Membership_NumberLogic::adjustSummaryView($contactID);
}