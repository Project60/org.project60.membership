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
 * Collection of upgrade steps.
 */
class CRM_Membership_Upgrader extends CRM_Extension_Upgrader_Base {

  /**
   * We want to convert the old settings to the new bucket version
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_0500() {
    $this->ctx->log->info('Converting settings...');

    // see if there is a new bucket
    $new_settings = CRM_Core_BAO_Setting::getItem('Membership Payments', 'p60_membership_settings');
    if (!$new_settings) {
      $mapping = array(
        'sync_mapping'      => 'sync_mapping',
        'sync_rangeback'    => 'sync_range',
        'synce_graceperiod' => 'sync_graceperiod',
        'live_statuses'     => 'live_statuses');

      $new_settings = array();
      foreach ($mapping as $old_key => $new_key) {
        $old_value = CRM_Core_BAO_Setting::getItem('Membership Payments', $old_key);
        if ($old_value) {
          $new_settings[$new_key] = $old_value;
        }
      }
    }

    return TRUE;
  }
}
