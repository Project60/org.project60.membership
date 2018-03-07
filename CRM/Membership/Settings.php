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

  /** singleton object */
  protected static $singleton = NULL;

  /** the current settings blob */
  protected $settings_bucket = NULL;

  /** cached data on the paid_via field */
  protected $paid_via_field = NULL;

  /**
   * CRM_Membership_Settings constructor.
   */
  protected function __construct() {
    $this->settings_bucket = CRM_Core_BAO_Setting::getItem('Membership Payments', 'p60_membership_settings');
    if (!is_array($this->settings_bucket)) {
      $this->settings_bucket = array();
    }
  }

  /**
   * Get the settings singleton object
   * @return CRM_Membership_Settings
   */
  public static function getSettings() {
    if (self::$singleton === NULL) {
      self::$singleton = new CRM_Membership_Settings();
    }
    return self::$singleton;
  }

  /**
   * Set a named setting to the given value
   * @param $key   string name
   * @param $value mixed value
   * @param bool $write_through  write to DB right away
   */
  public function setSetting($key, $value, $write_through = TRUE) {
    $this->settings_bucket[$key] = $value;
    if ($write_through) {
      $this->write();
    }
  }

  /**
   * Get a named setting
   * @param $key
   * @return mixed|null
   */
  public function getSetting($key) {
    if (isset($this->settings_bucket[$key])) {
      return $this->settings_bucket[$key];
    } else {
      return NULL;
    }
  }

  /**
   * Write settings to DB
   */
  public function write() {
    CRM_Core_BAO_Setting::setItem($this->settings_bucket, 'Membership Payments', 'p60_membership_settings');
  }
  /**
   * Get the field ID of the selected paid_via field
   * @return int
   */
  public function getPaidViaFieldID() {
    // TODO
    return 2;
  }

  /**
   * Get the field data of the paid_via field
   * or NULL if none set;
   */
  public function getPaidViaField() {
    $paid_via_id = $this->getPaidViaFieldID();
    if ($paid_via_id) {
      if ($this->paid_via_field === NULL) {
        // load the field data
        $this->paid_via_field = civicrm_api3('CustomField', 'getsingle', array(
            'id' => $paid_via_id,
            'return' => 'column_name,id,label,custom_group_id'));
        $this->paid_via_field['key'] = 'custom_' . $paid_via_id;

        // add some of the group data as well
        $group_data = civicrm_api3('CustomGroup', 'getsingle', array(
            'id' => $this->paid_via_field['custom_group_id'],
            'return' => 'id,table_name'));
        $this->paid_via_field['table_name'] = $group_data['table_name'];
      }
      return $this->paid_via_field;
    }
    return NULL;
  }



  // TODO: upgrader
  // sync_rangeback -> sync_range
  // synce_graceperiod -> sync_graceperiod

    /**
   * get the syncmap property
   * default is the mapping that is defined by the membership_types' financial type id
   *
   * @return array([financial_type_id] => array(membership_type_id))
     */
  public function getSyncMapping() {
    $mapping = $this->getSetting('sync_mapping');
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
  public function getSyncRange() {
    return (int) $this->getSetting('sync_range');
  }

  /**
   * Get the IDs of the 'live' statuses, i.e. the ones that can be assigned payments
   *  Default is 1,2,3 (new, current, grace)
   *
   * @return array
   */
  public function getLiveStatusIDs() {
    $status_ids = $this->getSetting('live_statuses');
    if (!is_array($status_ids) || empty($status_ids)) {
      return array(1,2,3);
    } else {
      return $status_ids;
    }
  }

  /**
   * get the sync range property (number of days)
   * which describes how far back the membership<->payment mapping should be performed
   *
   * default is 32 (days)
   * @return int
   */
  public function getSyncGracePeriod() {
    // TODO: updater: synce_graceperiod
    return (int) $this->getSetting('sync_graceperiod');
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