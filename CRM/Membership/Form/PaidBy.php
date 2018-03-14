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
    CRM_Utils_System::setTitle(ts('Change Membership Payment'));

    $settings = CRM_Membership_Settings::getSettings();
    $logic    = CRM_Membership_PaidByLogic::getSingleton();
    $paid_via = $settings->getPaidViaField();
    if (!$paid_via) {
      CRM_Core_Session::setStatus(ts("Paid Via Field not enabled!"), ts('Error'), 'error');
      return;
    }

    // get some IDs
    $membership_id = CRM_Utils_Request::retrieve('mid',  'Integer');
    $membership = civicrm_api3('Membership', 'getsingle', array('id' => $membership_id));
    $contribution_recur = $logic->getRecurringContribution($paid_via, $membership_id);

    // add vars:
    $this->assign('paid_by_current', $contribution_recur);
    $this->assign('membership',      $membership);

    // add form elements
    $this->add('hidden', 'selected_contribution_rcur_id');

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

    // TODO: store selection
    error_log(json_encode($values));

    parent::postProcess();
  }
}
