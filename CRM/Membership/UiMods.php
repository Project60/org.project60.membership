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
 * This class contains the logic connecting CiviSEPA mandates
 * to memberships
 * @see https://github.com/Project60/org.project60.membership/issues/10
 */
class CRM_Membership_UiMods {

  /**
   * Adjust a membership list (e.g. search result)
   */
  public static function adjustList(&$headers, &$rows, &$selector) {
    $settings = CRM_Membership_Settings::getSettings();
    if ($settings->getSetting('hide_auto_renewal')) {
      // set header
      $headers[8] = array('name' => E::ts("Contract"));

      // extract memberhship IDs
      $membership_ids = array();
      foreach ($rows as $index => $row) {
        $membership_ids[] = $row['membership_id'];
      }

      // render the payment
      $logic = CRM_Membership_PaidByLogic::getSingleton();
      $membership2rcontribution = $logic->getRecurringContributions($membership_ids);

      // set to data
      foreach ($rows as $index => &$row) {
        $membership_id = $row['membership_id'];
        $row['auto_renew'] = !empty($membership2rcontribution[$membership_id]);
      }
    }
  }

  /**
   * Adjust a form
   */
  public static function adjustForm($formName, $form) {
    if ($formName == 'CRM_Member_Form_MembershipView') {
      $settings = CRM_Membership_Settings::getSettings();
      $paid_via_field = $settings->getPaidViaField();
      if ($paid_via_field && $settings->getSetting('hide_auto_renewal')) {
        CRM_Core_Smarty::singleton()->assign('auto_renewal_label', E::ts('Auto Renew'));
        CRM_Core_Region::instance('page-body')->add(array(
            'template' => 'CRM/Membership/Snippets/HideAutoRenewal.tpl',
        ));
      }
    }
  }
}