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

  /**
   * @var list<customFieldT>|null
   */
  private static ?array $customFields = NULL;

  /**
   * @return array<int, string>
   *
   * @throws \CRM_Core_Exception
   */
  public static function getPaidViaFieldOptions(): array {
    return self::getCustomFieldOptions(
      fn (array $field): bool => $field['data_type'] === 'Int' && $field['is_view'] && $field['is_searchable']
    );
  }

  /**
   * @return array<int, string>
   *
   * @throws \CRM_Core_Exception
   */
  public static function getMembershipNumberFieldOptions(): array {
    return self::getCustomFieldOptions(
      fn (array $field): bool => $field['data_type'] === 'String' && $field['html_type'] === 'Text'
        && !$field['is_view'] && $field['is_searchable']
    );
  }

  /**
   * @return array<int, string>
   *
   * @throws \CRM_Core_Exception
   */
  public static function getSelectFieldOptions(): array {
    return self::getCustomFieldOptions(
      fn (array $field): bool => $field['data_type'] === 'String' && $field['html_type'] === 'Select'
        && $field['is_searchable']
    );
  }

  /**
   * @return array<int, string>
   *
   * @throws \CRM_Core_Exception
   */
  public static function getPaidByFieldOptions(): array {
    return self::getCustomFieldOptions(
      fn (array $field): bool => $field['data_type'] === 'ContactReference' && $field['is_searchable']
    );
  }

  /**
   * @return array<int, string>
   *
   * @throws \CRM_Core_Exception
   */
  public static function getEditableAmountFieldOptions(): array {
    return self::getCustomFieldOptions(
      fn (array $field): bool => $field['data_type'] === 'Money' && !$field['is_view'] && $field['is_searchable']
    );
  }

  /**
   * @return array<int, string>
   *
   * @throws \CRM_Core_Exception
   */
  public static function getReadOnlyAmountFieldOptions(): array {
    return self::getCustomFieldOptions(
      fn (array $field): bool => $field['data_type'] === 'Money' && $field['is_view'] && $field['is_searchable']
    );
  }

  /**
   * @return array<int, string>
   *
   * @throws \CRM_Core_Exception
   */
  public static function getDateFieldOptions(): array {
    return self::getCustomFieldOptions(
      fn (array $field): bool => $field['data_type'] === 'Date' && $field['is_searchable']
    );
  }

  /**
   * @return array<int, string>
   *
   * @throws \CRM_Core_Exception
   */
  public static function getAllCustomFieldOptions(): array {
    return self::getCustomFieldOptions(fn (array $field): bool => TRUE);
  }

  /**
   * @return array<int, string>
   *
   * @throws \CRM_Core_Exception
   */
  public static function getMembershipStatusOptions(): array {
    /** @var array<int, string> $statuses */
    $statuses = MembershipStatus::get(FALSE)
      ->addSelect('id', 'label')
      ->addOrderBy('weight')
      ->execute()
      ->column('label', 'id');

    return $statuses;
  }

  /**
   * @param callable(customFieldT): bool $filter
   *
   * @return array<int, string>
   *
   * @throws \CRM_Core_Exception
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
   *
   * @throws \CRM_Core_Exception
   */
  private static function getCustomFields(): array {
    if (self::$customFields === NULL) {
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

    return self::$customFields;
  }

}
