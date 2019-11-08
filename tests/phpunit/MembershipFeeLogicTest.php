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

require_once 'MembershipTestBase.php';

/**
 * Test MembershipFeeLogic class
 *
 * @group headless
 */
class MembershipFeeLogicTest extends MembershipTestBase  {

  public function setUp() {
    parent::setUp();
  }


  public function tearDown() {
    parent::tearDown();
  }

  /**
   * Test whether the calculated amount is right
   */
  public function testCalculateExpectedAmount() {
    // test simple memberships
    $logic = new CRM_Membership_MembershipFeeLogic(['time_unit' => 'month']);
    $this->_testCalculateExpectedAmount($logic, ['start_date' => date('Y-m-01')], 60.00,60.00);
    $this->_testCalculateExpectedAmount($logic, ['start_date' => date('Y-m-01')], 0.00,0.00);
    $this->_testCalculateExpectedAmount($logic, ['start_date' => date('Y-m-01')], 0.00,-50.00);

    // test simple, shortened memberships
    $logic = new CRM_Membership_MembershipFeeLogic(['time_unit' => 'month']);
    $this->_testCalculateExpectedAmount($logic, ['start_date' => '2019-01-01', 'end_date' => '2019-06-30'], 30.00,60.00);
    $this->_testCalculateExpectedAmount($logic, ['start_date' => '2019-06-01', 'end_date' => '2019-06-30'], 5.00,60.00);

    // memberships with up/downgrades
    $this->_testCalculateExpectedAmount($logic, ['start_date' => date('2019-01-01')], 90.00,120.00,
        [['2019-06-10', 60.00, 120.00]]);
    $this->_testCalculateExpectedAmount($logic, ['start_date' => date('2019-01-01')], 90.00,60.00,
        [['2019-06-10', 120.00, 60.00]]);
    $this->_testCalculateExpectedAmount($logic, ['start_date' => date('2019-01-01')], 90.00,120.00,
        [['2019-03-11', 60.00, 120.00], ['2019-06-11', 120.00, 60.00], ['2019-09-11', 60.00, 120.00]]);
  }

  /**
   * Test whether the expected amount is correctly calculated for the given values
   *
   * @param $logic            CRM_Membership_MembershipFeeLogic contains the base parameters
   * @param $expected_amount  string annual amount
   * @param $annual_amount    string annual amount
   * @param $changes          array changes: [[date, from_amount, to_amount]]
   *
   * @return array $membership
   * @throws Exception
   */
  public function _testCalculateExpectedAmount($logic, $membership_data, $expected_amount, $annual_amount, $changes = []) {
    if (!empty($start_date)) {
      $membership_data['start_date'] = $start_date;
    }
    if ($annual_amount !== NULL) {
      $membership_data['annual_amount'] = $annual_amount;
    }
    $membership = $this->createMembership($membership_data);

    // add change activities
    foreach ($changes as $change) {
      $this->createChangeActivity($membership, $change[0], $change[1], $change[2]);
    }
    $this->assertEquals($expected_amount, $logic->calculateExpectedFeeForCurrentPeriod($membership['id'], "Calculated fee off"));
    return $membership;
}

  /**
   * Test the align function
   */
  public function testUnitDateDiff() {
    $logic = new CRM_Membership_MembershipFeeLogic(['time_unit' => 'month']);
    $this->assertEquals(0, $logic->getDateUnitDiff('2019-01-01', '2019-01-01'));
    $this->assertEquals(1, $logic->getDateUnitDiff('2019-01-01', '2019-01-02'));
    $this->assertEquals(1, $logic->getDateUnitDiff('2019-01-01', '2019-01-31'));
    $this->assertEquals(1, $logic->getDateUnitDiff('2019-01-01', '2019-02-01'));
    $this->assertEquals(2, $logic->getDateUnitDiff('2019-01-01', '2019-02-02'));
  }

    /**
   * Test the align function
   */
  public function testAlignDate() {
    $logic = new CRM_Membership_MembershipFeeLogic(['time_unit' => 'month']);
    $this->assertEquals('2019-01-01', $logic->alignDate('2019-01-14', FALSE));
    $this->assertEquals('2019-01-01', $logic->alignDate('2019-01-01', FALSE));
    $this->assertEquals('2019-01-01', $logic->alignDate('2019-01-31', FALSE));
    $this->assertEquals('2019-01-31', $logic->alignDate('2019-01-14', TRUE));
    $this->assertEquals('2019-01-31', $logic->alignDate('2019-01-01', TRUE));
    $this->assertEquals('2019-01-31', $logic->alignDate('2019-01-31', TRUE));

    $this->assertEquals('2019-02-01', $logic->alignDate('2019-01-31', TRUE, TRUE));
    $this->assertEquals('2018-12-31', $logic->alignDate('2019-01-31', FALSE, TRUE));


    $logic = new CRM_Membership_MembershipFeeLogic(['time_unit' => 'year']);
    $this->assertEquals('2019-01-01', $logic->alignDate('2019-01-14', FALSE));
    $this->assertEquals('2019-01-01', $logic->alignDate('2019-02-01', FALSE));
    $this->assertEquals('2019-01-01', $logic->alignDate('2019-03-31', FALSE));
    $this->assertEquals('2019-12-31', $logic->alignDate('2019-04-14', TRUE));
    $this->assertEquals('2019-12-31', $logic->alignDate('2019-05-01', TRUE));
    $this->assertEquals('2019-12-31', $logic->alignDate('2019-06-31', TRUE));

    $logic = new CRM_Membership_MembershipFeeLogic(['time_unit' => 'week']);
    $this->assertEquals('2019-01-14', $logic->alignDate('2019-01-14', FALSE));
    $this->assertEquals('2019-01-14', $logic->alignDate('2019-01-16', FALSE));
    $this->assertEquals('2019-01-14', $logic->alignDate('2019-01-20', FALSE));

    $this->assertEquals('2019-01-20', $logic->alignDate('2019-01-14', TRUE));
    $this->assertEquals('2019-01-20', $logic->alignDate('2019-01-14', TRUE));
    $this->assertEquals('2019-01-20', $logic->alignDate('2019-01-14', TRUE));
  }

}
