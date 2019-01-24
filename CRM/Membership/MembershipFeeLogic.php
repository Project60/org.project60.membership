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
 * This class contains the logic to automatically extend memberships
 *
 * @see https://github.com/Project60/org.project60.membership/issues/28
 */
class CRM_Membership_MembershipFeeLogic {

  protected $parameters = NULL;
  protected $cached_membership = NULL;
  protected $membership_types  = NULL;

  /**
   * CRM_Membership_MembershipFeeLogic constructor.
   *
   * Possible parameters:
   *  extend_if_paid - should the membership be extended by one period if enough money was paid?
   *  create_missing - should a missing fee contribution be created?
   *
   * @param $parameters array
   */
  public function __construct($parameters = []) {
    // set defaults
    $this->parameters = [
        'extend_if_paid'      => 0,
        'create_invoice'      => 0,
        'contribution_status' => '1',
        'missing_fee_grace'   => 0.99,
        'cutoff_today'        => 1,
    ];

    // overwrite with passed parameters
    foreach ($parameters as $parameter => $value) {
      $this->parameters[$parameter] = $value;
    }
  }

  /**
   * Check the given membership can be extended,
   *  based on the fees paid
   *
   * @param $membership_id
   */
  public function process($membership_id) {
    $fees_expected = $this->calculateExpectedFeeForCurrentPeriod($membership_id);
    $fees_paid     = $this->receivedFeeForCurrentPeriod($membership_id);

    $missing = $fees_expected - $fees_paid;
    if ($missing < $this->parameters['missing_fee_grace']) {
      // paid enough
      if (!empty($this->parameters['extend_if_paid'])) {
        $this->extendMembership($membership_id);
      }

    } else {
      // paid too little
      if (!empty($this->parameters['create_invoice'])) {
        $this->updatedMissingFeeContribution($membership_id, $missing);
      }
    }
  }

  /**
   * Calculate the fee expected for the current membership period
   *
   * @param $membership_id
   * @return float expected amount for current period
   */
  public function calculateExpectedFeeForCurrentPeriod($membership_id) {
    list($from_date, $to_date) = $this->getCurrentPeriod($membership_id);
    // TODO: find activities, break into phases, sum up

    return 0.0;
  }

  /**
   * Determine the received fee payments during the current period
   *
   * @param $membership_id
   * @return float received amount for current period
   */
  public function receivedFeeForCurrentPeriod($membership_id) {
    list($from_date, $to_date) = $this->getCurrentPeriod($membership_id);

    // get contribution type
    $membership = $this->getMembership($membership_id);
    $mtype = $this->getMembershipType($membership['membership_type_id']);
    $contribution_type_id = $mtype['contribution_type_id'];
    if (empty($contribution_type_id)) {
      $contribution_type_id = 2; // membership fee
    }

    // run the query
    $amount = CRM_Core_DAO::singleValueQuery("
      SELECT SUM(payment.total_amount)
      FROM civicrm_contribution payment
      LEFT JOIN civicrm_membership_payment cp ON cp.contribution_id = payment.id
      WHERE cp.membership_id = {$membership_id}
        AND payment.contribution_status_id IN ({$this->parameters['contribution_status']})
        AND payment.financial_type_id IN ({$contribution_type_id})
        AND DATE(payment.receive_date) >= DATE('{$from_date}')
        AND DATE(payment.receive_date) <= DATE('{$to_date}')");

    return $amount;
  }

  /**
   * Get the current membership period
   *
   * @param $membership_id integer membership ID
   *
   * @return array [from_date, to_date]
   */
  public function getCurrentPeriod($membership_id) {
    $membership = $this->getMembership($membership_id);
    $mtype = $this->getMembershipType($membership['membership_type_id']);

    // get from_date
    $start_date   = strtotime($membership['start_date']);
    $period_start = strtotime("-{$mtype['duration_interval']} {$mtype['duration_unit']}", strtotime($membership['end_date']));
    $from_date    = max($start_date, $period_start);

    // get to_date
    $to_date = strtotime($membership['start_date']);
    if ($this->parameters['cutoff_today']) {
      $to_date = min(strtotime('now'), $to_date);
    }

    // TODO: check empty range?
    return [date('Y-m-d', $from_date), date('Y-m-d', $to_date)];
  }

  /**
   * Create a contribution with the missing amount
   * The txrn_id of that contribution is 'P60M-[membership_id]-[end_date]', e.g. 'P60M-1234-20181231'
   */
  public function updatedMissingFeeContribution($membership_id, $missing_amount) {
    // TODO: see if exists, update if still pending,
  }





  /**
   * Load membership (cached)
   *
   * @param $membership_id integer membership ID
   * @return array|null
   */
  protected function getMembership($membership_id) {
    // check if we have it cached
    if (!empty($this->cached_membership['id']) && $this->cached_membership['id'] == $membership_id) {
      return $this->cached_membership;
    }

    // load membership
    $this->cached_membership = civicrm_api3('Membership', 'getsingle', ['id' => $membership_id]);
    return $this->cached_membership;
  }

  /**
   * Load membership type (cached)
   *
   * @param $membership_id integer membership ID
   * @return array|null
   */
  protected function getMembershipType($membership_type_id) {
    if ($this->membership_types === NULL) {
      $this->membership_types = [];
      $types = civicrm_api3('MembershipType', 'get', ['option.limit' => 0]);
      foreach ($types as $type) {
        $this->membership_types[$type['id']] = $type;
      }
    }
    return $this->membership_types[$membership_type_id];
  }
}