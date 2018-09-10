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

use CRM_Membership_ExtensionUtil as E;

/**
 * Edit membership paid_via field
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Membership_Form_PaidBy extends CRM_Core_Form {

  public function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts('Change Membership Payment'));

    $settings = CRM_Membership_Settings::getSettings();
    $logic    = CRM_Membership_PaidByLogic::getSingleton();
    $paid_via = $settings->getPaidViaField();
    if (!$paid_via) {
      CRM_Core_Session::setStatus(E::ts("Paid Via Field not enabled!"), E::ts('Error'), 'error');
      return;
    }

    // get some IDs
    $membership_id = CRM_Utils_Request::retrieve('mid',  'Integer');
    $membership = civicrm_api3('Membership', 'getsingle', array('id' => $membership_id));
    $contribution_recur = $logic->getRecurringContribution($membership_id);

    // see if there is a 'paid by'
    $paid_by_id = $settings->getPaidByFieldID();
    if ($paid_by_id) {
      $paid_by_field_name = "custom_{$paid_by_id}_id";
      if (!empty($membership[$paid_by_field_name])) {
        $membership['paid_by'] = $membership[$paid_by_field_name];
      }
    }

    // add vars:
    $this->assign('paid_by_current', $contribution_recur);
    $this->assign('membership',      $membership);

    // add form elements
    $this->add('hidden', 'selected_contribution_rcur_id', $contribution_recur ? $contribution_recur['id'] : '');
    $this->add('hidden', 'membership_id', $membership_id);

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Save Changes'),
        'isDefault' => TRUE,
      ),
    ));

    // export form elements
    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();

    // Store selection
    if (!empty($values['membership_id']) && isset($values['selected_contribution_rcur_id'])) {
      $logic = CRM_Membership_PaidByLogic::getSingleton();
      $logic->changeContract($values['membership_id'], $values['selected_contribution_rcur_id']);
    }

    parent::postProcess();
  }
}
