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
 * @covers \CRM_Membership_SettingsMetadata
 */
class MembershipSettingsMetadataTest extends MembershipTestBase {

  /**
   * @dataProvider provideValidDateExpressions
   */
  public function testValidDateExpression(mixed $value): void {
    self::assertTrue(CRM_Membership_SettingsMetadata::validateDateExpression($value));
  }

  /**
   * @return array<string,array{mixed}>
   */
  public static function provideValidDateExpressions(): array {
    return [
      'null' => [NULL],
      'empty' => [''],
      'date' => ['2017-01-01'],
      'relative' => ['now - 2 days'],
    ];
  }

  public function testInvalidDateExpression(): void {
    $this->expectException(CRM_Core_Exception::class);
    CRM_Membership_SettingsMetadata::validateDateExpression('no date at all');
  }

  /**
   * @dataProvider provideValidPaymentTypeMappings
   */
  public function testValidPaymentTypeMapping(mixed $value): void {
    self::assertTrue(CRM_Membership_SettingsMetadata::validatePaymentTypeMapping($value));
  }

  /**
   * @return array<string,array{mixed}>
   */
  public static function provideValidPaymentTypeMappings(): array {
    return [
      'null' => [NULL],
      'empty' => [''],
      'single' => ['1:3'],
      'multiple' => ['1:3,2:2,5:4,6:4'],
      'whitespace' => [' 1 : 3 , 2:2 '],
    ];
  }

  /**
   * @dataProvider provideInvalidPaymentTypeMappings
   */
  public function testInvalidPaymentTypeMapping(string $value): void {
    $this->expectException(CRM_Core_Exception::class);
    CRM_Membership_SettingsMetadata::validatePaymentTypeMapping($value);
  }

  /**
   * @return array<string,array{string}>
   */
  public static function provideInvalidPaymentTypeMappings(): array {
    return [
      'missing target' => ['1'],
      'trailing comma' => ['1:3,'],
      'letters' => ['a:b'],
      'wrong separator' => ['1-3'],
    ];
  }

  public function testMembershipStatusOptions(): void {
    $options = CRM_Membership_SettingsMetadata::getMembershipStatusOptions();

    self::assertNotEmpty($options);
    self::assertContainsOnly('string', $options);
  }

}
