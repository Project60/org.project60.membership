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

  public function testCalculateExpectedAmount() {
    $logic = new CRM_Membership_MembershipFeeLogic(['time_unit' => 'month']);
    $membership = $this->createMembership([
        'start_date'    => date('Y-m-01'),
        'annual_amount' => 60.00
    ]);
    $this->assertEquals('2019-12-31', $membership['end_date'], "Calculated end date differs.");
    $this->assertEquals(60.00, $logic->calculateExpectedFeeForCurrentPeriod($membership['id'], "Calculated fee off"));
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
