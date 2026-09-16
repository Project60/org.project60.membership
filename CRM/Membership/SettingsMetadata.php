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

use Civi\Api4\CustomField;
use Civi\Api4\MembershipStatus;
use CRM_Membership_ExtensionUtil as E;

/**
 * @phpstan-type customFieldT array{
 *   id: int,
 *   label: string,
 *   data_type: string,
 *   html_type: string,
 *   is_view: bool,
 *   is_searchable: bool,
 * }
 */
final class CRM_Membership_SettingsMetadata {

  private const PAYMENT_TYPE_MAPPING_PATTERN = '/^\s*\d+\s*:\s*\d+\s*(,\s*\d+\s*:\s*\d+\s*)*$/';

  /**
   * @var list<customFieldT>|null
   */
  private static ?array $customFields = NULL;

  /**
   * @return array<int,string>
   */
  public static function getPaidViaFieldOptions(): array {
    return self::getCustomFieldOptions(
      fn (array $field): bool => $field['data_type'] === 'Int' && $field['is_view'] && $field['is_searchable']
    );
  }

  /**
   * @return array<int,string>
   */
  public static function getMembershipNumberFieldOptions(): array {
    return self::getCustomFieldOptions(
      fn (array $field): bool => $field['data_type'] === 'String' && $field['html_type'] === 'Text'
        && !$field['is_view'] && $field['is_searchable']
    );
  }

  /**
   * @return array<int,string>
   */
  public static function getSelectFieldOptions(): array {
    return self::getCustomFieldOptions(
      fn (array $field): bool => $field['data_type'] === 'String' && $field['html_type'] === 'Select'
        && $field['is_searchable']
    );
  }

  /**
   * @return array<int,string>
   */
  public static function getPaidByFieldOptions(): array {
    return self::getCustomFieldOptions(
      fn (array $field): bool => $field['data_type'] === 'ContactReference' && $field['is_searchable']
    );
  }

  /**
   * @return array<int,string>
   */
  public static function getEditableAmountFieldOptions(): array {
    return self::getCustomFieldOptions(
      fn (array $field): bool => $field['data_type'] === 'Money' && !$field['is_view'] && $field['is_searchable']
    );
  }

  /**
   * @return array<int,string>
   */
  public static function getReadOnlyAmountFieldOptions(): array {
    return self::getCustomFieldOptions(
      fn (array $field): bool => $field['data_type'] === 'Money' && $field['is_view'] && $field['is_searchable']
    );
  }

  /**
   * @return array<int,string>
   */
  public static function getDateFieldOptions(): array {
    return self::getCustomFieldOptions(
      fn (array $field): bool => $field['data_type'] === 'Date' && $field['is_searchable']
    );
  }

  /**
   * @return array<int,string>
   */
  public static function getAllCustomFieldOptions(): array {
    return self::getCustomFieldOptions(fn (array $field): bool => TRUE);
  }

  /**
   * @return array<int,string>
   */
  public static function getMembershipStatusOptions(): array {
    try {
      /** @var array<int,string> $statuses */
      $statuses = MembershipStatus::get(FALSE)
        ->addSelect('id', 'label')
        ->addOrderBy('weight')
        ->execute()
        ->column('label', 'id');
    }
    catch (CRM_Core_Exception) {
      return [];
    }

    return $statuses;
  }

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

  /**
   * @param callable(customFieldT): bool $filter
   *
   * @return array<int,string>
   */
  private static function getCustomFieldOptions(callable $filter): array {
    $options = [];
    foreach (self::getCustomFields() as $field) {
      if ($filter($field)) {
        $options[$field['id']] = $field['label'];
      }
    }

    return $options;
  }

  /**
   * @return list<customFieldT>
   */
  private static function getCustomFields(): array {
    if (self::$customFields === NULL) {
      try {
        /** @var list<customFieldT> $customFields */
        $customFields = CustomField::get(FALSE)
          ->addSelect('id', 'label', 'data_type', 'html_type', 'is_view', 'is_searchable')
          ->addWhere('custom_group_id.extends', '=', 'Membership')
          ->addWhere('custom_group_id.is_active', '=', TRUE)
          ->addWhere('custom_group_id.is_multiple', '=', FALSE)
          ->addWhere('is_active', '=', TRUE)
          ->execute()
          ->getArrayCopy();
        self::$customFields = $customFields;
      }
      catch (CRM_Core_Exception) {
        self::$customFields = [];
      }
    }

    return self::$customFields;
  }

}
