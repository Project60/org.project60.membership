<?php
/*-------------------------------------------------------+
| Project 60 - Membership Extension                      |
| Copyright (C) 2019 SYSTOPIA                            |
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
 * This class contains the logic for various generation activities
 *
 */
class CRM_Membership_Generator {

  /**
   * Generate Membership fee change activities based on extended logging data
   *
   * @param $from_date string from date
   * @param $to_date   string to date
   * @param $params    array  parameters
   * @return array API3 result
   */
  public static function generateFeeUpdateActivities($from_date, $to_date, $params) {
    Civi::log()->debug("Calling generateFeeUpdateActivities({$from_date}-{$to_date}), params: " . json_encode($params));
    $settings = CRM_Membership_Settings::getSettings();

    // check if it's turned on
    $fields = $settings->getFields(['annual_amount_field']);
    if (empty($fields)) {
      return civicrm_api3_create_error("Annual membership fee field not active.");
    }
    $annual_fee_field = reset($fields);

    //check if there is logging data
    $test = CRM_Core_DAO::executeQuery("SHOW TABLES LIKE 'log_{$annual_fee_field['table_name']}'");
    if (!$test->fetch()) {
      return civicrm_api3_create_error("Logging not enabled");
    }

    // generate query
    $timestamp = microtime(true);
    $event = CRM_Core_DAO::executeQuery("
      SELECT 
       entity_id                            AS membership_id,
       `{$annual_fee_field['column_name']}` AS annual_fee,
       log_date                             AS log_date
      FROM `log_{$annual_fee_field['table_name']}`
      WHERE log_action <> 'Delete'
      ORDER BY entity_id ASC, log_date ASC;");
    Civi::log()->debug(sprintf("Main query took %.2fs", (microtime(true)-$timestamp)));

    // processing loop:
    $records_written     = 0;
    $memberships_written = -1;
    $membership_limit    = empty($params['limit']) ? PHP_INT_MAX : (int) $params['limit'];
    $membership_id       = NULL;
    $last_fee_amount     = NULL;
    $last_timestamp      = NULL;
    $fee_data_points     = [];
    while ($event->fetch()) {
      // ignore zero fee events
      if (empty($event->annual_fee)) continue;

      // first: check if the membership has changed:
      if ($membership_id != $event->membership_id) {
        // new case: first write out the collected data of the last one:
        $new_records_written = self::writeFeeUpdateRecords($membership_id, $fee_data_points, $params);
        $records_written += $new_records_written;
        if ($new_records_written > 0) {
          $memberships_written += 1;
        }

        // then: reset case and move on
        $membership_id   = $event->membership_id;
        $last_fee_amount = $event->annual_fee;
        $fee_data_points = [];

        if ($memberships_written >= $membership_limit) {
          break;
        } else {
          continue;
        }
      }

      // record changes
      $new_fee_amount = $event->annual_fee;
      if ($last_fee_amount != $new_fee_amount) {
        // this is a change event
        $new_timestamp  = strtotime($event->log_date);
        $fee_data_points[] = [$new_timestamp, $new_fee_amount];

        // update stats
        $last_timestamp  = $new_timestamp;
        $last_fee_amount = $new_fee_amount;
      }
    }

    // if we get here, that was the last line:
    $records_written += self::writeFeeUpdateRecords($membership_id, $fee_data_points, $params);

    return civicrm_api3_create_success("Written {$records_written} change records.");
  }


  /**
   * Extract and write out the change events
   *
   * @param $membership_id   integer membership ID
   * @param $fee_data_points array   list of fee data points
   * @param $params          array   configuration
   * @return int number of records written
   */
  protected static function writeFeeUpdateRecords($membership_id, $fee_data_points, $params) {
    $change_counter = 0;
    $membership_id  = (int) $membership_id;
    if (empty($membership_id) || count($fee_data_points) < 2) {
      return 0;
    }

    // init the logic
    Civi::log()->debug("Fee data points [{$membership_id}]: " . json_encode($fee_data_points));
    $fee_logic = CRM_Membership_FeeChangeLogic::getSingleton();
    $contact_id = CRM_Core_DAO::singleValueQuery("SELECT contact_id FROM civicrm_membership WHERE id = {$membership_id};");

    // if there is a crunch limit set (minimum time between changes so they would be
    // considered a real update, and not just an error and a correction), do the crunching
    if (!empty($params['crunch_limit'])) {
      // simply drop data points that are followed closely followed by the next one
      $crunch_limit = strtotime("now + {$params['crunch_limit']}") - strtotime("now");
      $TIMESTAMP = 0;
      $crunched_data_points = [];
      $last_data_point = NULL;
      foreach ($fee_data_points as $next_data_point) {
        if ($last_data_point) {
          if ($next_data_point[$TIMESTAMP] < $last_data_point[$TIMESTAMP] + $crunch_limit) {
            // these are too close together, drop previous one
            array_pop($crunched_data_points);
          }
        }
        $last_data_point = $next_data_point;
        array_push($crunched_data_points, $next_data_point);
      }
      $fee_data_points = $crunched_data_points;
      Civi::log()->debug("Crunched data points [{$membership_id}]: " . json_encode($fee_data_points));
    }

    // go through the changes
    $last_record = NULL;
    foreach ($fee_data_points as $fee_record) {
      if ($last_record === NULL) {
        // this is the first record
        $last_record = $fee_record;
        continue;
      }

      // skip same fees (could be created by crunched short-term changes
      if ($fee_record[1] != $last_record[1]) {
        if (empty($params['dry_run'])) {
          // create a change
          $fee_logic->processChange([
              'membership_ids' => [$membership_id],
              'annual_amount'  => $last_record[1],
              'contact_ids'    => [$contact_id],
          ], [
              'membership_ids' => [$membership_id],
              'annual_amount'  => $fee_record[1],
              'contact_ids'    => [$contact_id],
          ], date('YmdHis', $fee_record[0]));

        } else {
          // just log (dry_run)
          Civi::log()->debug(sprintf("Annual fee change detected: %.2f to %.2f for membership [%d] (contact [%d]) on %s",
              $last_record[1], $fee_record[1], $membership_id, $contact_id, date('Y-m-d', $fee_record[0])));
        }
        $change_counter += 1;
      }
      $last_record = $fee_record;
    }

    // we're done
    return $change_counter;
  }
}