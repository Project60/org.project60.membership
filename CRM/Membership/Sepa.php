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

/**
 * This class contains the logic connecting CiviSEPA mandates
 * to memberships
 * @see https://github.com/Project60/org.project60.membership/issues/10
 */
class CRM_Membership_Sepa {

    protected static $singleton = NULL;

    public static function getSingleton() {
      if (self::$singleton === NULL) {
        self::$singleton = new CRM_Membership_Sepa();
      }
      return self::$singleton;
    }

    /**
     * Connect a newly create CiviSEPA installment to a membership if applicable
     * @param $mandate_id
     * @param $contribution_recur_id
     * @param $contribution_id
     */
    public function assignInstallment($mandate_id, $contribution_recur_id, $contribution_id) {
      // see if there is a paid_via field
      $settings = CRM_Membership_Settings::getSettings();
      $paid_via = $settings->getPaidViaField();
      if (!$paid_via) return;

      // then assign
      CRM_Core_DAO::executeQuery("
        INSERT IGNORE INTO civicrm_membership_payment (membership_id, contribution_id)
          SELECT 
            entity_id           AS membership_id,
            {$contribution_id}  AS contribution_id
          FROM {$paid_via['table_name']}
          WHERE {$paid_via['column_name']} = {$contribution_recur_id};");
    }
}