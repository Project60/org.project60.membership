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

use CRM_Membership_ExtensionUtil as E;

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Membership_Form_Task_AssignTask extends CRM_Contribute_Form_Task {

  // stores the calculated default contact
  static $default_contact = NULL;


  function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts('Assign Contributions to Membership'));

    // assign some values to the form
    $membership_type_data = array();
    $membership_types = civicrm_api3('MembershipType', 'getlist');
    foreach ($membership_types['values'] as $membership_type) {
      $membership_type_data[$membership_type['id']] = $membership_type['label'];
    }
    $this->assign('membership_types', json_encode($membership_type_data));

    $membership_status_data = array();
    $membership_statuses = civicrm_api3('MembershipStatus', 'getlist');
    foreach ($membership_statuses['values'] as $membership_status) {
      $membership_status_data[$membership_status['id']] = $membership_status['label'];
    }
    $this->assign('membership_statuses', json_encode($membership_status_data));


    // add form elements
    $field = $this->addElement('text', 
                      'contact',
                      E::ts('Contact'),
                      array('class' => 'huge crm-form-contact-reference', 'data-api-entity' => 'Contact'));
    $customUrls['contact'] = CRM_Utils_System::url('civicrm/ajax/rest', "entity=MembershipPayment&action=getlist&json=1", FALSE, NULL, FALSE);
    $this->assign('customUrls', $customUrls);
    
    // add contact selector
    $this->addElement('select', 
                      'membership',
                      E::ts('Membership'),
                      array(), 
                      array('class' => 'huge crm-select2'));

    // add some stats
    $default_contact = $this->getDefaultContact();
    $this->assign('default_contact_id',    $default_contact[0]);
    $this->assign('default_contact_label', $default_contact[1]);
    $this->assign('contribution_count',    count($this->_contributionIds));

    // add paid by field
    $settings = CRM_Membership_Settings::getSettings();
    $paid_by_field_id = $settings->getPaidByFieldID();
    if ($paid_by_field_id) {
      $this->assign('paid_by_field', "custom_{$paid_by_field_id}");
    } else {
        $this->assign('paid_by_field', "");
    }

    // call the (overwritten) Form's method, so the continue button is on the right...
    CRM_Core_Form::addDefaultButtons(E::ts('Assign'));
  }


  public function setDefaultValues() {
    $defaults = parent::setDefaultValues();

    $default_contact = $this->getDefaultContact();
    $defaults['contact'] = $default_contact[0];
    return $defaults;
  }


  function postProcess() {

    $values = $this->exportValues();
    if (!empty($values['membership']) && !empty($this->_contributionIds)) {
      $membership_id = $values['membership'];
      // load the current assignment status
      $mapping = array();
      $load_mapping = civicrm_api3('MembershipPayment', 'get', array(
        'contribution_id' => array('IN' => $this->_contributionIds),
        'option.limit'    => 0));
      foreach ($load_mapping['values'] as $membership_payment) {
        $mapping[$membership_payment['contribution_id']] = $membership_payment;
      }

      // now go through all the contributions
      $newly_assigned = $reassigned = $already_assigned = 0;
      foreach ($this->_contributionIds as $contribution_id) {
        if (isset($mapping[$contribution_id])) {
          $membership_payment = $mapping[$contribution_id];
          if ($membership_payment['membership_id'] == $membership_id) {
            // this is already assigned to the right membership
            $already_assigned++;
          
          } else {
            // this is assigned to another membership
            $reassigned++;
            civicrm_api3('MembershipPayment', 'create', array(
              'id'              => $membership_payment['id'],
              'membership_id'   => $membership_id,
              'contribution_id' => $contribution_id));
          }
        
        } else {
          // this is not assigned yet
          $newly_assigned++;
          civicrm_api3('MembershipPayment', 'create', array(
              'membership_id'   => $membership_id,
              'contribution_id' => $contribution_id));
        }
      }

      CRM_Core_Session::setStatus(E::ts("%1 contributions have been newly assigned, %2 have been re-assigned. %3 contributions were already assigned to the selected membership.", array(1 => $newly_assigned, 2 => $reassigned, 3 => $already_assigned)), ts('Success'), 'info');

    } else {
      // something went wrong
      CRM_Core_Session::setStatus(E::ts("Invalid values."), E::ts('Error'), 'error');
    }
  }


  /**
   * returns array(contact_id, label) 
   *  if there is ONLY ONE contact associated with the contributions
   */
  protected function getDefaultContact() {
    if (self::$default_contact === NULL) {
      if (empty($this->_contributionIds)) {
        self::$default_contact = array(0, NULL);
      } else {
        $contribution_id_list = implode(',', $this->_contributionIds);
        $contacts = CRM_Core_DAO::executeQuery("
            SELECT civicrm_contribution.contact_id AS contact_id,
                   COUNT(DISTINCT(contact_id))     AS contact_count,
                   civicrm_contact.sort_name       AS sort_name
            FROM civicrm_contribution
            LEFT JOIN civicrm_contact ON civicrm_contact.id = civicrm_contribution.contact_id
            WHERE civicrm_contribution.id IN ({$contribution_id_list})");
        if ($contacts->fetch()) {
          if ($contacts->contact_count == 1) {
            self::$default_contact = array($contacts->contact_id, "[{$contacts->contact_id}] {$contacts->sort_name}");
          } else {
            self::$default_contact = array(0, NULL);
          }
        }
      }
    }

    return self::$default_contact;
  }
}
