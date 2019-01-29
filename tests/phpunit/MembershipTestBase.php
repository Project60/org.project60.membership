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
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Test MembershipFeeLogic class
 *
 * @group headless
 */
class MembershipTestBase extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    parent::setUp();
    $this->makeSureThereIsALoggedInContact();
  }

  public function tearDown() {
    parent::tearDown();
  }

  /**
   * Make sure there is a logged in user,
   *  which is really important for some API stuff,
   *  and which is often not the case when running test from the command line
   */
  public function makeSureThereIsALoggedInContact() {
    // make sure there is a logged in user
    $user = CRM_Core_Session::getLoggedInContactID();
    if (empty($user)) {
      $session = CRM_Core_Session::singleton();
      // get random contact
      $contact = civicrm_api3('Contact', 'get', ['option.limit' => 1]);
      $session->set('userID', $contact['id']);
    }

    $user = CRM_Core_Session::getLoggedInContactID();
    $this->assertNotEmpty($user, "Couldn't set logged in contact ID");
  }

  /**
   * Create a new membership
   *
   * @param $params various attributes
   */
  public function createMembership($params) {
    if (empty($params['contact_id'])) {
      $params['contact_id'] = $this->createRandomContact();
    }

    if (empty($params['membership_type_id'])) {
      $params['membership_type_id'] = $this->getRandomMembershipType();
    }

    if (empty($params['start_date'])) {
      $params['start_date'] = date('Y-m-d');
    }

    if (!isset($params['annual_amount'])) {
      $params['annual_amount'] = '60.00';
    }
    $annual_amount_field = $this->getAnnualAmountField();
    $params[$annual_amount_field['key']] = $params['annual_amount'];
    unset($params['annual_amount']);

    $membership = civicrm_api3('Membership', 'create', $params);
    return civicrm_api3('Membership', 'getsingle', ['id' => $membership['id']]);
  }

  /**
   * Create a random contact and return the ID
   */
  public function createRandomContact() {
    $this->assertNotEmpty(CRM_Core_Session::getLoggedInContactID(), "No logged in user");
    $contact = civicrm_api3('Contact','create', [
        'contact_type'       => 'Individual',
        'first_name'         => substr(sha1(microtime()), 0, 16),
        'last_name'          => substr(sha1(microtime()), 0, 16),
        'preferred_language' => 'en_US',
    ]);
    $this->assertNotEmpty($contact['id'], "Couldn't create contact");
    return $contact['id'];
  }

  /**
   * Return the field info of the annual amount field
   * If no such field exists, create and set one.
   */
  public function getAnnualAmountField() {
    $settings = CRM_Membership_Settings::getSettings();

    $annual_amount_field_id = $settings->getSetting('annual_amount_field');
    if (empty($annual_amount_field_id)) {
      $annual_amount_field_search = civicrm_api3('CustomField', 'get', ['name' => 'test_annual_amount_field']);
      if (empty($annual_amount_field_search['id'])) {
        // field doesn't exist
        $annual_amount_field_creation = civicrm_api3('CustomField','create', [
            'custom_group_id' => $this->getMembershipCustomGroupID(),
            'name'            => 'test_annual_amount_field',
            'label'           => 'Annual Membership Fee',
            'data_type'       => 'Money',
            'html_type'       => 'Text',
            'is_active'       => 1,
        ]);
        $this->assertNotEmpty($annual_amount_field_creation['id'], "Couldn't create membership annual amount field");
        $annual_amount_field_id = $annual_amount_field_creation['id'];
      } else {
        $annual_amount_field_id = $annual_amount_field_search['id'];
      }
    }

    $settings->setSetting('annual_amount_field', $annual_amount_field_id);
    return $settings->getFieldInfo($annual_amount_field_id);
  }

  /**
   * Get the ID of the membership custom test data group
   */
  public function getMembershipCustomGroupID() {
    $membership_custom_group_search = civicrm_api3('CustomGroup', 'get', ['name' => 'test_membership_group']);
    if (empty($membership_custom_group_search['id'])) {
      // field doesn't exist
      $membership_custom_group_creation = civicrm_api3('CustomGroup','create', [
          'name'      => 'test_membership_group',
          'title'     => 'Membership Info',
          'extends'   => 'Membership',
          'style'     => 'Inline',
          'is_active' => 1,
      ]);
      $this->assertNotEmpty($membership_custom_group_creation['id'], "Couldn't create membership group");
      return $membership_custom_group_creation['id'];
    } else {
      return $membership_custom_group_search['id'];
    }
  }

  /**
   * Get any membership type, but create one if there is none there at all
   */
  public function getRandomMembershipType() {
    $type_query = civicrm_api3('MembershipType', 'get', ['is_active' => 1]);
    if (empty($type_query['count'])) {
      error_log("create type");
      $create_query = civicrm_api3('MembershipType', 'create', [
          "name"                 => "Member Test",
          "member_of_contact_id" => "1",
          "financial_type_id"    => "2",
          "minimum_fee"          => "10",
          "duration_unit"        => "year",
          "domain_id"            => "1",
          "duration_interval"    => "1",
          "period_type"          => "rolling",
          "visibility"           => "Public",
          "is_active"            => "1",
          "contribution_type_id" => "2"
      ]);
      $this->assertNotEmpty($create_query['id'], "Couldn't create membership type");
      return $create_query['id'];

    } else {
      $first_type = reset($type_query['values']);
      return $first_type['id'];
    }
  }

  /**
   * Create a change activity
   *
   * @param $membership
   * @param $change_date
   * @param $old_amount
   * @param $new_amount
   * @throws CiviCRM_API3_Exception
   */
  public function createChangeActivity($membership, $change_date, $old_amount, $new_amount) {
    $change_logic = CRM_Membership_FeeChangeLogic::getSingleton();
    $activity_data = [
        'activity_type_id'   => $change_logic->getActivityTypeID(),
        'target_id'          => $membership['contact_id'],
        'subject'            => "TEST",
        'activity_date_time' => $change_date,
        'source_contact_id'  => CRM_Core_Session::getLoggedInContactID(),
        'source_record_id'   => $membership['id'],

        // custom data
        'p60membership_fee_update.annual_amount_before'   => number_format($old_amount, 2, '.', ''),
        'p60membership_fee_update.annual_amount_after'    => number_format($new_amount, 2, '.', ''),
        'p60membership_fee_update.annual_amount_increase' => number_format(($new_amount-$old_amount), 2, '.', ''),
    ];

    // normalise
    CRM_Membership_CustomData::resolveCustomFields($activity_data);

    // create activity
    civicrm_api3('Activity', 'create', $activity_data);
  }
}
