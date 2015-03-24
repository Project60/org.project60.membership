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
   * @return array([financial_type_id] => [membership_type_id])
   */
  public static function getSyncMapping() {
    $mapping_string = CRM_Membership_Settings::getSyncMappingString();    
    return CRM_Membership_Settings::_string2syncmap($mapping_string);
  }

  /**
   * get the syncmap property in string form
   * default is the mapping that is defined by the membership_types' financial type id
   *
   * @return string with comma separated <financial_type_id>:<membership_type_id> tuples, 
   *            e.g. 10:4,2:2
   */
  public static function getSyncMappingString() {
    $mapping = CRM_Core_BAO_Setting::getItem('Membership Payments', 'sync_mapping');
    if (empty($mapping)) {
      $default = CRM_Membership_Settings::_getDefaultSyncmap();
      return CRM_Membership_Settings::_symcmap2string($default);
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
   * set the syncmap property
   * @param $syncmap array([financial_type_id] => [membership_type_id])
   */
  public static function setSyncMapping($syncmap) {
    $newString = CRM_Membership_Settings::_symcmap2string($syncmap);
    // compare with default
    $defaultMapping = CRM_Membership_Settings::_getDefaultSyncmap();
    $defaultString = CRM_Membership_Settings::_symcmap2string($defaultMapping);

    if ($newString == $defaultString) {
      // this is the default, just set it to empty
      CRM_Core_BAO_Setting::setItem('', 'Membership Payments', 'sync_mapping');  
    } else {
      CRM_Core_BAO_Setting::setItem($newString, 'Membership Payments', 'sync_mapping');
    }
  }

  /**
   * get the sync range property (number of days)
   * which describes how far back the membership<->payment mapping should be performed
   *
   * default is 400 (days)
   * @return int
   */
  public static function setSyncRange($range) {
    CRM_Core_BAO_Setting::setItem($range, 'Membership Payments', 'sync_rangeback');
  }


  /**
   * converts the given [financial_type_id] => [membership_type_id] tuples
   * to a comma separated string, e.g. "10:4,2:2"
   *
   * @param $syncmap  array([financial_type_id] => [membership_type_id])
   *
   * @return comma separated string, e.g. "10:4,2:2"
   */
  public static function _symcmap2string($syncmap) {
    // sort first, to make it comparable
    ksort($syncmap);

    // then generate string
    $tuples = array();
    foreach ($syncmap as $key => $value) {
      $tuples[] = "$key:$value";
    }
    return implode(',', $tuples);
  }

  /**
   * converts the given comma separated string, e.g. "10:4,2:2"
   * to an array([financial_type_id] => [membership_type_id])
   *
   * @param mapping_string comma separated string, e.g. "10:4,2:2"
   *
   * @return $syncmap  array([financial_type_id] => [membership_type_id])
   */
  public static function _string2syncmap($mapping_string) {
    $mapping = array();
    $items = split(',', $mapping_string);
    foreach ($items as $item) {
      $keyValue = split(':', $item);
      if (count($keyValue) != 2) {
        error_log("org.project60.membership: Invalid value for setting 'sync_mapping'!");
      } else {
        if (isset($mapping[$keyValue[0]])) {
          error_log("org.project60.membership: Duplicate value for setting 'sync_mapping'!");
        } else {
          $mapping[$keyValue[0]] = $keyValue[1];
        }
      }
    }

    return $mapping;
  }

  /**
   * extracts the default syncmapString from the membership types
   */
  protected function _getDefaultSyncmap() {
    $mapping = array();
    $membership_types = civicrm_api3('MembershipType', 'get', 
       array('is_active' => 1, 'option.limit' => 9999));
    if (empty($membership_types['is_error'])) {
      foreach ($membership_types['values'] as $membership_type) {
        $key = $membership_type['id'];
        $value = $membership_type['financial_type_id'];
        if (!empty($key) && !empty($value)) {
          if (isset($mapping[$key])) {
            error_log("org.project60.membership: duplicate use of financial type [$key].");
          } else {
            $mapping[$key] = $value;
          }
        }
      }
    } else {
      error_log("org.project60.membership: Cannot read membership types - ".$membership_types['error_message']);
    }
    error_log(print_r($mapping,1));
    return $mapping;
  }
}