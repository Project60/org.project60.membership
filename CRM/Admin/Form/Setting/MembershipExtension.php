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
    $membership_types = array_merge(array(0 => ts('Not membership related')), $membership_types);
    $this->assign("membership_types", $membership_types);

    // load financial types
    $financial_types = CRM_Contribute_PseudoConstant::financialType();
    $this->assign("financial_types", $financial_types);
    
    // get sync_mapping
    $sync_mapping = CRM_Membership_Settings::getSyncMapping();
    $this->assign("sync_mapping", json_encode($sync_mapping));

    // get sync_range
    $sync_range = CRM_Membership_Settings::getSyncRange();
    $this->assign("sync_range", $sync_range);

    // add elements
    foreach ($financial_types as $financial_type_id => $financial_type_name) {
      $this->addElement('select',
                        "syncmap_$financial_type_id", 
                        $financial_type_name, 
                        $membership_types);
    }

    $this->addElement('text',
                      "sync_range", 
                      ts("Backward horizon (in days)"),
                      array('value' => $sync_range));

    parent::buildQuickForm();
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
      if ($key_prefix == 'syncmap' && !empty($value)) {
        $key_id = substr($key, 8);
        $sync_mapping[$key_id] = $value;
      }
    }
    CRM_Membership_Settings::setSyncMapping($sync_mapping);

    // set the range
    CRM_Membership_Settings::setSyncRange($values['sync_range']);
  }
}