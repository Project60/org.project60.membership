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

require_once 'CRM/Admin/Form/Setting.php';

use CRM_Membership_ExtensionUtil as E;

/**
 * Configuration page for the Project60 Membership extension
 */
class CRM_Admin_Form_Setting_MembershipExtension extends CRM_Admin_Form_Setting {

  const CUSTOM_MEMBERSHIP_TOKEN_COUNT = 5;

  protected $_eligibleCustomGroups = NULL;

  public function buildQuickForm( ) {
    CRM_Utils_System::setTitle(E::ts('Configuration - Project60 Membership Extension'));

    // load membership types
    $membership_types = CRM_Member_BAO_MembershipType::getMembershipTypes(FALSE);
    // $membership_types[0] = E::ts('Not membership related');

    $this->assign("membership_types", $membership_types);

    // load financial types
    $financial_types = CRM_Contribute_PseudoConstant::financialType();
    $this->assign("financial_types", $financial_types);

    // load status options
    $membership_statuses = array();
    $statusQuery = civicrm_api3('MembershipStatus', 'getlist');
    foreach ($statusQuery['values'] as $status) {
      $membership_statuses[$status['id']] = $status['label'];
    }

    // get sync_mapping
    $settings = CRM_Membership_Settings::getSettings();
    $sync_mapping = $settings->getSyncMapping();
    $this->assign("sync_mapping", json_encode($sync_mapping));

    // get sync_range
    $sync_range = $settings->getSyncRange();
    $this->assign("sync_range", $sync_range);

    // get grace_period
    $grace_period = $settings->getSyncGracePeriod();
    $this->assign("grace_period", $grace_period);

    // add elements
    foreach ($financial_types as $financial_type_id => $financial_type_name) {
      $this->addElement('select',
                        "syncmap_$financial_type_id",
                        $financial_type_name,
                        $membership_types,
                        array('multiple' => "multiple", 'class' => 'crm-select2'));
    }

    $this->addElement('text',
                      "sync_range",
                      E::ts("Backward horizon (in days)"),
                      array('value' => $sync_range));
    $this->addElement('text',
                      "grace_period",
                      E::ts("Grace Period (in days)"),
                      array('value' => $grace_period));

    $this->addElement('select',
                      "live_statuses",
                      E::ts("Live Statuses"),
                      $membership_statuses,
                      array('multiple' => "multiple", 'class' => 'crm-select2'));

    // add from/to date restrictions for syncing
    $this->addElement('text',
        "sync_minimum_date",
        E::ts("Only Synchronise Between"));
    $this->addElement('text',
        "sync_maximum_date",
        E::ts("Only Synchronise Between"));

    // add membership number integration fields
    $this->addElement('select',
        "membership_number_field",
        E::ts("Membership Number Field"),
        $this->getMembershipNumberOptions(),
        array('class' => 'crm-select2'));

    $this->addElement('checkbox',
        "membership_number_show",
        E::ts("Show in Summary View"));

    $this->addElement('text',
        "membership_number_generator",
        E::ts("Number Pattern"));


    // add payment integration fields
    $this->addElement('select',
        "paid_via_field",
        E::ts("Payment Contract (paid via) Field"),
        $this->getPaidViaOptions(),
        array('class' => 'crm-select2'));

    $this->addElement('select',
        "annual_amount_field",
        E::ts("Annual Amount Field"),
        $this->getAmountFieldOptions(FALSE),
        array('class' => 'crm-select2'));

    $this->addElement('select',
        "installment_amount_field",
        E::ts("Installment Amount Field"),
        $this->getAmountFieldOptions(TRUE),
        array('class' => 'crm-select2'));

    $this->addElement('select',
        "diff_amount_field",
        E::ts("Annual Gap Field"),
        $this->getAmountFieldOptions(TRUE),
        array('class' => 'crm-select2'));

    $this->addElement('select',
        "payment_frequency_field",
        E::ts("Payment Frequency Field"),
        $this->getSelectFieldOptions(),
        array('class' => 'crm-select2'));

    $this->addElement('select',
        "payment_type_field",
        E::ts("Payment Type Field"),
        $this->getSelectFieldOptions(),
        array('class' => 'crm-select2'));

    $this->addElement('text',
        "payment_type_field_mapping",
        E::ts("Payment Type Field Mapping"));

    $this->addElement('checkbox',
        'synchronise_payment_now',
        E::ts("Update Derived Fields on Save"));

    // add cancellation fields
    $this->addElement('select',
        "membership_cancellation_date_field",
        E::ts("Cancel Date Field"),
        $this->getDateFieldOptions(),
        array('class' => 'crm-select2'));

    $this->addElement('select',
        "membership_cancellation_reason_field",
        E::ts("Cancel Reason Field"),
        $this->getSelectFieldOptions(),
        array('class' => 'crm-select2'));


    // logic fields
    $this->addElement('select',
        "paid_via_end_with_status",
        E::ts("End with Statuses"),
        $membership_statuses,
        array('multiple' => "multiple", 'class' => 'crm-select2'));

    $this->addElement('checkbox',
        "update_membership_status",
        E::ts("Extend membership when contribution is completed"));

    $this->addElement('checkbox',
        "hide_auto_renewal",
        E::ts("Hide Auto Renewal"));

    $this->addElement('select',
        "paid_by_field",
        E::ts("Paid by Field"),
        $this->getPaidByOptions(),
        array('class' => 'crm-select2'));

    // add extra tokens
    $this->assign('custom_token_indices', range(1, self::CUSTOM_MEMBERSHIP_TOKEN_COUNT));
    $all_custom_fields = $this->getAllCustomFields();
    for ($i = 1; $i <= self::CUSTOM_MEMBERSHIP_TOKEN_COUNT; $i++) {
      $this->addElement('select',
          "custom_token_{$i}",
          E::ts("Additional Token %1", [1 => $i]),
          $all_custom_fields,
          array('class' => 'crm-select2'));
    }

    $this->addElement('checkbox',
        "record_fee_updates",
        E::ts("Record annual fee changes"));

    parent::buildQuickForm();
  }


  /**
   * preset the current values as default
   */
  public function setDefaultValues() {
    $defaults = parent::setDefaultValues();
    $settings = CRM_Membership_Settings::getSettings();
    $current_values = $settings->getAllSettings();
    foreach ($current_values as $key => $value) {
      $defaults[$key] = $value;
    }

    return $defaults;
  }

  /**
   * general postProcess to store the values
   */
  function postProcess() {
    $values = $this->controller->exportValues($this->_name);
    // extract & set the sync mapping
    $sync_mapping = array();
    foreach ($values as $key => $value) {
      $key_prefix = substr($key, 0, 7);
      if ($key_prefix == 'syncmap') {
        $key_id = substr($key, 8);
        $sync_mapping[$key_id] = $value;
      }
    }

    // save new settings
    $settings = CRM_Membership_Settings::getSettings();
    $settings->setSetting('sync_mapping',    $sync_mapping, FALSE);
    $settings->setSetting('sync_range',      $values['sync_range'], FALSE);
    $settings->setSetting('grace_period',    $values['grace_period'], FALSE);
    $settings->setSetting('sync_minimum_date', $values['sync_minimum_date'], FALSE);
    $settings->setSetting('sync_maximum_date', $values['sync_maximum_date'], FALSE);
    $settings->setSetting('membership_number_field',  $values['membership_number_field'], FALSE);
    $settings->setSetting('membership_number_generator',  $values['membership_number_generator'], FALSE);
    $settings->setSetting('membership_number_show', CRM_Utils_Array::value('membership_number_show', $values), FALSE);
    $settings->setSetting('hide_auto_renewal', CRM_Utils_Array::value('hide_auto_renewal', $values), FALSE);
    $settings->setSetting('paid_via_field',  $values['paid_via_field'], FALSE);
    $settings->setSetting('record_fee_updates', CRM_Utils_Array::value('record_fee_updates', $values), FALSE);
    $settings->setSetting('update_membership_status',  CRM_Utils_Array::value('update_membership_status', $values), FALSE);
    $settings->setSetting('paid_by_field',   $values['paid_by_field'], FALSE);
    $settings->setSetting('annual_amount_field',        $values['annual_amount_field'], FALSE);
    $settings->setSetting('installment_amount_field',   $values['installment_amount_field'], FALSE);
    $settings->setSetting('diff_amount_field',          $values['diff_amount_field'], FALSE);
    $settings->setSetting('payment_frequency_field',    $values['payment_frequency_field'], FALSE);
    $settings->setSetting('payment_type_field',         $values['payment_type_field'], FALSE);
    $settings->setSetting('payment_type_field_mapping', $values['payment_type_field_mapping'], FALSE);
    $settings->setSetting('membership_cancellation_date_field', $values['membership_cancellation_date_field'], FALSE);
    $settings->setSetting('membership_cancellation_reason_field', $values['membership_cancellation_reason_field'], FALSE);

    // set custom tokens
    for ($i = 1; $i <= self::CUSTOM_MEMBERSHIP_TOKEN_COUNT; $i++) {
      $key = "custom_token_{$i}";
      $settings->setSetting($key, $values[$key], FALSE);
    }

    if (is_array($values['live_statuses']) && !empty($values['live_statuses'])) {
      $settings->setSetting('live_statuses', $values['live_statuses'], FALSE);
    }
    if (is_array($values['paid_via_end_with_status']) && !empty($values['paid_via_end_with_status'])) {
      $settings->setSetting('paid_via_end_with_status', $values['paid_via_end_with_status'], FALSE);
    }
    $settings->write();

    // issue warnings for strtotime fields
    $settings->getStrtotimeDate('sync_minimum_date', TRUE);
    $settings->getStrtotimeDate('sync_maximum_date', TRUE);


    // update fields if requested
    if (!empty($values['synchronise_payment_now'])) {
      $logic = CRM_Membership_PaidByLogic::getSingleton();
      $logic->updateDerivedFields();
    }

    // stay on this page
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/admin/setting/membership', 'reset=1'));
  }


  // HELPER FUNCTIONS

  /**
   * Get all eligible fields to be used as paid_via
   * @return array options
   */
  protected function getPaidViaOptions() {
    $options = array('' => E::ts('Disabled'));
    $custom_group_ids = $this->getEligibleCustomGroups();

    // find all custom fields that are
    //  1) attached to a membership
    //  2) of type Int
    //  3) not multivalue
    //  4) indexed
    //  5) read-only

    // if there is eligible groups, look for fields
    if (!empty($custom_group_ids)) {
      $custom_fields = civicrm_api3('CustomField', 'get', array(
        'custom_group_id' => array('IN' => $custom_group_ids),
        'data_type'       => 'Int',
        'is_active'       => 1,
        'is_view'         => 1,
        'is_searchable'   => 1,
        'return'          => 'id,label'));
      foreach ($custom_fields['values'] as $custom_field) {
        $options[$custom_field['id']] = $custom_field['label'];
      }
    }

    return $options;
  }


  /**
   * Get all eligible fields to be used membership number
   * @return array options
   */
  protected function getMembershipNumberOptions() {
    $options = array('' => E::ts('Disabled'));
    $custom_group_ids = $this->getEligibleCustomGroups();

      // find all custom fields that are
      //  1) attached to a membership
      //  2) of type Int
      //  3) not multivalue
      //  4) indexed
      //  5) read-only

      // if there is eligible groups, look for fields
      if (!empty($custom_group_ids)) {
          $custom_fields = civicrm_api3('CustomField', 'get', array(
              'custom_group_id' => array('IN' => $custom_group_ids),
              'data_type'       => 'String',
              'html_type'       => 'Text',
              'is_active'       => 1,
              'is_view'         => 0,
              'is_searchable'   => 1,
              'return'          => 'id,label'));
          foreach ($custom_fields['values'] as $custom_field) {
              $options[$custom_field['id']] = $custom_field['label'];
          }
      }

      return $options;
  }

  /**
   * Get all eligible fields to be used as frequency field
   * @return array options
   */
  protected function getSelectFieldOptions() {
    $options = array('' => E::ts('Disabled'));
    $custom_group_ids = $this->getEligibleCustomGroups();

    // find all custom fields that are
    //  1) attached to a membership
    //  2) of type Int
    //  3) not multivalue
    //  4) indexed
    //  5) read-only

    // if there is eligible groups, look for fields
    if (!empty($custom_group_ids)) {
      $custom_fields = civicrm_api3('CustomField', 'get', array(
          'custom_group_id' => array('IN' => $custom_group_ids),
          'data_type'       => 'String',
          'html_type'       => 'Select',
          'is_active'       => 1,
//          'is_view'         => 1,
          'is_searchable'   => 1,
          'return'          => 'id,label'));
      foreach ($custom_fields['values'] as $custom_field) {
        $options[$custom_field['id']] = $custom_field['label'];
      }
    }

    return $options;
  }


  /**
   * Get all eligible fields to be used as paid_via
   * @return array options
   */
  protected function getPaidByOptions() {
    $options = array('' => E::ts('Disabled'));
    $custom_group_ids = $this->getEligibleCustomGroups();

    // find all custom fields that are
    //  1) attached to a membership
    //  2) of type contact reference
    //  3) indexed

    // if there is eligible groups, look for fields
    if (!empty($custom_group_ids)) {
      $custom_fields = civicrm_api3('CustomField', 'get', array(
        'custom_group_id' => array('IN' => $custom_group_ids),
        'data_type'       => 'ContactReference',
        'is_active'       => 1,
        'is_searchable'   => 1,
        'return'          => 'id,label'));
      foreach ($custom_fields['values'] as $custom_field) {
        $options[$custom_field['id']] = $custom_field['label'];
      }
    }

    return $options;
  }


  /**
   * Get all eligible fields to be used as paid_via
   * @return array options
   */
  protected function getAmountFieldOptions($read_only) {
    $options = array('' => E::ts('Disabled'));
    $custom_group_ids = $this->getEligibleCustomGroups();

    // find all custom fields that are
    //  1) attached to a membership
    //  2) of type money
    //  3) indexed

    // if there is eligible groups, look for fields
    if (!empty($custom_group_ids)) {
      $custom_fields = civicrm_api3('CustomField', 'get', array(
          'custom_group_id' => array('IN' => $custom_group_ids),
          'data_type'       => 'Money',
          'is_active'       => 1,
          'is_searchable'   => 1,
          'is_view'         => $read_only ? '1' : '0',
          'return'          => 'id,label'));
      foreach ($custom_fields['values'] as $custom_field) {
        $options[$custom_field['id']] = $custom_field['label'];
      }
    }

    return $options;
  }


  /**
   * Get all membership custom fields
   *
   * @return array options
   */
  protected function getAllCustomFields() {
    $options = array('' => E::ts('None'));
    $custom_group_ids = $this->getEligibleCustomGroups();

    // find all custom fields that are
    //  1) attached to a membership
    //  2) of type money
    //  3) indexed

    // if there is eligible groups, look for fields
    if (!empty($custom_group_ids)) {
      $custom_fields = civicrm_api3('CustomField', 'get', array(
          'custom_group_id' => array('IN' => $custom_group_ids),
          'is_active'       => 1,
          'return'          => 'id,label'));
      foreach ($custom_fields['values'] as $custom_field) {
        $options[$custom_field['id']] = $custom_field['label'];
      }
    }

    return $options;
  }

  /**
   * Get all eligible fields to be used as date
   * @return array options
   */
  protected function getDateFieldOptions() {
    $options = array('' => E::ts('Disabled'));
    $custom_group_ids = $this->getEligibleCustomGroups();

    // find all custom fields that are
    //  1) attached to a membership
    //  2) of type money
    //  3) indexed

    // if there is eligible groups, look for fields
    if (!empty($custom_group_ids)) {
      $custom_fields = civicrm_api3('CustomField', 'get', array(
          'custom_group_id' => array('IN' => $custom_group_ids),
          'data_type'       => 'Date',
          'is_active'       => 1,
          'is_searchable'   => 1,
          'return'          => 'id,label'));
      foreach ($custom_fields['values'] as $custom_field) {
        $options[$custom_field['id']] = $custom_field['label'];
      }
    }

    return $options;
  }

  /**
   * get a list of custom group ids of groups eligible
   * to contain the fields we're interested in
   *
   * @return array group_ids
   */
  protected function getEligibleCustomGroups() {
    if ($this->_eligibleCustomGroups === NULL) {
      $custom_groups = civicrm_api3('CustomGroup', 'get', array(
          'extends'     => 'Membership',
          'is_active'   => '1',
          'is_multiple' => '0',
          'return'      => 'id'));
      $this->_eligibleCustomGroups = array();
      foreach ($custom_groups['values'] as $custom_group) {
        $this->_eligibleCustomGroups[] = $custom_group['id'];
      }
    }
    return $this->_eligibleCustomGroups;
  }
}