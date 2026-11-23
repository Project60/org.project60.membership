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

use CRM_Membership_ExtensionUtil as E;

final class CRM_Membership_SettingsValidator {

  private const PAYMENT_TYPE_MAPPING_PATTERN = '/^\s*\d+\s*:\s*\d+\s*(,\s*\d+\s*:\s*\d+\s*)*$/';

  /**
   * @throws \CRM_Core_Exception
   */
  public static function validateDateExpression(mixed $value): bool {
    if ($value === NULL || $value === '' || (is_string($value) && strtotime($value) !== FALSE)) {
      return TRUE;
    }

    throw new CRM_Core_Exception(E::ts(
      "The value '%1' is not a valid date expression.",
      [1 => is_scalar($value) ? (string) $value : '']
    ));
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public static function validatePaymentTypeMapping(mixed $value): bool {
    if ($value === NULL || $value === ''
      || (is_string($value) && preg_match(self::PAYMENT_TYPE_MAPPING_PATTERN, $value) === 1)) {
      return TRUE;
    }

    throw new CRM_Core_Exception(E::ts(
      'The mapping has to be a comma separated list of "payment_instrument_id:option_value" pairs, e.g. "1:3,2:2".'
    ));
  }

}
