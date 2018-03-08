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

/**
 * This class contains the logic connecting CiviSEPA mandates
 * to memberships
 * @see https://github.com/Project60/org.project60.membership/issues/10
 */
class CRM_Membership_PaidByLogic {

    protected static $singleton = NULL;

    public static function getSingleton() {
      if (self::$singleton === NULL) {
        self::$singleton = new CRM_Membership_PaidByLogic();
      }
      return self::$singleton;
    }

    /**
     * Render a nice representation of the
     */
    public function extendForm($formName, &$form) {
      // see if there is a paid_via field
      $settings = CRM_Membership_Settings::getSettings();
      $paid_via = $settings->getPaidViaField();
      if (!$paid_via) return;

      // get some IDs
      $membership_id = CRM_Utils_Request::retrieve('id',  'Integer');
      $contact_id    = CRM_Utils_Request::retrieve('cid', 'Integer');

      if ($formName == 'CRM_Member_Form_MembershipView') {
        // render the current
        $current_display = $this->renderRecurringContribution($paid_via, $membership_id);
        $form->assign('p60paidby_current', $current_display);
        $form->assign('p60paidby_label',   $paid_via['label']);
        // TODO: add buttons
        CRM_Core_Region::instance('page-body')->add(array(
          'template' => 'CRM/Membership/Snippets/PaidByField.tpl',
        ));
      }
    }

    /**
     * Connect a newly create CiviSEPA installment to a membership if applicable
     * @param $mandate_id
     * @param $contribution_recur_id
     * @param $contribution_id
     */
    public function assignSepaInstallment($mandate_id, $contribution_recur_id, $contribution_id) {
      // see if there is a paid_via field
      $settings = CRM_Membership_Settings::getSettings();
      $paid_via = $settings->getPaidViaField();
      if (!$paid_via) return;

      // then assign
      CRM_Core_DAO::executeQuery("
        INSERT IGNORE INTO civicrm_membership_payment (membership_id, contribution_id)
          SELECT
            entity_id           AS membership_id,
            {$contribution_id}  AS contribution_id
          FROM {$paid_via['table_name']}
          WHERE {$paid_via['column_name']} = {$contribution_recur_id};");
    }


    /**
     * render a textual representation of the field value
     */
    protected function renderRecurringContribution($paid_via_field, $membership_id) {
      $paid_via_key = $paid_via_field['key'];
      $membership = civicrm_api3('Membership', 'getsingle', array(
        'id'     => $membership_id,
        'return' => $paid_via_key));

      if (empty($membership[$paid_via_key])) {
        return ts('<i>None</i>');
      }

      try {
        // load the recurring contribution
        $contribution_recur = civicrm_api3('ContributionRecur', 'getsingle', array(
          'id'     => $membership[$paid_via_key]
        ));

        // generate a type string
        $type  = $this->renderRecurringContributionType($contribution_recur);
        $cycle = $this->renderRecurringContributionCycle($contribution_recur);

        return ts("%1: %2", array(1 => $type, 2 => $cycle));
      } catch (Exception $e) {
        return ts('<strong>Error</strong>');
      }
    }

    /**
     * render a string to represent the type of recurring contribution
     */
    protected function renderRecurringContributionType($contribution_recur) {
      if (function_exists('sepa_civicrm_install')) {
        // check if it is a SEPA mandate
        $mandates = civicrm_api3('SepaMandate', 'get', array(
          'entity_id'    => $contribution_recur['id'],
          'entity_table' => 'civicrm_contribution_recur',
          'return'       => 'id'
        ));

        if ($mandates['count'] > 0) {
          return ts('SEPA');
        }
      }

      // no type yet? try payment instrument...
      if (!$type && !empty($contribution_recur['payment_instrument_id'])) {
        $label = civicrm_api3('OptionValue', 'getvalue', array(
          'return'          => 'label',
          'value'           => $contribution_recur['payment_instrument_id'],
          'option_group_id' => 'payment_instrument'
        ));

        return ts("Recurring %1", array(1 => $label));
      }

      // fallback
      return ts("Recurring", array(1 => 'Contribution'));
    }

    /**
     * render a string to represent the type of recurring contribution
     */
    protected function renderRecurringContributionCycle($contribution_recur) {
      $money = CRM_Utils_Money::format($contribution_recur['amount'], $contribution_recur['currency']);
      $mode  = 'monthly';
      return ts("%1 %2", array(1 => $money, 2 => $mode));
    }
}