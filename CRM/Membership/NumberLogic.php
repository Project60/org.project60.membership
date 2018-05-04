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
class CRM_Membership_NumberLogic {


    /**
     * Get the current membership number picked from the given membership IDs
     *
     * @param  $contact_ids array contact IDs
     * @param $membership_type_ids array list of potential membership type IDs
     * @return array contact id => membership number
     * @throws API_Exception
     */
    public static function getCurrentMembershipNumbers($contact_ids, $membership_type_ids = NULL) {
        $contact_id_2_membership_number = array();

        // load field and group
        $settings = CRM_Membership_Settings::getSettings();
        $number_field_id = $settings->getSetting('membership_number_field');
        if (empty($number_field_id)) {
          return $contact_id_2_membership_number;
        }
        $field = civicrm_api3('CustomField', 'getsingle', array(
            'id'     => $number_field_id,
            'return' => 'column_name,custom_group_id'));
        $group = civicrm_api3('CustomGroup', 'getsingle', array(
            'id'     => $field['custom_group_id'],
            'return' => 'table_name'));

        // build SQL query
        $contact_id_list = implode(',', $contact_ids);
        $active_status_list = implode(',', $settings->getLiveStatusIDs());

        $MEMBERSHIP_TYPE_CONDITION = '';
        if (!empty($membership_type_ids)) {
          $membership_type_id_list = implode(',', $membership_type_ids);
          $MEMBERSHIP_TYPE_CONDITION = "AND membership.membership_type_id IN ({$membership_type_id_list})";
        }

        $query = "
        SELECT 
          membership.contact_id                                 AS contact_id,
          GROUP_CONCAT(membership.status_id 
            ORDER BY membership.status_id, membership.id DESC)  AS membership_status,
          GROUP_CONCAT(number_table.{$field['column_name']}
            ORDER BY membership.status_id, membership.id DESC)  AS membership_numbers
        FROM civicrm_membership membership 
        LEFT JOIN {$group['table_name']} number_table ON number_table.entity_id = membership.id
        WHERE membership.contact_id IN ({$contact_id_list})
          AND membership.status_id IN ({$active_status_list})
          {$MEMBERSHIP_TYPE_CONDITION}
        GROUP BY membership.contact_id;";
//        error_log($query);
        $data = CRM_Core_DAO::executeQuery($query);
        while ($data->fetch()) {
          // TODO: evaluate the status?
          //$status = explode(',', $data->membership_status);
          $numbers = explode(',', $data->membership_numbers);
          $contact_id_2_membership_number[$data->contact_id] = $numbers[0];
        }
        return $contact_id_2_membership_number;
    }

    /**
     * Will generate a new membership number when called from the preHook
     * Conditions:
     *  - membership field integration enabled
     *  - membership number generator is set
     *  - no custom number is submitted
     *
     * @param $params the parameters as picked up from the preHook
     */
    public static function generateNewNumber(&$params) {
        $settings = CRM_Membership_Settings::getSettings();
        $field_id = $settings->getSetting('membership_number_field');
        $generator = $settings->getSetting('membership_number_generator');
        if ($field_id && $generator) {
            // the configuration sais we should generate a number
            $value = self::getFieldValue($params, $field_id);
            if (empty($value)) {
                // generate!
                $value = self::generateNumber($generator, $params);
                self::setFieldValue($params, $field_id, $value);
            }
        }
    }

    /**
     * Get the current field value from CiviCRM's pre-hook structure
     *
     * @param $params pre-hook data
     * @param $field_id custom field ID
     * @return mixed the current value
     */
    protected static function getFieldValue($params, $field_id) {
        if (!empty($params['custom'][$field_id][-1])) {
            $field_data = $params['custom'][$field_id][-1];
            return $field_data['value'];
        }
    }

    /**
     * Set the current field value in CiviCRM's pre-hook structure
     *
     * @param $params pre-hook data
     * @param $field_id custom field ID
     * @param $value the new value
     */
    protected static function setFieldValue(&$params, $field_id, $value) {
        if (!empty($params['custom'][$field_id][-1])) {
            $params['custom'][$field_id][-1]['value'] = $value;
        }
    }

    /**
     * Generate a new number based on the generator string
     *
     * @param $generator string generator template
     * @param $params
     * @return string
     */
    protected static function generateNumber($generator, $params) {
        $number = $generator;

        // replace {mid+x} patterns
        if (preg_match('#\{mid(?P<offset>[+-][0-9]+)?\}#', $number, $matches)) {
            // get the next membership ID
            // FIXME: this is not very reliable
            $mid = CRM_Core_DAO::singleValueQuery("SELECT MAX(id) FROM civicrm_membership;") + 1;
            if (!empty($matches['offset'])) {
                if (substr($matches['offset'], 0, 1) == '-') {
                    $mid -= substr($matches['offset'], 1);
                } else {
                    $mid += substr($matches['offset'], 1);
                }
            }
            $number = preg_replace('#\{mid(?P<offset>[+-][0-9]+)?\}#', $mid, $number);
        }


        // replace {cid+x} patterns
        if (preg_match('#\{cid(?P<offset>[+-][0-9]+)?\}#', $number, $matches)) {
            // get the next membership ID
            $cid = $params['contact_id'];
            if (!empty($matches['offset'])) {
                if (substr($matches['offset'], 0, 1) == '-') {
                    $cid -= substr($matches['offset'], 1);
                } else {
                    $cid += substr($matches['offset'], 1);
                }
            }
            $number = preg_replace('#\{cid(?P<offset>[+-][0-9]+)?\}#', $mid, $number);
        }

        // TODO: implement {seq:x} pattern

        return $number;
    }
}