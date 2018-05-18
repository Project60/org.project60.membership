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

/**
 * This class contains the logic connecting CiviSEPA mandates
 * to memberships
 * @see https://github.com/Project60/org.project60.membership/issues/10
 */
class CRM_Membership_PaidByLogic
{

  protected static $singleton = NULL;

  protected $financial_types = NULL;

  /** stores the pre/post hook records */
  protected $monitoring_stack = array();

  public static function getSingleton()
  {
    if (self::$singleton === NULL) {
      self::$singleton = new CRM_Membership_PaidByLogic();
    }
    return self::$singleton;
  }

  /**
   * Change the payment contract for a membership
   */
  public function changeContract($membership_id, $contribution_recur_id)
  {
    if (empty($membership_id)) {
      // no membership given
      return;
    }

    $settings = CRM_Membership_Settings::getSettings();
    $field_id = $settings->getPaidViaFieldID();
    $field_name = "custom_{$field_id}";
    if (empty($field_id)) {
      // paid via field not set
      return;
    }

    // load membership to compare
    $membership = civicrm_api3('Membership', 'getsingle', array(
        'id' => $membership_id,
        'return' => $field_name,
    ));

    if ($membership[$field_name] == $contribution_recur_id) {
      // nothing changed
      return;
    }

    // now: set the new ID
    civicrm_api3('Membership', 'create', array(
        'id' => $membership_id,
        $field_name => (int)$contribution_recur_id));

    // TODO: further changes?
  }

  /**
   * @param $membership_id
   */
  public function endContract($membership_id) {
    $contribution_recur = $this->getRecurringContribution($membership_id);
    if (empty($contribution_recur)) return; // nothing to do

    try {
      if (function_exists('sepa_civicrm_install')) { // if CiviSEPA is present
        // check if it's a SEPA Mandate
        $mandate = NULL;
        try {
          $mandate = civicrm_api3('SepaMandate', 'getsingle', array(
              'entity_id'    => $contribution_recur['id'],
              'entity_table' => 'civicrm_contribution_recur'));
        } catch (Exception $ex) {
          // seems it's no (valid) mandate after all...
        }
        if ($mandate) {
          CRM_Sepa_BAO_SEPAMandate::terminateMandate($mandate['id'], date('Ymd'), // no seconds due to SEPA-Bug
              E::ts("Connected membership [%1] was ended.", array(1 => $membership_id)));
          return; // our work here is done
        }
      }

      // if we get here, it should be a simple recurring contribution.
      // we just want to end it.
      civicrm_api3('ContributionRecur', 'create', array(
          'id'                     => $contribution_recur['id'],
          'contribution_status_id' => '1'  // "completed"
      ));
      CRM_Core_Session::setStatus(E::ts("Connected contribution [%1] was terminated."), E::ts("Payment terminated."), 'info');
    } catch (Exception $ex) {
      // if there's any problem: make sure the user is warned
      $message = E::ts("The connected recurring contribution [%1] couldn't be ended: %2.", array(
          1 => $contribution_recur['id'], 2 => $ex->getMessage()));
      CRM_Core_Session::setStatus($message, ts('Error'), 'error');
      CRM_Core_Error::debug_log_message("P60Membership: " . $message);
    }
  }

  /**
   * Render a nice representation of the
   */
  public function extendForm($formName, &$form)
  {
    // see if there is a paid_via field
    $settings = CRM_Membership_Settings::getSettings();
    $paid_via = $settings->getPaidViaField();
    if (!$paid_via) return;

    // get some IDs
    $membership_id = CRM_Utils_Request::retrieve('id', 'Integer');
    $contact_id = CRM_Utils_Request::retrieve('cid', 'Integer');

    if ($formName == 'CRM_Member_Form_MembershipView') {
      // render the current
      $contribution_recur = $this->getRecurringContribution($membership_id);
      $current_display = $this->renderRecurringContribution($contribution_recur);
      $edit_link = CRM_Utils_System::url("civicrm/membership/paidby", "reset=1&mid={$membership_id}&action=update");
      $form->assign('p60paid_via_current', $current_display);
      $form->assign('p60paid_via_label', $paid_via['label']);
      $form->assign('p60paid_via_edit', $edit_link);
      CRM_Core_Region::instance('page-body')->add(array(
          'template' => 'CRM/Membership/Snippets/PaidByField.tpl',
      ));

      // also, add some styling
      CRM_Core_Resources::singleton()->addStyleFile('org.project60.membership', 'css/p60mem.css');
    }
  }

  /**
   * Connect a newly create CiviSEPA installment to a membership if applicable
   * @param $mandate_id
   * @param $contribution_recur_id
   * @param $contribution_id
   */
  public function assignSepaInstallment($mandate_id, $contribution_recur_id, $contribution_id)
  {
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
  public function getRecurringContribution($membership_id)
  {
    $settings = CRM_Membership_Settings::getSettings();
    $paid_via_field = $settings->getPaidViaField();
    $paid_via_key = $paid_via_field['key'];
    $membership = civicrm_api3('Membership', 'getsingle', array(
        'id' => $membership_id,
        'return' => $paid_via_key));

    if (empty($membership[$paid_via_key])) {
      return NULL;
    }

    try {
      // load the recurring contribution
      $contribution_recur = civicrm_api3('ContributionRecur', 'getsingle', array(
          'id' => $membership[$paid_via_key]
      ));
      return $contribution_recur;
    } catch (Exception $e) {
      CRM_Core_Session::setStatus(ts("Couldn't load 'paid via' data."), ts('Error'), 'error');
      return NULL;
    }
  }

  /**
   * render extra parameters into this recurring contribution
   *
   * @return textual representation of the field value
   */
  public function renderRecurringContribution(&$contribution_recur)
  {
    if (empty($contribution_recur)) {
      return ts('<i>None</i>');
    }

    // generate a type string
    $type = $this->renderRecurringContributionType($contribution_recur);
    $cycle = $this->renderRecurringContributionCycle($contribution_recur);
    $annual = $this->calculateAnnual($contribution_recur);
    $text = ts("%1: %2", array(1 => $type, 2 => $cycle));

    // get contact
    $contact = $this->renderContact($contribution_recur['contact_id']);

    // copy values into object
    $contribution_recur['display_type'] = $type;
    $contribution_recur['display_cycle'] = $cycle;
    $contribution_recur['display_text'] = $text;
    $contribution_recur['display_annual'] = $annual;
    $contribution_recur['contact'] = $contact;
    $contribution_recur['financial_type'] = $this->getFinancialType($contribution_recur['financial_type_id']);

    if ($contribution_recur['contribution_status_id'] == '5'
        || $contribution_recur['contribution_status_id'] == '2') {
      $contribution_recur['classes'] = "p60-paid-via-row-eligible";
      $contribution_recur['display_status'] = E::ts("Active");
    } else {
      $contribution_recur['display_status'] = E::ts("Terminated");
    }

    return $text;
  }

  /**
   * render a string to represent the type of recurring contribution
   */
  protected function renderRecurringContributionType($contribution_recur)
  {
    if (function_exists('sepa_civicrm_install')) {
      // check if it is a SEPA mandate
      $mandates = civicrm_api3('SepaMandate', 'get', array(
          'entity_id' => $contribution_recur['id'],
          'entity_table' => 'civicrm_contribution_recur',
          'return' => 'id'
      ));

      if ($mandates['count'] > 0) {
        return ts('SEPA');
      }
    }

    // no type yet? try payment instrument...
    if (!$type && !empty($contribution_recur['payment_instrument_id'])) {
      $label = civicrm_api3('OptionValue', 'getvalue', array(
          'return' => 'label',
          'value' => $contribution_recur['payment_instrument_id'],
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
  protected function renderRecurringContributionCycle($contribution_recur)
  {
    // render amount
    $money = CRM_Utils_Money::format($contribution_recur['amount'], $contribution_recur['currency']);

    // render frequency
    switch ($contribution_recur['frequency_unit']) {
      case 'month':
        switch ($contribution_recur['frequency_interval']) {
          case 12:
            $mode = ts('annually');
            break 2;
          case 1:
            $mode = ts('monthly');
            break 2;
          default:
            $mode = ts('every %1 months', array(1 => $contribution_recur['frequency_interval']));
            break 2;
        }
      case 'year':
        switch ($contribution_recur['frequency_interval']) {
          case 1:
            $mode = ts('annually');
            break 2;
          default:
            $mode = ts('every %1 years', array(1 => $contribution_recur['frequency_interval']));
            break 2;
        }
      default:
        $mode = ts('(illegal frequency)');
        break;
    }

    // render collection day
    if (isset($contribution_recur['cycle_day'])) {
      $collection = ts("on the %1.", array(1 => $contribution_recur['cycle_day']));
    } else {
      $collection = '';
    }
    return ts("%1 %2 %3", array(1 => $money, 2 => $mode, 3 => $collection));
  }

  /**
   * Get a simple reduced attribute set of the given contact
   */
  protected function renderContact($contact_id)
  {
    if (empty($contact_id)) {
      return array(
          'display_name' => ts('Error'),
      );
    }

    if (!isset($this->_renderedContacts[$contact_id])) {
      $this->_renderedContacts[$contact_id] = civicrm_api3('Contact', 'getsingle', array(
          'id' => $contact_id,
          'return' => 'contact_type,display_name'
      ));
    }
    return $this->_renderedContacts[$contact_id];
  }

  /**
   * add a value annual to the recurring contribution
   *
   * return string rendered version
   */
  protected function calculateAnnual(&$contribution_recur)
  {
    $multiplier = 0;
    if ($contribution_recur['frequency_unit'] == 'month') {
      $multiplier = 12.0;
    } elseif ($contribution_recur['frequency_unit'] == 'year') {
      $multiplier = 1.0;
    }

    // calcualte and format
    $contribution_recur['annual'] = (float)$contribution_recur['amount'] * (float)$multiplier / (float)$contribution_recur['frequency_interval'];
    $contribution_recur['annual'] = number_format($contribution_recur['annual'], 2, '.', '');

    return CRM_Utils_Money::format($contribution_recur['annual'], $contribution_recur['currency']);
  }

  /**
   * get the name of the financial type
   */
  protected function getFinancialType($financial_type_id)
  {
    if ($this->financial_types === NULL) {
      $query = civicrm_api3('FinancialType', 'get', array(
          'return' => 'id,name',
          'sequential' => 0
      ));
      $this->financial_types = $query['values'];
    }
    return $this->financial_types[$financial_type_id]['name'];
  }


  /**
   * MEMBERSHIP STATUS MONITORING
   *
   * @param $membership_id integer Membership ID
   * @param $update        array   Membership update
   */
  public function membershipUpdatePre($membership_id, $update) {
    // simply store the update params on the stack, will be evaluated in membershipUpdatePOST
    $update['membership_id'] = $membership_id; // just to be on the save side :)
    array_push($this->monitoring_stack, $update);
  }


  /**
   * MEMBERSHIP STATUS MONITORING
   *
   * @param $membership_id integer Membership ID
   * @param $object        object  Membership BAO object (?)
   * @throws Exception     only if something's wrong with the pre/post call sequence - shouldn't happen
   */
  public function membershipUpdatePOST($membership_id, $object) {
    $update = array_pop($this->monitoring_stack);
    if ($update['membership_id'] != $membership_id) {
      throw new Exception("Illegal pre/post sequence: membership IDs don't match!");
    }

    // now check if we are supposed to do anything about this
    if (!empty($update['status_id'])) {
      $settings = CRM_Membership_Settings::getSettings();
      $status_ids = $settings->getSetting('paid_via_end_with_status');
      if (is_array($status_ids) && in_array($update['status_id'], $status_ids)) {
        // ok, we should end the connected mandate/recurring contribution
        $this->endContract($membership_id);
      }
    }
  }
}