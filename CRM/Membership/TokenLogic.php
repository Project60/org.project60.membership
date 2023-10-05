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
 * This class provides new tokens for memberships
 *
 * @see https://github.com/Project60/org.project60.membership/issues/10
 */
class CRM_Membership_TokenLogic {

  protected static $singleton = NULL;

  protected $_membershipTypes = NULL;
  protected $_customTokenFields = NULL;


  public static function getSingleton() {
    if (self::$singleton === NULL) {
      self::$singleton = new CRM_Membership_TokenLogic();
    }
    return self::$singleton;
  }


  protected function getBaseTokens() {
    return array(
        'start_date'       => E::ts("Start Date"),
        'start_date_raw'   => E::ts("Start Date (raw)"),
        'join_date'        => E::ts("Join Date"),
        'join_date_raw'    => E::ts("Join Date (raw)"),
        'end_date'         => E::ts("End Date"),
        'end_date_raw'     => E::ts("End Date (raw)"),
    );
  }

  protected function getCustomTokens() {
    $all_custom_tokens = array(
        'annual_amount'                    => E::ts("Annual Amount"),
        'annual_amount_raw'                => E::ts("Annual Amount (raw)"),
        'installment_amount'               => E::ts("Installment Amount"),
        'installment_amount_raw'           => E::ts("Installment Amount (raw)"),
        'payment_frequency'                => E::ts("Payment Frequency"),
        'payment_type'                     => E::ts("Payment Type"),
        'membership_cancellation_date'     => E::ts("Cancellation Date"),
        'membership_cancellation_date_raw' => E::ts("Cancellation Date (raw)"),
        'membership_cancellation_reason'   => E::ts("Cancellation Reason"),
    );

    // check which of those are active (i.e. have a field connected to them)
    $active_custom_tokens = array();
    $settings = CRM_Membership_Settings::getSettings();
    foreach ($all_custom_tokens as $custom_token => $custom_token_name) {
      // TODO: deal with 'raw'
      if ($settings->getSetting($custom_token . '_field')) {
        $active_custom_tokens[$custom_token] = $custom_token_name;
      }
    }

    // add user-defined custom tokens
    $custom_token_fields = $this->getCustomTokenFields();
    foreach ($custom_token_fields as $custom_token_field_info) {
      $custom_token_name = "custom_{$custom_token_field_info['column_name']}";
      $active_custom_tokens[$custom_token_name] = $custom_token_field_info['label'];
    }

    return $active_custom_tokens;
  }



  /**
   * Implements (indirectly) the civicrm_tokens hook to
   *  add more membership related tokens
   *
   * @param $tokens
   * @throws CiviCRM_API3_Exception
   */
  public function tokens(&$tokens) {
    $settings = CRM_Membership_Settings::getSettings();
    $membership_types = $this->getMembershipTypes();
    if (empty($membership_types)) return;

    // add basic membership tokens (namespace 'membership')
    $membership_tokens = array();
    $base_tokens = $this->getBaseTokens();
    foreach ($membership_types as $membership_type) {
      foreach ($base_tokens as $base_token => $base_token_name) {
        $membership_tokens["membership.{$base_token}_{$membership_type['id']}"] = "{$membership_type['name']} {$base_token_name}";
      }
    }

    // add custom membership tokens (namespace 'membership')
    $custom_tokens = $this->getCustomTokens();
    foreach ($membership_types as $membership_type) {
      foreach ($custom_tokens as $custom_token => $custom_token_name) {
        $membership_tokens["membership.{$custom_token}_{$membership_type['id']}"] = "{$membership_type['name']} {$custom_token_name}";
      }
    }
    $tokens['membership'] = $membership_tokens;

    // add membership number (namespace 'membership_number')
    $number_tokens = array();
    foreach ($membership_types as $membership_type) {
      $number_tokens["membership_number.membership_number_{$membership_type['id']}"] = E::ts("%1 Number", array(1 => $membership_type['name']));
    }
    $tokens['membership_number'] = $number_tokens;
  }


  /**
   * Implements (indirectly) the civicrm_tokensValues hook to
   *  add more membership related tokens
   */
  function tokenValues(&$values, $cids, $job = null, $tokens = array(), $context = null) {
    // fill membership base tokens (namespace 'membership')
    if (!empty($tokens['membership'])) {
      $membership_types_used = array();

      // find out which tokens are used
      $base_tokens = $this->getBaseTokens();
      $base_tokens_used = $this->getTokensUsed($tokens['membership'], array_keys($base_tokens), $membership_types_used);
      $custom_tokens = $this->getCustomTokens();
      $custom_tokens_used = $this->getTokensUsed($tokens['membership'], array_keys($custom_tokens), $membership_types_used);

      foreach ($membership_types_used as $membership_type_id) {
        $contact_values = $this->getMembershipTokenValues($cids, $base_tokens_used, $custom_tokens_used, $membership_type_id);
        foreach ($contact_values as $contact_id => $token_values) {
          foreach ($token_values as $token_name => $token_value) {
            $values[$contact_id]["membership.{$token_name}"] = $token_value;
          }
        }
      }
    }


    // fill membership number (namespace 'membership_number')
    if (!empty($tokens['membership_number'])) {
      // collect membership types
      foreach ($tokens['membership_number'] as $token) {
        if (substr($token, 0, 18) == 'membership_number_') {
          $membership_type_id = substr($token, 18);
          $tvalues = CRM_Membership_NumberLogic::getCurrentMembershipNumbers($cids, array($membership_type_id));
          foreach ($tokens['membership_number'] as $key => $value) {
            $token = $job ? $key : $value; // there seems to be a difference between individual and mass mailings
            foreach ($tvalues as $cid => $number) {
              $values[$cid]["membership_number.{$token}"] = $number;
            }
          }
        }
      }
    }
  }



  /**
   * @return null
   * @throws CiviCRM_API3_Exception
   */
  protected function getMembershipTypes() {
    if ($this->_membershipTypes == NULL) {
      $membership_types = civicrm_api3('MembershipType', 'get', array(
          'is_active'  => 1,
          'sequential' => 0,
          'return'     => 'id,name'));
      $this->_membershipTypes = $membership_types['values'];
    }
    return $this->_membershipTypes;
  }


  /**
   * Determine which of our tokens are used in token list
   *
   * @param $token_list array of tokens requested by the system
   * @param $our_tokens array of tokens we could provide
   * @param $membership_types_used array of membership_types involved, will be extended
   *
   * @return array of tokens being used
   */
  protected function getTokensUsed($token_list, $our_tokens, &$membership_types_used) {
    $used_tokens = array();
    foreach ($token_list as $requested_token) {
      if (preg_match("/^(?P<token_name>\w+)_(?P<membership_type>[0-9]+)$/", $requested_token,$match)) {
        if (in_array($match['token_name'], $our_tokens)) {
          $used_tokens[] = $match['token_name'];
          $membership_types_used[$match['membership_type']] = $match['membership_type'];
        }
      }
    }
    return $used_tokens;
  }

  /**
   * Get the values for all the required
   *
   * @param $cids array contact IDs
   * @param $base_tokens_used array basic tokens used
   * @param $custom_tokens_used array custom tokens used
   * @param $membership_type_id int membership type ID
   *
   * @return array cid => [token => value]
   */
  protected function getMembershipTokenValues($cids, $base_tokens_used, $custom_tokens_used, $membership_type_id) {
    if (empty($cids)) return array();
    $cid_list = implode(',', $cids);

    // first: create a temp table to identify the membership ID per contact
    $temp_contact2membership = CRM_Membership_Legacycode_Core_DAO::createTempTableName('p60m_token');
    CRM_Core_DAO::executeQuery("DROP TEMPORARY TABLE IF EXISTS {$temp_contact2membership}");
    $contact2membership_sql = "
      SELECT
        contact_id AS contact_id,
        MAX(id)    AS membership_id
      FROM civicrm_membership
      WHERE contact_id IN ({$cid_list})
        AND membership_type_id = {$membership_type_id}
      GROUP BY contact_id";
    CRM_Core_DAO::executeQuery("CREATE TEMPORARY TABLE {$temp_contact2membership} AS {$contact2membership_sql}");

    // now: compile data query
    $selects[] = "c2m.contact_id AS contact_id";
    $joins     = array();

    if ($base_tokens_used) {
      $joins[] = "LEFT JOIN civicrm_membership membership ON membership.id = membership_id";
      foreach ($base_tokens_used as $base_token_used) {
        if (preg_match("/^(?P<token>\w+)_raw$/", $base_token_used, $match)) {
          $selects[] = "membership.{$match['token']} AS {$base_token_used}";
        } else {
          $selects[] = "membership.{$base_token_used} AS {$base_token_used}";
        }
      }
    }

    // add internal tokens
    if ($custom_tokens_used) {
      $settings = CRM_Membership_Settings::getSettings();
      foreach ($custom_tokens_used as $custom_token_used) {
        $field_name = $custom_token_used . '_field';
        $field_id = $settings->getSetting($field_name);
        if ($field_id) {
          $field_spec = $settings->getFieldInfo($field_id);
          if ($field_spec) {
            $joins[] = "LEFT JOIN {$field_spec['table_name']} AS {$custom_token_used} ON {$custom_token_used}.entity_id = c2m.membership_id";
            if (!empty($field_spec['option_group_id'])) {
              // join option group
              $joins[] = "LEFT JOIN civicrm_option_value {$custom_token_used}_ov ON {$custom_token_used}_ov.value = {$custom_token_used}.{$field_spec['column_name']} AND {$custom_token_used}_ov.option_group_id = {$field_spec['option_group_id']}";
              $selects[] = "{$custom_token_used}_ov.label AS {$custom_token_used}";
            } else {
              $selects[] = "{$custom_token_used}.{$field_spec['column_name']} AS {$custom_token_used}";
            }
          }
        }
      }
    }

    // add custom tokens
    $custom_token_fields = $this->getCustomTokenFields();
    foreach ($custom_token_fields as $custom_token_field_info) {
      $custom_token_name = "custom_{$custom_token_field_info['column_name']}";
      $joins[] = "LEFT JOIN {$custom_token_field_info['table_name']} AS {$custom_token_name} ON {$custom_token_name}.entity_id = c2m.membership_id";
      $selects[] = "{$custom_token_name}.{$custom_token_field_info['column_name']} AS {$custom_token_name}";
    }

    $select_list = implode(",\n      ", $selects);
    $join_list   = implode(" \n    ", $joins);
    $value_query_sql = "
    SELECT
      {$select_list}
    FROM {$temp_contact2membership} c2m
    {$join_list}
    WHERE c2m.membership_id IS NOT NULL;";

    // execute the query
    $values = array();
    // CRM_Core_Error::debug_log_message("SQL: {$value_query_sql}");
    $value_query = CRM_Core_DAO::executeQuery($value_query_sql);
    while ($value_query->fetch()) {
      $contact_values = array();
      foreach ($base_tokens_used as $base_token) {
        $contact_values["{$base_token}_{$membership_type_id}"] = $this->formatTokenValue($base_token, $value_query->$base_token);
      }
      foreach ($custom_tokens_used as $custom_token) {
        $contact_values["{$custom_token}_{$membership_type_id}"] = $this->formatTokenValue($custom_token, $value_query->$custom_token);
      }
      $values[$value_query->contact_id] = $contact_values;
    }

    CRM_Core_DAO::executeQuery("DROP TEMPORARY TABLE IF EXISTS {$temp_contact2membership}");

    return $values;
  }

  /**
   * Apply formatting to tokens
   *
   * @param $token_name
   * @param $token_value
   */
  protected function formatTokenValue($token_name, $token_value) {
    if (preg_match('/_date$/', $token_name)) {
      // this need to be date-formatted
      return CRM_Utils_Date::customFormat($token_value, CRM_Core_Config::singleton()->dateformatFull);
    } elseif (preg_match('/_amount$/', $token_name)) {
      // this need to be money-formatted
      return CRM_Utils_Money::format($token_value);
    } else {
      return $token_value;
    }
  }

  /**
   * Get a list of the fields selected to become custom tokens
   *
   * @return array list of CustomField metadata
   */
  public function getCustomTokenFields() {
    if ($this->_customTokenFields === NULL) {
      $this->_customTokenFields = [];

      // first: get from the settings
      $custom_field_ids = [];
      $settings = CRM_Membership_Settings::getSettings();
      for ($i = 1; $i <= CRM_Admin_Form_Setting_MembershipExtension::CUSTOM_MEMBERSHIP_TOKEN_COUNT; $i++) {
        $potential_field_id = (int) $settings->getSetting("custom_token_{$i}");
        if ($potential_field_id) {
          $custom_field_ids[] = $potential_field_id;
        }
      }

      // now load the fields
      $settings->cacheFields($custom_field_ids);
      foreach ($custom_field_ids as $custom_field_id) {
        $this->_customTokenFields[] = $settings->getFieldInfo($custom_field_id);
      }
    }
    return $this->_customTokenFields;
  }

}