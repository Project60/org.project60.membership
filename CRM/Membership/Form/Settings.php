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

use Civi\Api4\FinancialType;
use Civi\Api4\MembershipType;
use CRM_Membership_ExtensionUtil as E;

final class CRM_Membership_Form_Settings extends CRM_Admin_Form_Generic {

  private const SYNC_MAPPING_PREFIX = 'syncmap_';

  private const SYNCHRONISE_NOW = 'synchronise_payment_now';

  public function preProcess(): void {
    $this->sections = self::getSections();
    parent::preProcess();
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function buildQuickForm(): void {
    $this->addSyncMappingFields();
    $this->addSynchroniseNowField();
    parent::buildQuickForm();

    Civi::resources()->addScriptFile(E::LONG_NAME, 'js/settings.js');
  }

  /**
   * @return array<string, mixed>
   */
  public function setDefaultValues(): array {
    /** @var array<string, mixed> $defaults */
    $defaults = parent::setDefaultValues();
    foreach (CRM_Membership_Settings::getSettings()->getSyncMapping() as $financialTypeId => $membershipTypeIds) {
      $defaults[self::SYNC_MAPPING_PREFIX . $financialTypeId] = $membershipTypeIds;
    }

    return $defaults;
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function postProcess(): void {
    parent::postProcess();

    $values = $this->exportValues();
    $settings = CRM_Membership_Settings::getSettings();

    $mapping = $settings->getSetting('sync_mapping');
    if (!is_array($mapping)) {
      $mapping = [];
    }
    foreach (array_keys(self::getFinancialTypes()) as $financialTypeId) {
      $mapping[$financialTypeId] = self::toIdList($values[self::SYNC_MAPPING_PREFIX . $financialTypeId] ?? []);
    }
    $settings->setSetting('sync_mapping', $mapping);

    if ((bool) ($values[self::SYNCHRONISE_NOW] ?? FALSE)) {
      CRM_Membership_PaidByLogic::getSingleton()->updateDerivedFields();
    }

    CRM_Core_Session::singleton()->replaceUserContext(
      CRM_Utils_System::url('civicrm/admin/setting/' . $this->getSettingPageFilter(), 'reset=1')
    );
  }

  /**
   * @throws \CRM_Core_Exception
   */
  private function addSyncMappingFields(): void {
    $membershipTypes = self::getMembershipTypes();
    $weight = 0;
    foreach (self::getFinancialTypes() as $financialTypeId => $financialTypeLabel) {
      $name = self::SYNC_MAPPING_PREFIX . $financialTypeId;
      $this->add('select', $name, $financialTypeLabel, $membershipTypes, FALSE, [
        'multiple' => 'multiple',
        'class' => 'crm-select2',
      ]);
      $weight += 10;
      $this->sections['mapping']['fields'][$name] = ['weight' => $weight];
    }
  }

  private function addSynchroniseNowField(): void {
    $this->add('checkbox', self::SYNCHRONISE_NOW, E::ts('Update Derived Fields on Save'));
    $this->sections['derived']['fields'][self::SYNCHRONISE_NOW] = [
      'weight' => 60,
      'description' => E::ts(
        'If checked, all derived fields will be re-calculated from the currently linked recurring payment.'
      ),
    ];
  }

  /**
   * @return list<int>
   */
  private static function toIdList(mixed $selected): array {
    $ids = [];
    if (is_array($selected)) {
      foreach ($selected as $id) {
        if (is_numeric($id)) {
          $ids[] = (int) $id;
        }
      }
    }

    return $ids;
  }

  /**
   * @return array<string, array{title: string, icon: string, weight: int, description?: string}>
   */
  private static function getSections(): array {
    return [
      'number' => [
        'title' => E::ts('Membership Number Integration'),
        'icon' => 'fa-hashtag',
        'weight' => 10,
      ],
      'cancellation' => [
        'title' => E::ts('Membership Cancellation'),
        'icon' => 'fa-ban',
        'weight' => 20,
      ],
      'payment' => [
        'title' => E::ts('Payment Integration'),
        'icon' => 'fa-credit-card',
        'weight' => 30,
      ],
      'derived' => [
        'title' => E::ts('Derived Fields'),
        'icon' => 'fa-calculator',
        'weight' => 40,
      ],
      'late' => [
        'title' => E::ts('Late Payments'),
        'icon' => 'fa-clock',
        'weight' => 50,
      ],
      'tokens' => [
        'title' => E::ts('Additional Tokens'),
        'icon' => 'fa-code',
        'weight' => 60,
        'description' => E::ts(
          'You can define arbitrary membership custom fields to be added to the membership token list.'
        ),
      ],
      'sync' => [
        'title' => E::ts('Payment Synchronisation Tool'),
        'icon' => 'fa-arrows-rotate',
        'weight' => 70,
      ],
      'status' => [
        'title' => E::ts('Membership Status'),
        'icon' => 'fa-signal',
        'weight' => 80,
      ],
      'mapping' => [
        'title' => E::ts('Financial Type Mapping'),
        'icon' => 'fa-arrows-left-right',
        'weight' => 90,
        'description' => E::ts(
          'Here you can decide which contribution types should be associated with which membership types.'
        ),
      ],
    ];
  }

  /**
   * @return array<int, string>
   *
   * @throws \CRM_Core_Exception
   */
  private static function getFinancialTypes(): array {
    /** @var array<int, string> $financialTypes */
    $financialTypes = FinancialType::get(FALSE)
      ->addSelect('id', 'label')
      ->addWhere('is_active', '=', TRUE)
      ->addOrderBy('label')
      ->execute()
      ->column('label', 'id');

    return $financialTypes;
  }

  /**
   * @return array<int, string>
   *
   * @throws \CRM_Core_Exception
   */
  private static function getMembershipTypes(): array {
    /** @var array<int, string> $membershipTypes */
    $membershipTypes = MembershipType::get(FALSE)
      ->addSelect('id', 'name')
      ->addWhere('is_active', '=', TRUE)
      ->addOrderBy('weight')
      ->execute()
      ->column('name', 'id');

    return $membershipTypes;
  }

}
