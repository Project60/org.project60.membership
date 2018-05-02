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

/**
 * Configuration page for the Project60 Membership extension
 */
class CRM_Admin_Form_Setting_MembershipExtension extends CRM_Admin_Form_Setting {

  public function buildQuickForm( ) {
    CRM_Utils_System::setTitle(ts('Configuration - Project60 Membership Extension'));

    // load membership types
    $membership_types = CRM_Member_BAO_MembershipType::getMembershipTypes(FALSE);
    // $membership_types[0] = ts('Not membership related');

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
                      ts("Backward horizon (in days)"),
                      array('value' => $sync_range));
    $this->addElement('text',
                      "grace_period",
                      ts("Grace Period (in days)"),
                      array('value' => $grace_period));

    $this->addElement('select',
                      "live_statuses",
                      ts("Live Statuses"),
                      $membership_statuses,
                      array('multiple' => "multiple", 'class' => 'crm-select2'));


    // add membership number integration fields
    $this->addElement('select',
          "membership_number_field",
          ts("Membership Number Field"),
          $this->getMembershipNumberOptions(),
          array('class' => 'crm-select2'));

      $this->addElement('text',
          "membership_number_generator",
          ts("Number Pattern"));


      // add payment integration fields
    $this->addElement('select',
        "paid_via_field",
        ts("Paid via Field"),
        $this->getPaidViaOptions(),
        array('class' => 'crm-select2'));

    $this->addElement('checkbox',
        "paid_via_linked",
        ts("End with membership"));

    $this->addElement('select',
        "paid_by_field",
        ts("Paid by Field"),
        $this->getPaidByOptions(),
        array('class' => 'crm-select2'));

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
    $settings->setSetting('membership_number_field',  $values['membership_number_field'], FALSE);
    $settings->setSetting('membership_number_generator',  $values['membership_number_generator'], FALSE);
    $settings->setSetting('paid_via_field',  $values['paid_via_field'], FALSE);
    $settings->setSetting('paid_via_linked', CRM_Utils_Array::value('paid_via_linked', $values), FALSE);
    $settings->setSetting('paid_by_field',   $values['paid_by_field'], FALSE);
    if (is_array($values['live_statuses']) && !empty($values['live_statuses'])) {
      $settings->setSetting('live_statuses', $values['live_statuses'], FALSE);
    }
    $settings->write();
  }


  // HELPER FUNCTIONS

  /**
   * Get all eligible fields to be used as paid_via
   * @return array options
   */
  protected function getPaidViaOptions() {
    $options = array('' => ts('Disabled'));

    // find all custom fields that are
    //  1) attached to a membership
    //  2) of type Int
    //  3) not multivalue
    //  4) indexed
    //  5) read-only
    $custom_groups = civicrm_api3('CustomGroup', 'get', array(
        'extends'     => 'Membership',
        'is_active'   => '1',
        'is_multiple' => '0',
        'return'      => 'id'));
    $custom_group_ids = array();
    foreach ($custom_groups['values'] as $custom_group) {
      $custom_group_ids[] = $custom_group['id'];
    }

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
        $options = array('' => ts('Disabled'));

        // find all custom fields that are
        //  1) attached to a membership
        //  2) of type Int
        //  3) not multivalue
        //  4) indexed
        //  5) read-only
        $custom_groups = civicrm_api3('CustomGroup', 'get', array(
            'extends'     => 'Membership',
            'is_active'   => '1',
            'is_multiple' => '0',
            'return'      => 'id'));
        $custom_group_ids = array();
        foreach ($custom_groups['values'] as $custom_group) {
            $custom_group_ids[] = $custom_group['id'];
        }

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
   * Get all eligible fields to be used as paid_via
   * @return array options
   */
  protected function getPaidByOptions() {
    $options = array('' => ts('Disabled'));

    // find all custom fields that are
    //  1) attached to a membership
    //  2) of type contact reference
    //  3) indexed
    $custom_groups = civicrm_api3('CustomGroup', 'get', array(
        'extends'     => 'Membership',
        'is_active'   => '1',
        'is_multiple' => '0',
        'return'      => 'id'));
    $custom_group_ids = array();
    foreach ($custom_groups['values'] as $custom_group) {
      $custom_group_ids[] = $custom_group['id'];
    }

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
}