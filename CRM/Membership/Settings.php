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
 
/**
 * Settings logic for membership extension
 */
class CRM_Membership_Settings {

  /**
   * get the syncmap property
   * default is the mapping that is defined by the membership_types' financial type id
   *
   * @return array([financial_type_id] => array(membership_type_id))
   */
  public static function getSyncMapping() {
    $mapping_json = CRM_Core_BAO_Setting::getItem('Membership Payments', 'sync_mapping');
    $mapping = json_decode($mapping_json, TRUE);
    if (empty($mapping)) {
      return CRM_Membership_Settings::_getDefaultSyncmap();
    } else {
      return $mapping;
    }
  }

  /**
   * get the sync range property (number of days)
   * which describes how far back the membership<->payment mapping should be performed
   *
   * default is 400 (days)
   * @return int
   */
  public static function getSyncRange() {
    return (int) CRM_Core_BAO_Setting::getItem('Membership Payments', 'sync_rangeback');
  }

  /**
   * get the sync range property (number of days)
   * which describes how far back the membership<->payment mapping should be performed
   *
   * default is 32 (days)
   * @return int
   */
  public static function getSyncGracePeriod() {
    return (int) CRM_Core_BAO_Setting::getItem('Membership Payments', 'synce_graceperiod');
  }

  /**
   * set the syncmap property
   * @param $syncmap array([financial_type_id] => array(membership_type_ids))
   */
  public static function setSyncMapping($syncmap) {
    CRM_Core_BAO_Setting::setItem(json_encode($syncmap), 'Membership Payments', 'sync_mapping');
  }

  /**
   * set the sync range property (number of days)
   * which describes how far back the membership<->payment mapping should be performed
   *
   * default is 400 (days)
   * @return $range int
   */
  public static function setSyncRange($range) {
    CRM_Core_BAO_Setting::setItem($range, 'Membership Payments', 'sync_rangeback');
  }

  /**
   * set the sync range property (number of days)
   * which describes how far back the membership<->payment mapping should be performed
   *
   * default is 32 (days)
   * @return $range int
   */
  public static function setSyncGracePeriod($value) {
    CRM_Core_BAO_Setting::setItem($value, 'Membership Payments', 'synce_graceperiod');
  }

  /**
   * extracts the default syncmapString from the membership types
   */
  protected static function _getDefaultSyncmap() {
    $mapping = array();
    $membership_types = civicrm_api3('MembershipType', 'get', 
       array('is_active' => 1, 'option.limit' => 9999));
    if (empty($membership_types['is_error'])) {
      foreach ($membership_types['values'] as $membership_type) {
        $key = $membership_type['id'];
        $value = $membership_type['financial_type_id'];
        if (!empty($key) && !empty($value)) {
          if (isset($mapping[$key])) {
            $mapping[$key][] = $value;
          } else {
            $mapping[$key] = array($value);
          }
        }
      }
    } else {
      error_log("org.project60.membership: Cannot read membership types - ".$membership_types['error_message']);
    }
    return $mapping;
  }
}