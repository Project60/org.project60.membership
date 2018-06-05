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
  protected $paid_by_field = NULL;
  protected $field_cache = NULL;

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
   * Get all settings
   * @return array
   */
  public function getAllSettings() {
    return $this->settings_bucket;
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
  public function getPaidByFieldID() {
    if (isset($this->settings_bucket['paid_by_field'])) {
      return (int) $this->settings_bucket['paid_by_field'];
    } else {
      return NULL;
    }
  }

  /**
   * Get the field data of the paid_via field
   * or NULL if none set;
   */
  public function getPaidByField() {
    if ($this->paid_by_field == NULL) {
      $field_id = $this->getPaidByFieldID();
      $this->paid_by_field = $this->getFieldInfo($field_id);
    }
    return $this->paid_by_field;
  }

  /**
   * Get the field ID of the selected paid_via field
   * @return int
   */
  public function getPaidViaFieldID() {
    if (isset($this->settings_bucket['paid_via_field'])) {
      return (int) $this->settings_bucket['paid_via_field'];
    } else {
      return NULL;
    }
  }

  /**
   * Get the field data of the paid_via field
   * or NULL if none set;
   */
  public function getPaidViaField() {
    if ($this->paid_via_field == NULL) {
      $field_id = $this->getPaidViaFieldID();
      $this->paid_via_field = $this->getFieldInfo($field_id);
    }
    return $this->paid_via_field;
  }

  /**
   * Put the requested field IDs in the cache
   *
   * @param $ids
   */
  public function cacheFields($field_ids) {
    $fields_to_load = array();
    foreach ($field_ids as $field_id) {
      if (!isset($this->field_cache[$field_id])) {
        $fields_to_load[] = $field_id;
      }
    }

    if (!empty($fields_to_load)) {
      $field_infos = civicrm_api3('CustomField', 'get', array(
          'id'         => array('IN' => $fields_to_load),
          'sequential' => 0,
          'return'     => 'column_name,id,label,custom_group_id'));

      $groups_to_load = array();
      foreach ($field_infos['values'] as $field_info) {
        $groups_to_load[] = $field_info['custom_group_id'];
      }

      $group_data = civicrm_api3('CustomGroup', 'get', array(
          'id'         => array('IN' => $groups_to_load),
          'sequential' => 0,
          'return'     => 'id,table_name'));

      // finally: fill the cache
      foreach ($fields_to_load as $field_id) {
        if (isset($field_infos['values'][$field_id])) {
          $field = $field_infos['values'][$field_id];
          $field['key'] = 'custom_' . $field_id;
          $field['table_name'] = $group_data['values'][$field['custom_group_id']]['table_name'];

          $this->field_cache[$field_id] = $field;

        } else {
          $this->field_cache[$field_id] = 'MISSING';
        }
      }
    }
  }

  /**
   * Get an info block for the given field ID
   */
  public function getFieldInfo($field_id)
  {
    if ($field_id) {
      $this->cacheFields(array($field_id));
      if (isset($this->field_cache[$field_id])) {
        $field_info = $this->field_cache[$field_id];
        if (is_array($field_info)) {
          return $field_info;
        }
      }
    }
    return NULL;
  }

  /**
   * Get the derived fields from the settings
   *
   * @return array with the settings names mapped to the custom field objects
   */
  public function getDerivedFields() {
    $settings = CRM_Membership_Settings::getSettings();
    $field_keys = array('paid_via_field', 'annual_amount_field', 'installment_amount_field', 'diff_amount_field', 'payment_frequency_field', 'payment_type_field');
    $active_field_ids = array();
    foreach ($field_keys as $field_key) {
      $field_id = $settings->getSetting($field_key);
      if ($field_id) {
        $active_field_ids[] = $field_id;
      }
    }

    $settings->cacheFields($active_field_ids);

    $active_fields = array();
    foreach ($field_keys as $field_key) {
      $field_id = $settings->getSetting($field_key);
      if ($field_id) {
        $field = $settings->getFieldInfo($field_id);
        if ($field) {
          $active_fields[$field_key] = $field;
        }
      }
    }

    return $active_fields;
  }


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
    return (int) $this->getSetting('grace_period');
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