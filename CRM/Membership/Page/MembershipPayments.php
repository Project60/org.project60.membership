<?php
/*-------------------------------------------------------+
| Project 60 - Membership Extension                      |
| Copyright (C) 2013-2014 SYSTOPIA                       |
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

require_once 'CRM/Core/Page.php';

class CRM_Membership_Page_MembershipPayments extends CRM_Core_Page {

  function run() {
    // load/store process mapping parameter:
    if (empty($_REQUEST['mapping'])) {
      // not given => load from settings
      $mapping = CRM_Core_BAO_Setting::getItem('Membership Payments', 'sync_mapping');

    } else {
      // given => store to settings
      $mapping = $_REQUEST['mapping'];
      CRM_Core_BAO_Setting::setItem($mapping, 'Membership Payments', 'sync_mapping');
    }
    $this->assign('mapping', $mapping);

    // load/store rangeback parameter
    if (empty($_REQUEST['rangeback'])) {
      // not given => load from settings
      $rangeback = CRM_Core_BAO_Setting::getItem('Membership Payments', 'sync_rangeback');
      if (empty($rangeback) && $rangeback!='0') $rangeback = "400";

    } else {
      // given => store to settings
      $rangeback = $_REQUEST['rangeback'];
      CRM_Core_BAO_Setting::setItem($rangeback, 'Membership Payments', 'sync_rangeback');
    }
    $this->assign('rangeback', $rangeback);



    // if parameter RUN is set, execute and compile list
    if (isset($_REQUEST['run'])) {
      if (empty($_REQUEST['rebuild'])) {
        $rebuild = 0;
      } else {
        $rebuild = 1;
      }
      if (empty($_REQUEST['adjust'])) {
        $rangeback = 0;
      }
      $result = civicrm_api('MembershipPayment', 'synchronize', array(
          'mapping'   => $mapping, 
          'rangeback' => $rangeback, 
          'rebuild'   => $rebuild, 
          'version'   => 3));
      $this->assign('results', print_r($result, true));

      // gather the data to display
      $this->getData($result['values'],        'mapped', true);
      $this->getData($result['no_membership'], 'no_membership');
      $this->getData($result['ambibiguous'],   'ambibiguous');
      $this->assign('executed', true);
    }

    parent::run();
  }

  // use DB statements to loop up data for the given contributions
  function getData($contribution_ids, $list_name, $add_membership = false) {
    if (count($contribution_ids) == 0) return;
    
    $contribution_id_list = implode(",", $contribution_ids);
    $date_format = CRM_Core_Config::singleton()->dateformatFull;
    $data_list = array();

    // include membership?
    if ($add_membership) {
      $membership_select = "civicrm_membership_payment.membership_id AS membership_id,";
      $membership_join = "LEFT JOIN civicrm_membership_payment ON civicrm_membership_payment.contribution_id = civicrm_contribution.id";
    } else {
      $membership_select = '';
      $membership_join = '';
    }

    // execute query
    $contribution_data_sql = "
    SELECT
      civicrm_contribution.id                AS contribution_id,
      civicrm_contribution.total_amount      AS contribution_amount,
      civicrm_contribution.currency          AS contribution_currency,
      civicrm_contribution.receive_date      AS contribution_date,
      civicrm_financial_type.name            AS contribution_type,
      civicrm_contact.id                     AS contact_id,
      $membership_select
      civicrm_contact.contact_type           AS contact_type,
      civicrm_contact.display_name           AS contact_name
    FROM       civicrm_contribution
    LEFT JOIN  civicrm_contact         ON civicrm_contribution.contact_id = civicrm_contact.id
    LEFT JOIN  civicrm_financial_type  ON civicrm_contribution.financial_type_id = civicrm_financial_type.id
    $membership_join
    WHERE 
      civicrm_contribution.id IN ($contribution_id_list);
    ";
    $results = CRM_Core_DAO::executeQuery($contribution_data_sql);
    while ($results->fetch()) {
      $data_list[] = array(
        'contribution_id'       => $results->contribution_id,
        'contribution_amount'   => CRM_Utils_Money::format($results->contribution_amount, $results->contribution_currency),
        'contribution_link'     => CRM_Utils_System::url('civicrm/contact/view/contribution', "&reset=1&action=view&id=".$results->contribution_id."&cid=".$results->contact_id),
        'contribution_date'     => CRM_Utils_Date::customFormat($results->contribution_date, $date_format),
        'contribution_type'     => $results->contribution_type,
        'membership_id'         => (empty($results->membership_id)?'':$results->membership_id),
        'membership_link'       => (empty($results->membership_id)?'':CRM_Utils_System::url('civicrm/contact/view/membership', "action=view&reset=1&cid=".$results->contact_id."&id=".$results->membership_id)),
        'contact_id'            => $results->contact_id,
        'contact_type'          => $results->contact_type,
        'contact_name'          => $results->contact_name,
        'contact_link'          => CRM_Utils_System::url('civicrm/contact/view', "&reset=1&cid=".$results->contact_id),
      );

      // make sure, we don't run into memory issues:
      if (count($data_list) >= 500) break;
    }
    $results->free();
    
    $this->assign($list_name, $data_list);
  }
}
