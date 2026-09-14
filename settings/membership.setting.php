<?php
/*-------------------------------------------------------+
| Project 60 - Membership Extension                      |
| Copyright (C) 2018 SYSTOPIA                            |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

/*
* Settings metadata file
*/

declare(strict_types = 1);

use CRM_Membership_ExtensionUtil as E;

$common = [
  'group_name' => 'Membership Payments',
  'group' => 'org.project60',
  'is_domain' => 1,
  'is_contact' => 0,
];

$fieldSelect = [
  'type' => 'Integer',
  'html_type' => 'select',
  'html_attributes' => ['class' => 'crm-select2'],
  'is_required' => FALSE,
  'default' => NULL,
] + $common;

$toggle = [
  'type' => 'Boolean',
  'html_type' => 'toggle',
  'default' => FALSE,
] + $common;

$text = [
  'type' => 'String',
  'html_type' => 'text',
  'default' => NULL,
] + $common;

$days = [
  'type' => 'Integer',
  'html_type' => 'number',
  'html_attributes' => ['min' => 0],
  'default' => NULL,
] + $common;

$statusSelect = [
  'type' => 'Array',
  'html_type' => 'select',
  'html_attributes' => ['multiple' => 1, 'class' => 'huge crm-select2'],
  'default' => [],
  'pseudoconstant' => ['callback' => 'CRM_Membership_SettingsMetadata::getMembershipStatusOptions'],
] + $common;

$page = static fn (string $section, int $weight): array => [
  'membership' => ['section' => $section, 'weight' => $weight],
];

$dateExpressionHelp = [
  E::ts('This defines which contributions will be considered for synchronisation by defining a time frame. This is optional'),
  E::ts('You can enter any valid strtotime() expression like "2017-01-01" or "now - 2 days".'),
];

$settings = [
  'p60_membership_settings' => [
    'name' => 'p60_membership_settings',
    'type' => 'Array',
    'default' => NULL,
    'add' => '4.6',
    'description' => 'P60 Membership settings',
  ] + $common,

  'p60_membership_last_processed_id' => [
    'name' => 'p60_membership_last_processed_id',
    'type' => 'Integer',
    'default' => NULL,
    'title' => E::ts('Last processed membership ID'),
  ] + $common,

  'p60_membership_sync_mapping' => [
    'name' => 'p60_membership_sync_mapping',
    'type' => 'Array',
    'default' => NULL,
    'title' => E::ts('Financial Type Mapping'),
  ] + $common,

  'p60_membership_membership_number_field' => [
    'name' => 'p60_membership_membership_number_field',
    'title' => E::ts('Membership Number Field'),
    'pseudoconstant' => ['callback' => 'CRM_Membership_SettingsMetadata::getMembershipNumberFieldOptions'],
    'help_text' => [
      E::ts('You can select a membership custom field to contain the Membership Number'),
      E::ts('The field has to be an active, alphanumeric (text) type, is_view, searchable custom field to be eligible.'),
    ],
    'settings_pages' => $page('number', 10),
  ] + $fieldSelect,

  'p60_membership_membership_number_show' => [
    'name' => 'p60_membership_membership_number_show',
    'title' => E::ts('Show in Summary View'),
    'help_text' => E::ts("If you select this, the current membership number of the contact will be show in the contact's summary view, underneath the external identifier."),
    'settings_pages' => $page('number', 20),
  ] + $toggle,

  'p60_membership_membership_number_generator' => [
    'name' => 'p60_membership_membership_number_generator',
    'title' => E::ts('Number Pattern'),
    'help_text' => [
      E::ts('If you fill this field the membership number will automatically generated for new memberships.'),
      E::ts('Any characters are allowed but it is advisable to stick to alphanumeric characters, and to avoid whitespaces and special characters.'),
      E::ts('Additionally, the following special tokens are currently available: {mid[+offset]} for the CiviCRM Membership ID and {cid[+offset]} for the CiviCRM Contact ID, each with an optional offset value, e.g. {mid+1000} or {cid+100000}.'),
    ],
    'settings_pages' => $page('number', 30),
  ] + $text,

  'p60_membership_membership_cancellation_date_field' => [
    'name' => 'p60_membership_membership_cancellation_date_field',
    'title' => E::ts('Cancel Date Field'),
    'pseudoconstant' => ['callback' => 'CRM_Membership_SettingsMetadata::getDateFieldOptions'],
    'help_text' => E::ts('The core membership entity only has an end date, but if you want to keep track of the date somebody cancelled the membership, you can create a new custom field of type Date. You can then select it here to make this date available as a token.'),
    'settings_pages' => $page('cancellation', 10),
  ] + $fieldSelect,

  'p60_membership_membership_cancellation_reason_field' => [
    'name' => 'p60_membership_membership_cancellation_reason_field',
    'title' => E::ts('Cancel Reason Field'),
    'pseudoconstant' => ['callback' => 'CRM_Membership_SettingsMetadata::getSelectFieldOptions'],
    'help_text' => E::ts("The core membership entity doesn't have a field for the cancel reason, but if you want record this data, you can create a new custom field of type Text. You can then select it here to make this date available as a token."),
    'settings_pages' => $page('cancellation', 20),
  ] + $fieldSelect,

  'p60_membership_paid_via_field' => [
    'name' => 'p60_membership_paid_via_field',
    'title' => E::ts('Payment Contract (paid via) Field'),
    'html_attributes' => [],
    'pseudoconstant' => ['callback' => 'CRM_Membership_SettingsMetadata::getPaidViaFieldOptions'],
    'help_text' => [
      E::ts('You can select a membership custom field to record the connection to any recurring contribution, e.g. a CiviSEPA mandate or similar.'),
      E::ts('The field has to be an integer type, active, read only, and searchable custom field to be eligible.'),
    ],
    'settings_pages' => $page('payment', 10),
  ] + $fieldSelect,

  'p60_membership_annual_amount_field' => [
    'name' => 'p60_membership_annual_amount_field',
    'title' => E::ts('Annual Amount Field'),
    'pseudoconstant' => ['callback' => 'CRM_Membership_SettingsMetadata::getEditableAmountFieldOptions'],
    'help_text' => E::ts('Select an editable monetary field where you define the annual expected membership fees, should they deviate from the amount defined by the membership type.'),
    'settings_pages' => $page('payment', 20),
  ] + $fieldSelect,

  'p60_membership_record_fee_updates' => [
    'name' => 'p60_membership_record_fee_updates',
    'title' => E::ts('Record annual fee changes'),
    'help_text' => E::ts('If active, any change of the annual amount (determined by the two fields above) will be recorded as an activity.'),
    'settings_pages' => $page('payment', 30),
  ] + $toggle,

  'p60_membership_paid_via_end_with_status' => [
    'name' => 'p60_membership_paid_via_end_with_status',
    'title' => E::ts('End with Statuses'),
    'help_text' => E::ts('For any membership status you select, the linked recurring contribution or mandate will be ended automatically should a membership be set to that status.'),
    'settings_pages' => $page('payment', 40),
  ] + $statusSelect,

  'p60_membership_hide_auto_renewal' => [
    'name' => 'p60_membership_hide_auto_renewal',
    'title' => E::ts('Hide Auto Renewal'),
    'help_text' => E::ts('The built-in auto renewal feature interferes with the P60 membership concept, so we recommend not to use it. If you activate this option the fields will be hidden from the UI and the column in the tab and the search result will be freed up for something else.'),
    'settings_pages' => $page('payment', 50),
  ] + $toggle,

  'p60_membership_update_membership_status' => [
    'name' => 'p60_membership_update_membership_status',
    'title' => E::ts('Extend membership when contribution is completed'),
    'help_text' => [
      E::ts('Extend the membership as soon as the membership contribution is set to completed.'),
      E::ts('This will calculate the new end date and a new status.'),
      E::ts('Remark: This only works if there is exactly one contribution per membership period.'),
    ],
    'settings_pages' => $page('payment', 60),
  ] + $toggle,

  'p60_membership_installment_amount_field' => [
    'name' => 'p60_membership_installment_amount_field',
    'title' => E::ts('Installment Amount Field'),
    'pseudoconstant' => ['callback' => 'CRM_Membership_SettingsMetadata::getReadOnlyAmountFieldOptions'],
    'help_text' => E::ts('Select a read-only monetary field to display the installment amount as provided by the paid-via recurring contribution.'),
    'settings_pages' => $page('derived', 10),
  ] + $fieldSelect,

  'p60_membership_diff_amount_field' => [
    'name' => 'p60_membership_diff_amount_field',
    'title' => E::ts('Annual Gap Field'),
    'pseudoconstant' => ['callback' => 'CRM_Membership_SettingsMetadata::getReadOnlyAmountFieldOptions'],
    'help_text' => E::ts('Select a read-only monetary field to display any deviation between annual amount as required and as provided by the paid-via recurring contribution.'),
    'settings_pages' => $page('derived', 20),
  ] + $fieldSelect,

  'p60_membership_payment_frequency_field' => [
    'name' => 'p60_membership_payment_frequency_field',
    'title' => E::ts('Payment Frequency Field'),
    'pseudoconstant' => ['callback' => 'CRM_Membership_SettingsMetadata::getSelectFieldOptions'],
    'help_text' => E::ts('You can choose any select field, but its options have to have entries for the following values: 1 (monthly), 3 (quarterly), 6 (semi-annually), 12 (annually).'),
    'settings_pages' => $page('derived', 30),
  ] + $fieldSelect,

  'p60_membership_payment_type_field' => [
    'name' => 'p60_membership_payment_type_field',
    'title' => E::ts('Payment Type Field'),
    'html_attributes' => [],
    'pseudoconstant' => ['callback' => 'CRM_Membership_SettingsMetadata::getSelectFieldOptions'],
    'help_text' => E::ts("Pick a field to reflect your recurring contribution's payment instrument. You can simply use the built-in payment_instrument group, or specifiy a mapping below."),
    'settings_pages' => $page('derived', 40),
  ] + $fieldSelect,

  'p60_membership_payment_type_field_mapping' => [
    'name' => 'p60_membership_payment_type_field_mapping',
    'title' => E::ts('Payment Type Field Mapping'),
    'validate_callback' => 'CRM_Membership_SettingsMetadata::validatePaymentTypeMapping',
    'help_text' => [
      E::ts('You can enter a string to map the payment instrument id of your recurring contribution in the field above to the values of your option group.'),
      E::ts('The format is a comma separated list of "payment_instrument_id:option_group_value" mappings, e.g. "1:3,2:2,5:4,6:4".'),
    ],
    'settings_pages' => $page('derived', 50),
  ] + $text,

  'p60_membership_missing_period_amount_field' => [
    'name' => 'p60_membership_missing_period_amount_field',
    'title' => E::ts('Missing Amount (current period)'),
    'pseudoconstant' => ['callback' => 'CRM_Membership_SettingsMetadata::getReadOnlyAmountFieldOptions'],
    'help_text' => E::ts('Holds the outstanding amount for the current membership period. This is calcualted by the Membership.process job.'),
    'settings_pages' => $page('late', 10),
  ] + $fieldSelect,

  'p60_membership_sync_minimum_date' => [
    'name' => 'p60_membership_sync_minimum_date',
    'title' => E::ts('Only Synchronise From'),
    'validate_callback' => 'CRM_Membership_SettingsMetadata::validateDateExpression',
    'help_text' => $dateExpressionHelp,
    'settings_pages' => $page('sync', 10),
  ] + $text,

  'p60_membership_sync_maximum_date' => [
    'name' => 'p60_membership_sync_maximum_date',
    'title' => E::ts('Only Synchronise Until'),
    'validate_callback' => 'CRM_Membership_SettingsMetadata::validateDateExpression',
    'help_text' => $dateExpressionHelp,
    'settings_pages' => $page('sync', 20),
  ] + $text,

  'p60_membership_sync_range' => [
    'name' => 'p60_membership_sync_range',
    'title' => E::ts('Backward horizon (in days)'),
    'help_text' => E::ts("If a membership payment is received within this number of days before the current starting date, the start and the join date will be extended to the payment's received date."),
    'settings_pages' => $page('sync', 30),
  ] + $days,

  'p60_membership_grace_period' => [
    'name' => 'p60_membership_grace_period',
    'title' => E::ts('Grace Period (in days)'),
    'help_text' => [
      E::ts('Membership payments will be accepted up to this amount of days after the current end date.'),
      E::ts('In order to associate contributions with expired memberships, you will first have to manually extend those memberships.'),
    ],
    'settings_pages' => $page('sync', 40),
  ] + $days,

  'p60_membership_paid_by_field' => [
    'name' => 'p60_membership_paid_by_field',
    'title' => E::ts('Paid by Field'),
    'pseudoconstant' => ['callback' => 'CRM_Membership_SettingsMetadata::getPaidByFieldOptions'],
    'help_text' => [
      E::ts('You can select a membership custom field to indicate that another contact is paying for this membership.'),
      E::ts('The field has to be an contact reference type, active, and searchable custom field to be eligible.'),
    ],
    'settings_pages' => $page('sync', 50),
  ] + $fieldSelect,

  'p60_membership_live_statuses' => [
    'name' => 'p60_membership_live_statuses',
    'title' => E::ts('Live Statuses'),
    'help_text' => [
      E::ts("Only if a membership has a 'live' status, membership payments will be assigned to it."),
      E::ts('Caution: This status list is only valid for the payment synchronization tool, the membership extension job uses the parameter status_ids instead.'),
    ],
    'settings_pages' => $page('status', 10),
  ] + $statusSelect,
];

for ($i = 1; $i <= CRM_Membership_Settings::CUSTOM_MEMBERSHIP_TOKEN_COUNT; $i++) {
  $settings["p60_membership_custom_token_{$i}"] = [
    'name' => "p60_membership_custom_token_{$i}",
    'title' => E::ts('Additional Token %1', [1 => $i]),
    'pseudoconstant' => ['callback' => 'CRM_Membership_SettingsMetadata::getAllCustomFieldOptions'],
    'settings_pages' => $page('tokens', $i * 10),
  ] + $fieldSelect;
}

return $settings;
