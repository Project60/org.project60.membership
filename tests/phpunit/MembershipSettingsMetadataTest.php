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

/**
 * @group headless
 *
 * @covers \CRM_Membership_SettingsMetadata
 */
class MembershipSettingsMetadataTest extends CRM_Membership_TestBase {

  public function testMembershipStatusOptions(): void {
    $options = CRM_Membership_SettingsMetadata::getMembershipStatusOptions();

    self::assertNotEmpty($options);
    self::assertContainsOnly('string', $options);
  }

}
