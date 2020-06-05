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
 * This class contains the logic to track changes in the annual fee
 *  as activities
 *
 * @see https://github.com/Project60/org.project60.membership/issues/28
 */
class CRM_Membership_FeeChangeLogic {

  /** stores the pre/post hook records */
  protected $monitoring_stack = array();

  /** stores a membership id => before_record list */
  protected $pending_records  = array();

  /** stores the singleton instance */
  protected static $singleton = NULL;

  /** caches the activity type id */
  protected $_activity_type_id   = NULL;

  public static function getSingleton()
  {
    if (self::$singleton === NULL) {
      self::$singleton = new CRM_Membership_FeeChangeLogic();
    }
    return self::$singleton;
  }

  /**
   * Hook call to record the before state of the membership fee,
   *  from either membership or recurring contribution ID
   *
   * @param $membership_id               int membership ID (optional)
   * @param null $contribution_recur_id  int recurring contribution ID (optional)
   */
  public function membershipFeeUpdatePRE($membership_id, $contribution_recur_id = NULL) {
    $before_record = $this->getMembershipFeeRecord($membership_id, $contribution_recur_id);
    array_push($this->monitoring_stack, $before_record);
  }


  /**
   * Hook call to record the after state of the membership fee,
   *  from either membership or recurring contribution ID
   *
   * @param $membership_id               int membership ID (optional)
   * @param null $contribution_recur_id  int recurring contribution ID (optional)
   */
  public function membershipFeeUpdatePOST($membership_id, $contribution_recur_id = NULL) {
    $before_record = array_pop($this->monitoring_stack);
    if ($before_record && $membership_id) {
      // this is a membership POST -> the changes will happen in the custom hook,
      //  so we move the party to the pending_records
      foreach ($before_record['membership_ids'] as $membership_id) {
        $this->pending_records[$membership_id] = $before_record;
      }

    } elseif ($contribution_recur_id) {
      // in this case, the relevant changes have already happened
      $after_record  = $this->getMembershipFeeRecord($membership_id);
      $this->processChange($before_record, $after_record);
    }
  }


  /**
   * Hook triggered by a change to a custom field. Let's see if it's
   *
   * If there is a change detected, an activity will be created
   *
   * @param $custom_group_id int  ID of the custom group
   * @param $entity_id       int  ID of the related entity (depends on group)
   *
   * @return null
   */
  public function membershipFeeUpdateWrapup($custom_group_id, $entity_id, $params) {
    $settings = CRM_Membership_Settings::getSettings();

    // make sure we should spring into action:
    $record_fee_updates = $settings->getSetting('record_fee_updates');
    if (empty($record_fee_updates)) {
      // recording fee changes disabled
      return NULL;
    }

    // for now, only annual_amount_field // $fields = $settings->getFields(['paid_via_field', 'annual_amount_field']);
    $fields = $settings->getFields(['annual_amount_field']);
    if (empty($fields)) {
      // relevant fields not enabled
      return NULL;
    }

    $group_relevant = FALSE;
    foreach ($fields as $field) {
      $group_relevant |= ($field['custom_group_id'] == $custom_group_id);
    }
    if (!$group_relevant) {
      // this group is not relevant for us
      return NULL;
    }

    // OK, this is a relevant group -> let's go!
    $membership_id = (int) $entity_id;
    $after_record  = $this->getMembershipFeeRecord($membership_id);
    $before_record = CRM_Utils_Array::value($membership_id, $this->pending_records);
    if ($before_record) {
      unset($this->pending_records[$membership_id]);
    }
    $this->processChange($before_record, $after_record);
  }

  /**
   * Process the actual before/after records, and create
   *  an activity if required
   *
   * @param $before_record array before state
   * @param $after_record  array after state
   */
  protected function processChange($before_record, $after_record) {
    // now we have two records - let's see if there's a difference
    if ($before_record && $after_record) { // if there's not two records it's not an update
      $membership_id_diff = array_diff($before_record['membership_ids'], $after_record['membership_ids']);
      if (!empty($membership_id_diff)) {
        // something went wrong here
        CRM_Core_Error::debug_log_message("p60 fee change: differing membership IDs received for change {$before_record['input']}-{$after_record['input']}");

      } else {
        $amount_increase = $after_record['annual_amount'] - $before_record['annual_amount'];
        if ($amount_increase) {
          // there has been a change - create activity
          try {
            $activity_data = [
                'activity_type_id'   => $this->getActivityTypeID(),
                'target_id'          => array_intersect($before_record['contact_ids'], $after_record['contact_ids']),
                'subject'            => ($amount_increase > 0) ? E::ts("Membership Fee Increase") : E::ts("Membership Fee Reduction"),
                'activity_date_time' => date('YmdHis'),
                'source_contact_id'  => CRM_Core_Session::getLoggedInContactID(),
                'source_record_id'   => reset($after_record['membership_ids']),

                // custom data
                'p60membership_fee_update.annual_amount_before'   => number_format($before_record['annual_amount'], 2, '.', ''),
                'p60membership_fee_update.annual_amount_after'    => number_format($after_record['annual_amount'], 2, '.', ''),
                'p60membership_fee_update.annual_amount_increase' => number_format($amount_increase, 2, '.', ''),
            ];

            // normalise
            CRM_Membership_CustomData::resolveCustomFields($activity_data);

            // create activity
            civicrm_api3('Activity', 'create', $activity_data);
          } catch (Exception $ex) {
            CRM_Core_Error::debug_log_message("ERROR: P60mem - couldn't create fee increase activity: " . $ex->getMessage());
          }
        }
      }
    }
  }

  /**
   * Generate record of the current annual fee of the membership
   *  from either the membership or the recurring contribution
   *
   * @param $membership_id         int membership id
   * @param $contribution_recur_id int the recurring contribution
   *
   * @return array|null the resulting record, or NULL if this doesn't apply
   */
  protected function getMembershipFeeRecord($membership_id, $contribution_recur_id = NULL) {
    $settings = CRM_Membership_Settings::getSettings();
    $membership_id = (int) $membership_id;
    $contribution_recur_id = (int) $contribution_recur_id;

    if (empty($membership_id) && empty($contribution_recur_id)) {
      // no data submitted
      return NULL;
    }

    $record_fee_updates = $settings->getSetting('record_fee_updates');
    if (empty($record_fee_updates)) {
      // recording fee changes disabled
      return NULL;
    }

    if (empty($membership_id)) {
      $logic = CRM_Membership_PaidByLogic::getSingleton();
      $membership_ids = $logic->getMembershipIDs($contribution_recur_id);
    } else {
      $membership_ids = [$membership_id];
    }

    if (empty($membership_ids)) {
      // no memberships given or found
      return NULL;
    }

    // for now, only annual_amount_field // $fields = $settings->getFields(['paid_via_field', 'annual_amount_field']);
    $fields = $settings->getFields(['annual_amount_field']);
    if (empty($fields)) {
      // relevant fields not enabled
      return NULL;
    }

    // create query based on membership
    $joins = $selects = [];

    if (isset($fields['paid_via_field'])) {
      $joins[]   = "LEFT JOIN {$fields['paid_via_field']['table_name']} paid_via ON paid_via.entity_id = membership.id";
      $joins[]   = "LEFT JOIN civicrm_contribution_recur recur ON recur.id = paid_via.{$fields['paid_via_field']['column_name']}";
      $selects[] = "recur.amount AS amount";
      $selects[] = "recur.frequency_unit AS frequency_unit";
      $selects[] = "recur.frequency_interval AS frequency_interval";
    }

    if (isset($fields['annual_amount_field'])) {
      $joins[]   = "LEFT JOIN {$fields['annual_amount_field']['table_name']} annual_amount ON annual_amount.entity_id = membership.id";
      $selects[] = "annual_amount.{$fields['annual_amount_field']['column_name']} AS annual_amount";
    }

    $selects[]   = 'membership.id AS membership_id';
    $selects[]   = 'membership.contact_id AS contact_id';
    $all_joins   = implode(' ', $joins);
    $all_selects = implode(', ', $selects);
    $all_ids     = implode(',', $membership_ids);
    $query_sql = "
      SELECT {$all_selects} 
      FROM civicrm_membership membership
      {$all_joins}
      WHERE membership.id IN ({$all_ids})";
    $query = CRM_Core_DAO::executeQuery($query_sql);

    // compile the results
    $annual_amount  = 0.0;
    $membership_ids = [];
    $contact_ids    = [];
    while ($query->fetch()) {
      $data = $query->toArray();

      // extract the amounts
      $paid_by_field_amount = 0.0;
      $annual_field_amount  = 0.0;

      if (!empty($data['amount']) && !empty($data['frequency_unit'])) {
        $paid_by_field_amount = CRM_Membership_PaidByLogic::calculateAnnualTotal($data);
      }

      if (!empty($data['annual_amount'])) {
        $annual_field_amount  = (float) $data['annual_amount'];
      }

      // gather data
      $annual_amount    = max($annual_amount, $paid_by_field_amount, $annual_field_amount);
      $membership_ids[] = (int) $data['membership_id'];
      $contact_ids[]    = (int) $data['contact_id'];
    }

    if (!empty($membership_ids)) {
      return [
          'annual_amount'  => $annual_amount,
          'membership_ids' => $membership_ids,
          'contact_ids'    => $contact_ids,
          'input'          => "{$membership_id}/{$contribution_recur_id}"
      ];
    } else {
      return NULL;
    }
  }

  /**
   * Get the activity type used to track the changes
   * If the activity type doesn't exist, create it.
   *
   * @return int activity type ID
   * @throws Exception if there are more than one activity type with the given name
   */
  public function getActivityTypeID() {
    if ($this->_activity_type_id !== NULL) {
      return $this->_activity_type_id;
    }

    // try to find it
    $activity_types = civicrm_api3('OptionValue', 'get', [
        'option_group_id' => 'activity_type',
        'name'            => 'p60membership_fee_update'
    ]);

    // there's a problem
    if ($activity_types['count'] > 1) {
      throw new Exception("More than one activity type with name 'p60membership_fee_update' found! Please fix!");
    }

    // found!
    if ($activity_types['count'] == 1) {
      // it's exactly one!
      $activity_type = reset($activity_types['values']);
      $this->_activity_type_id = (int) $activity_type['value'];
      return $this->_activity_type_id;
    }

    // no? we need to create it...
    $activity_type = civicrm_api3('OptionValue', 'create', [
        'option_group_id' => 'activity_type',
        'name'            => 'p60membership_fee_update',
        'label'           => E::ts('Membership Fee Update')
    ]);

    // success?
    if (!empty($activity_type['id'])) {
      $this->_activity_type_id = civicrm_api3('OptionValue', 'getvalue', [
          'id'     => $activity_type['id'],
          'return' => 'value']);

      // make sure the fields are there
      $custom_data = new CRM_Membership_CustomData('org.project60.membership');
      $custom_data->syncCustomGroup(__DIR__ . '/../../resources/fee_update_custom_group.json');

      return $this->_activity_type_id;
    } else {
      throw new Exception("Couldn't create activity type");
    }
  }
}