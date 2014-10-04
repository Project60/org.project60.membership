<?php
/*-------------------------------------------------------+
| Project 60 - Membership Extension                      |
| Copyright (C) 2013-2014 SYSTOPIA                       |
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
    $this->assign("membership_types", $membership_types);

    // load financial types
    $financial_types = CRM_Contribute_PseudoConstant::financialType();
    $this->assign("financial_types", $financial_types);
    
    // add elements
    foreach ($membership_types as $membership_type_id => $membership_type_name) {
      $this->addElement('select', "syncmap_$membership_type_id", $membership_type_name, $financial_types);
    }

    parent::buildQuickForm();
  }

  function postProcess() {
    // TODO
  }
}