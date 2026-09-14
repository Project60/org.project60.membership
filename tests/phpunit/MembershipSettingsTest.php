<?php
/*-------------------------------------------------------+
| Project 60 - Membership Extension                      |
| Copyright (C) 2026 SYSTOPIA                            |
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
declare(strict_types = 1);
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols

require_once 'MembershipTestBase.php';

/**
 * @group headless
 *
 * @covers \CRM_Membership_Settings
 */
class MembershipSettingsTest extends MembershipTestBase {

  public function setUp(): void {
    parent::setUp();
    /** @var \Civi\Core\SettingsManager $manager */
    $manager = Civi::service('settings_manager');
    $manager->flush();
  }

  public function testSetAndGetSetting(): void {
    $settings = CRM_Membership_Settings::getSettings();
    $settings->setSetting('sync_range', 400);

    self::assertSame(400, $settings->getSetting('sync_range'));
    self::assertSame(400, Civi::settings()->get('p60_membership_sync_range'));
  }

  public function testUnknownKeyReturnsNull(): void {
    self::assertNull(CRM_Membership_Settings::getSettings()->getSetting('annual_amount_raw_field'));
  }

  public function testSetUnknownKeyThrows(): void {
    $this->expectException(CRM_Core_Exception::class);
    CRM_Membership_Settings::getSettings()->setSetting('unknown_setting', 1);
  }

  public function testFallsBackToLegacySettings(): void {
    Civi::settings()->set(CRM_Membership_Settings::LEGACY_SETTING, ['paid_via_field' => '27']);

    self::assertSame(27, CRM_Membership_Settings::getSettings()->getPaidViaFieldID());
  }

  public function testIndividualSettingTakesPrecedenceOverLegacySettings(): void {
    Civi::settings()->set(CRM_Membership_Settings::LEGACY_SETTING, ['paid_via_field' => '27']);
    Civi::settings()->set('p60_membership_paid_via_field', 42);

    self::assertSame(42, CRM_Membership_Settings::getSettings()->getPaidViaFieldID());
  }

  public function testMigrateLegacySettings(): void {
    Civi::settings()->set(CRM_Membership_Settings::LEGACY_SETTING, [
      'sync_mapping' => ['2' => ['1', '2']],
      'sync_range' => '400',
      'grace_period' => '0',
      'live_statuses' => ['1', '2'],
      'sync_minimum_date' => 'now - 2 years',
      'sync_maximum_date' => '',
      'membership_number_show' => '1',
      'hide_auto_renewal' => NULL,
      'paid_via_field' => '27',
      'payment_type_field_mapping' => '1:3,2:2',
    ]);

    CRM_Membership_Settings::migrateLegacySettings();

    $settings = Civi::settings();
    self::assertFalse($settings->hasExplicit(CRM_Membership_Settings::LEGACY_SETTING));
    self::assertSame([2 => ['1', '2']], $settings->get('p60_membership_sync_mapping'));
    self::assertSame(400, $settings->get('p60_membership_sync_range'));
    self::assertFalse($settings->hasExplicit('p60_membership_grace_period'));
    self::assertSame(['1', '2'], $settings->get('p60_membership_live_statuses'));
    self::assertSame('now - 2 years', $settings->get('p60_membership_sync_minimum_date'));
    self::assertFalse($settings->hasExplicit('p60_membership_sync_maximum_date'));
    self::assertTrue($settings->get('p60_membership_membership_number_show'));
    self::assertFalse($settings->hasExplicit('p60_membership_hide_auto_renewal'));
    self::assertSame(27, $settings->get('p60_membership_paid_via_field'));
    self::assertSame('1:3,2:2', $settings->get('p60_membership_payment_type_field_mapping'));
  }

  public function testMigrationKeepsExistingIndividualSettings(): void {
    Civi::settings()->set('p60_membership_sync_range', 30);
    Civi::settings()->set(CRM_Membership_Settings::LEGACY_SETTING, ['sync_range' => '400']);

    CRM_Membership_Settings::migrateLegacySettings();

    self::assertSame(30, Civi::settings()->get('p60_membership_sync_range'));
    self::assertFalse(Civi::settings()->hasExplicit(CRM_Membership_Settings::LEGACY_SETTING));
  }

  public function testMigrationWithoutLegacySettingsDoesNothing(): void {
    CRM_Membership_Settings::migrateLegacySettings();

    self::assertFalse(Civi::settings()->hasExplicit(CRM_Membership_Settings::LEGACY_SETTING));
    self::assertFalse(Civi::settings()->hasExplicit('p60_membership_sync_range'));
  }

}
