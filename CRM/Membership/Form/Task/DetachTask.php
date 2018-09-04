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

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Membership_Form_Task_DetachTask extends CRM_Contribute_Form_Task {

  function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts('Detach Contributions from Membership'));

    // compile an info text
    $infotext = E::ts("%1 of the %2 contributions are currently attached to a membership.", array(
      1 => $this->getAssignedCount(),
      2 => count($this->_contributionIds)));
    $this->assign('infotext', $infotext);

    // call the (overwritten) Form's method, so the continue button is on the right...
    CRM_Core_Form::addDefaultButtons(E::ts('Detach'));
  }

  function postProcess() {
    // get the count
    $count = $this->getAssignedCount();

    // simply do this by SQL
    $id_list = implode(',', $this->_contributionIds);
    if (!empty($id_list)) {
      CRM_Core_DAO::executeQuery("DELETE FROM civicrm_membership_payment WHERE contribution_id IN ({$id_list})");
    }

    CRM_Core_Session::setStatus(E::ts("%1 contributions have been detached from their memberships.", array(1 => $count)), ts('Success'), 'info');
  }

  /**
   * get the number of assigned contributions
   */
  protected function getAssignedCount() {
    $id_list = implode(',', $this->_contributionIds);
    if (empty($id_list)) {
      return 0;
    } else {
      return CRM_Core_DAO::singleValueQuery("SELECT COUNT(id) FROM civicrm_membership_payment WHERE contribution_id IN ({$id_list})");
    }
  }
}
