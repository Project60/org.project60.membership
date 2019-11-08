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
 * Implements hook_civicrm_postProcess().
 *
 * @param string $formName
 * @param CRM_Core_Form $form
 */
function membership_civicrm_postProcess($formName, &$form) {
  if ($form instanceof CRM_Contribute_Form_Contribution) {
    // Reset the status message after our logic has renewed a membership after completing
    // a contribution. The civicrm status message from core would
    // indicate a wrong end date and that end date is shown to the user
    // so that is confusing.
    $paidByLogic = CRM_Membership_PaidByLogic::getSingleton();
    $paidByLogic->replaceStatusMessages();

  } elseif ($form instanceof CRM_Member_Form_Membership) {
    // update derived fields
    $membership_id = $form->getEntityId();
    if ($membership_id) {
      $logic = CRM_Membership_PaidByLogic::getSingleton();
      $logic->updateDerivedFields($membership_id);
    }
  }
}

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
function membership_civicrm_installment_created($mandate_id, $contribution_recur_id, $contribution_id) {
  //see if this installment should be assigned to a membership
  $paid_by_logic = CRM_Membership_PaidByLogic::getSingleton();
  $paid_by_logic->assignSepaInstallment($mandate_id, $contribution_recur_id,$contribution_id);
}

/**
 * CiviCRM SearchColumn Hook
 *
 * @access public
 */
function membership_civicrm_searchColumns( $objectName, &$headers, &$rows, &$selector ) {
  if ($objectName == 'membership') {
    CRM_Membership_UiMods::adjustList($headers, $rows, $selector);
  }
}

/**
 * CiviCRM PRE Hook
 *
 * @access public
 */
function membership_civicrm_pre($op, $objectName, $id, &$params) {
  //CRM_Core_Error::debug_log_message("civicrm_pre $op, $objectName, $id");
  if ($objectName == 'Membership') {
    // generate new membership number when new membership is created
    if ($op == 'create' && empty($id)) {
      // this might be one for us
      CRM_Membership_NumberLogic::generateNewNumber($params);
    }

    // catch if a membership is set to a certain status
    if (!empty($id) && ($op == 'create' || $op == 'edit')) {
      $logic = CRM_Membership_PaidByLogic::getSingleton();
      $logic->membershipUpdatePre($id, $params);

      $fee_logic = CRM_Membership_FeeChangeLogic::getSingleton();
      $fee_logic->membershipFeeUpdatePRE($id);
    }

  } elseif ($objectName == 'Contribution' && $op == 'edit') {
    $logic = CRM_Membership_PaidByLogic::getSingleton();
    $logic->contributionUpdatePRE($id, $params);

  } elseif ($objectName == 'ContributionRecur') {
    // catch if a membership is set to a certain status
    if (!empty($id) && ($op == 'create' || $op == 'edit')) {
      $fee_logic = CRM_Membership_FeeChangeLogic::getSingleton();
      $fee_logic->membershipFeeUpdatePRE(NULL, $id);
    }
  }
}

/**
 * CiviCRM POST Hook
 *
 * @access public
 */
function membership_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  //CRM_Core_Error::debug_log_message("civicrm_post $op, $objectName, $objectId");
  if ($objectName == 'Membership') {
    if (!empty($objectId) && $op == 'create') {
      $logic = CRM_Membership_PaidByLogic::getSingleton();
      $logic->createMembershipUpdatePOST($objectId, $objectRef);
      $logic->membershipUpdatePOST($objectId, $objectRef);
    } elseif (!empty($objectId) && $op == 'edit') {
      $logic = CRM_Membership_PaidByLogic::getSingleton();
      $logic->membershipUpdatePOST($objectId, $objectRef);

      $fee_logic = CRM_Membership_FeeChangeLogic::getSingleton();
      $fee_logic->membershipFeeUpdatePOST($objectId);
    }
  }
  if ($objectName == 'MembershipPayment' && $op == 'create') {
    $logic = CRM_Membership_PaidByLogic::getSingleton();
    $logic->membershipPaymentCreatePOST($objectRef->contribution_id, $objectRef->membership_id);
  }
  if ($objectName == 'Contribution' && $op == 'edit') {
    $logic = CRM_Membership_PaidByLogic::getSingleton();
    $logic->contributionUpdatePOST($objectId, $objectRef);
  }

  // update derived fields:
  if (!empty($objectId) && ($op == 'create' || $op == 'edit')) {
    switch ($objectName) {
      case 'Membership':
        // update membership
        $logic = CRM_Membership_PaidByLogic::getSingleton();
        $logic->updateDerivedFields($objectId);
        break;
      case 'ContributionRecur':
        // update
        $logic = CRM_Membership_PaidByLogic::getSingleton();
        $membership_ids = $logic->getMembershipIDs($objectId);
        foreach ($membership_ids as $membership_id) {
          $logic->updateDerivedFields($objectId);
        }
        // monitor amount
        $fee_logic = CRM_Membership_FeeChangeLogic::getSingleton();
        $fee_logic->membershipFeeUpdatePOST(NULL, $objectId);
        break;
      default:
        // do nothing
    }
  }
}

/**
 * CiviCRM Custom (post) Hook
 *
 * @access public
 */
function membership_civicrm_custom( $op, $groupID, $entityID, &$params ) {
  //CRM_Core_Error::debug_log_message("civicrm_custom $op, $groupID, $entityID ");// . json_encode($params));
  if ($op == 'create' || $op == 'edit' ) {
    $fee_logic = CRM_Membership_FeeChangeLogic::getSingleton();
    $fee_logic->membershipFeeUpdateWrapup($groupID, $entityID, $params);
  }
}


/**
 * Implements hook_civicrm_buildForm().
 *
 * Insert
 *
 * @param string $formName
 * @param CRM_Core_Form $form
 */
function membership_civicrm_buildForm($formName, &$form) {
  // first: general UI mods
  CRM_Membership_UiMods::adjustForm($formName, $form);

  // then inject paid-via stuff - if enabled
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
  CRM_Membership_TokenLogic::getSingleton()->tokens($tokens);
}

/**
 * Hook implementation: New Tokens
 */
function membership_civicrm_tokenValues(&$values, $cids, $job = null, $tokens = array(), $context = null) {
  // compatibility: extract contact_ids
  if (is_string($cids)) {
    $contact_ids = explode(',', $cids);
  } elseif (isset($cids['contact_id'])) {
    $contact_ids = array($cids['contact_id']);
  } elseif (is_array($cids)) {
    $contact_ids = $cids;
  } else {
    CRM_Core_Error::debug_log_message("Cannot interpret cids: " . json_encode($cids));
    return;
  }

  CRM_Membership_TokenLogic::getSingleton()->tokenValues($values, $contact_ids, $job, $tokens, $context);
}

/**
 * Hook implementation: New Tokens
 */
function membership_civicrm_summary( $contactID, &$content, &$contentPlacement ) {
  CRM_Membership_NumberLogic::adjustSummaryView($contactID);
}