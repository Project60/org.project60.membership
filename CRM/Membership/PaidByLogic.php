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

  /** stores the pre/post hook records for contribution status changed */
  protected $contribution_status_monitoring_stack = array();

  /**
   * Contains a list of memberships which have been renewed by
   * the logic in this class.
   *
   * Why do we need this? If one updates a contribution related to a membership and puts the status
   * to completed we want the membership to be renewed. However if one does this through the UI
   * civicrm core does handle the renewal, but if the renewal is done through the api (e.g. with civi banking or sepa). then
   * the renewal is not handled.
   * This class contains functionality to cater for the latter but the side effect is that if you set a membership contribution to completed through
   * the ui the membership is renewed twice (e.g. for two periods instead of one).
   * So the solution is as soon as this extension renews a membership store the end date in this array and use the membership_pre hook to reset the end date
   * to our date.
   *
   * @var array
   */
  protected $renewed_memberships = array();

  /**
   * Contains a list with strings which should be replaced in the status messages.
   *
   * Use the function replaceStatusMessages to do the actual replacements.
   * The replacement does a search and replace in the status text.
   *
   * So far we only replace status messages from the postProcess hook and when the form
   * is a contribution form.
   *
   * Every item in the array consists of a subarray with two keys
   * - original: the original translated message text
   * - new: the new translated message text
   *
   * @var array
   */
  protected $replacementStatusMessages = array();

  public static function getSingleton()
  {
    if (self::$singleton === NULL) {
      self::$singleton = new CRM_Membership_PaidByLogic();
    }
    return self::$singleton;
  }

  /**
   * Replaces text within a status message
   * See also the description at the variable declaration $replacementStatusMessages
   */
  public function replaceStatusMessages() {
    $session = CRM_Core_Session::singleton();
    // Get the message buffer and clear it. That is ok as we are going to readd the
    // messages anyway.
    $statusMsgs = $session->getStatus(true);
    // Check for replacements and replace the text in the messages.
    foreach($this->replacementStatusMessages as $replacement) {
      foreach($statusMsgs as $key => $statusMsg) {
        if (stripos($statusMsg['text'], $replacement['original'])) {
          $statusMsgs[$key]['text'] = str_replace($replacement['original'], $replacement['new'], $statusMsg['text']);
        }
      }
    }
    // Readd the messages.
    foreach($statusMsgs as $statusMsg) {
      if (!is_array($statusMsg['options'])) {
        $statusMsg['options'] = array();
      }
      CRM_Core_Session::setStatus($statusMsg['text'], $statusMsg['title'], $statusMsg['type'], $statusMsg['options']);
    }
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

    // update derived fields
    $this->updateDerivedFields($membership_id);

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

    // Create membership payment with the api. So that the pre and post hooks are invoked.
    $membership_dao = CRM_Core_DAO::executeQuery("SELECT entity_id AS membership_id FROM {$paid_via['table_name']} WHERE {$paid_via['column_name']} = {$contribution_recur_id}");
    while($membership_dao->fetch()) {
      civicrm_api3('MembershipPayment', 'create', array('membership_id' => $membership_dao->membership_id, 'contribution_id' => $contribution_id));
    }
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
        'id'     => $membership_id,
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
   * Get the recurring contriutions based on the
   * @param $membership_ids array list of membership IDs
   *
   * @return array membership id => recurring contribution ID
   */
  public function getRecurringContributions($membership_ids) {
    $result = array();
    if (empty($membership_ids)) {
      return $result;
    }

    $settings = CRM_Membership_Settings::getSettings();
    $paid_via_field = $settings->getPaidViaField();
    if (!$paid_via_field) {
      return $result;
    }

    $membership_id_list = implode(',', $membership_ids);
    $query = CRM_Core_DAO::executeQuery("
      SELECT 
        entity_id                        AS membership_id,
        {$paid_via_field['column_name']} AS contribution_recur_id
      FROM {$paid_via_field['table_name']} paymentinfo
      WHERE entity_id IN ({$membership_id_list});");
    while ($query->fetch()) {
      $result[$query->membership_id] = $query->contribution_recur_id;
    }
    return $result;
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
  public function membershipUpdatePre($membership_id, &$update) {
    // simply store the update params on the stack, will be evaluated in membershipUpdatePOST
    $update['membership_id'] = $membership_id; // just to be on the save side :)
    array_push($this->monitoring_stack, $update);

    // Check whether we have need to reset the end date after we have done a renewal.
    // Read the explanation at the variable declaration of $renewed_memberships of this class.
    if (isset($this->renewed_memberships[$membership_id])) {
      if (isset($update['end_date'])) {
        // Create a replament for the status messages.
        $formattedOriginalEndDate = CRM_Utils_Date::customFormat($update['end_date'], '%B %E%f, %Y');
        $formattedNewEndDate = CRM_Utils_Date::customFormat($this->renewed_memberships[$membership_id]['end_date'],'%B %E%f, %Y');
        // Retrieve displayNamne
        $displayName = CRM_Core_DAO::singleValueQuery("
          SELECT display_name 
          FROM civicrm_membership 
          INNER JOIN civicrm_contact ON civicrm_membership.contact_id = civicrm_contact.id 
          WHERE civicrm_membership.id = %1",
          array (
              1=>array($membership_id, 'Integer')
          )
        );
        $replaceStatusMessage['original'] = ts("Membership for %1 has been updated. The membership End Date is %2.",
          array(
            1 => $displayName,
            2 => $formattedOriginalEndDate,
          )
        );
        $replaceStatusMessage['new'] = ts("Membership for %1 has been updated. The membership End Date is %2.",
          array(
            1 => $displayName,
            2 => $formattedNewEndDate,
          )
        );
        $this->replacementStatusMessages[] = $replaceStatusMessage;

        // Now correct the end date
        $update['end_date'] = $this->renewed_memberships[$membership_id]['end_date'];
      }
    }
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
    if (!empty($update['membership_id']) && $update['membership_id'] != $membership_id) {
      error_log("P60 Memberships: Illegal pre/post sequence: membership IDs don't match!");
      return;
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


  /**
   * MEMBERSHIP PAYMENT STATUS MONITORING
   *
   * If a membership payment is added also update the end date of the membership.
   * We don't check the status of the contribution as we assume only pending or completed contributions
   * will be added to the membership.
   *
   * @param $contribution_id integer Contribution ID
   * @param $membership_id        object  Contribution BAO object (?)
   * @throws Exception     only if something's wrong with the pre/post call sequence - shouldn't happen
   */
  public function membershipPaymentCreatePOST($contribution_id, $membership_id) {
    $settings = CRM_Membership_Settings::getSettings();
    if (!$settings->getSetting('update_membership_status')) {
      return;
    }

    $completed_status = civicrm_api3('OptionValue', 'getvalue', array('name' => 'Completed', 'option_group_id' => 'contribution_status', 'return' => 'value'));
    $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $contribution_id));
    if ($contribution['contribution_status_id'] != $completed_status) {
      return; // Do not calculate the new end date as the contribution is not yet completed.
    }

    // Calculate new end date and set this as the new membership end date.
    $membership = civicrm_api3('Membership', 'getsingle', array('id' => $membership_id));
    $currentEndDate = new DateTime($membership['end_date']);
    $newDates = CRM_Member_BAO_MembershipType::getRenewalDatesForMembershipType($membership_id, $contribution['receive_date']);
    $newEndDate = new DateTime($newDates['end_date']);
    if ($newEndDate > $currentEndDate) {
      $membershipStatus = CRM_Member_BAO_MembershipStatus::getMembershipStatusByDate(
        CRM_Utils_Date::customFormat($membership['start_date'], '%Y%m%d'),
        $newDates['end_date'],
        CRM_Utils_Date::customFormat($membership['join_date'], '%Y%m%d'),
        'today',
        FALSE,
        $membership['membership_type_id']
      );
      $membershipParams['status_id'] = $membershipStatus['id'];
      $membershipParams['id'] = $membership_id;
      $membershipParams['end_date'] = $newDates['end_date'];
      civicrm_api3('Membership', 'create', $membershipParams);
      $this->renewed_memberships[$membership_id] = $membershipParams;
    }
  }

  /**
   * Contribution status monitor function.
   *
   * Monitor for contribution status changed to completed to update the membership end date.
   *
   * @param $contribution_id
   * @param $params
   * @throws Exception     only if something's wrong with the pre/post call sequence - shouldn't happen
   */
  public function contributionUpdatePRE($contribution_id, $params) {
    $settings = CRM_Membership_Settings::getSettings();
    if (!$settings->getSetting('update_membership_status')) {
      return;
    }

    $completed_status = civicrm_api3('OptionValue', 'getvalue', array('name' => 'Completed', 'option_group_id' => 'contribution_status', 'return' => 'value'));
    $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $contribution_id));
    if ($params['contribution_status_id'] != $completed_status) {
      return;
    }
    if ($params['contribution_status_id'] == $contribution['contribution_status_id']) {
      return;
    }

    $this->contribution_status_monitoring_stack[$contribution_id] = $contribution;
  }

  /**
   * Contribution status monitor function.
   *
   * Monitor for contribution status changed to completed to update the membership end date.
   *
   * @param $contribution_id
   * @param $object
   * @throws Exception     only if something's wrong with the pre/post call sequence - shouldn't happen
   */
  public function contributionUpdatePOST($contribution_id, $object)
  {
    $settings = CRM_Membership_Settings::getSettings();
    if (!$settings->getSetting('update_membership_status')) {
      return;
    }

    if (!isset($this->contribution_status_monitoring_stack[$contribution_id])) {
      return;
    }

    $membershipPayments = civicrm_api3('MembershipPayment', 'get', array('contribution_id' => $contribution_id, 'options' => array('limit' => 0)));
    foreach ($membershipPayments['values'] as $membershipPayment) {
      $this->membershipPaymentCreatePOST($contribution_id, $membershipPayment['membership_id']);
    }

    unset($this->contribution_status_monitoring_stack[$contribution_id]);
  }

  //   DERIVED FIELDS

  /**
   * Recalculate all derived fields with on big SQL statement
   *
   * @param array|int $membership_ids
   */
  public function updateDerivedFields($membership_ids = NULL) {
    $settings = CRM_Membership_Settings::getSettings();
    $derived_fields = $settings->getDerivedFields();
    if (empty($derived_fields) || empty($derived_fields['paid_via_field'])) return;

    // start building the query
    $joins = $updates = $wheres = array();

    // are we doing this for some memberships only?
    if ($membership_ids) {
      if (is_array($membership_ids)) {
        $wheres[] = "membership.id IN (" . implode(',', $membership_ids) . ')';
      } elseif (is_numeric($membership_ids)) {
        $wheres[] = "membership.id = " . (int) $membership_ids;
      }
    }

    // join contribution recur
    $joins[] = "LEFT JOIN {$derived_fields['paid_via_field']['table_name']} paid_via ON paid_via.entity_id = membership.id";
    $joins[] = "LEFT JOIN civicrm_contribution_recur recur ON recur.id = paid_via.{$derived_fields['paid_via_field']['column_name']}";

    // calculate recurring annual amount
    $unit_year  = "IF(recur.frequency_unit = 'year', (recur.amount / recur.frequency_interval), 0.0)";
    $unit_month = "IF(recur.frequency_unit = 'month', (recur.amount * 12.0 / recur.frequency_interval), {$unit_year})";
    $current_annual = "IF(recur.id IS NULL, 0.0, {$unit_month})";

    // UPDATE installment_amount_field
    if (isset($derived_fields['installment_amount_field'])) {
      $joins[] = "LEFT JOIN {$derived_fields['installment_amount_field']['table_name']} installment_amount ON installment_amount.entity_id = membership.id";
      $updates[] = "installment_amount.{$derived_fields['installment_amount_field']['column_name']} = IF(recur.amount IS NULL, 0.00, recur.amount)";
    }

    // UPDATE diff_amount_field
    if (isset($derived_fields['diff_amount_field']) && isset($derived_fields['annual_amount_field'])) {
      $joins[] = "LEFT JOIN {$derived_fields['diff_amount_field']['table_name']} diff_amount ON diff_amount.entity_id = membership.id";
      $joins[] = "LEFT JOIN {$derived_fields['annual_amount_field']['table_name']} annual_amount ON annual_amount.entity_id = membership.id";
      $updates[] = "diff_amount.{$derived_fields['diff_amount_field']['column_name']} = {$current_annual} - annual_amount.{$derived_fields['annual_amount_field']['column_name']}";
    }

    // UPDATE payment_frequency_field
    if (isset($derived_fields['payment_frequency_field'])) {
      $intv_year  = "IF(recur.frequency_unit = 'year', recur.frequency_interval * 12, 0)";
      $interval = "IF(recur.frequency_unit = 'month', recur.frequency_interval, {$intv_year})";
      $joins[] = "LEFT JOIN {$derived_fields['payment_frequency_field']['table_name']} payment_frequency ON payment_frequency.entity_id = membership.id";
      $updates[] = "payment_frequency.{$derived_fields['payment_frequency_field']['column_name']} = {$interval}";
    }

    // UPDATE payment_type_field
    if (isset($derived_fields['payment_type_field'])) {
      $payment_type_value = "recur.payment_instrument_id";

      // apply mapping
      $mapping_raw = $settings->getSetting('payment_type_field_mapping');
      if (!empty($mapping_raw)) {
        $mappings = explode(',', $mapping_raw);
        foreach ($mappings as $mapping) {
          $from_to = explode(':', $mapping);
          $from = (int) $from_to[0];
          $to   = (int) $from_to[1];
          if ($from && $to) {
            $payment_type_value = "IF(recur.payment_instrument_id = {$from}, {$to}, {$payment_type_value})";
          }
        }
      }

      $joins[] = "LEFT JOIN {$derived_fields['payment_type_field']['table_name']} payment_type ON payment_type.entity_id = membership.id";
      $updates[] = "payment_type.{$derived_fields['payment_type_field']['column_name']} = {$payment_type_value}";
    }

    // check if there's anything to do
    if (empty($updates)) {
      return;
    }

    // compile query
    $update_query = "\nUPDATE civicrm_membership membership";
    foreach ($joins as $join) {
      $update_query .= "\n" . $join;
    }

    if ($updates) {
      $update_query .= "\n SET " . implode(", \n     ", $updates);
    }

    if ($wheres) {
      $update_query .= "\n WHERE (" . implode(') AND (', $wheres) . ')';
    }

    // execute!
    //CRM_Core_Error::debug_log_message($update_query);
    CRM_Core_DAO::executeQuery($update_query);
  }
}