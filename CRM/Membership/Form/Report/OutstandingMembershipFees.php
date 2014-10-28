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

require_once 'CRM/Report/Form.php';

/*
TODO:
 - memberships that do not span the whole range, exclude?
 - add links to memberships
 - format dues as money
 */

/**
 * This report will identify memberships that are behind on paying their membersip fees
 */
class CRM_Membership_Form_Report_OutstandingMembershipFees extends CRM_Report_Form {

  protected $_addressField = FALSE;

  protected $_emailField = FALSE;

  protected $_summary = NULL;

  protected $_customGroupExtends = array('Membership');
  protected $_customGroupGroupBy = FALSE; 

  function __construct() {
    $this->_options = array(
      'membership_fee' => array(
        'title' => ts('Annual membership fee'),
        'type' => 'select',
        'default' => 'spec',
        'options' => array(
          'type' => ts('minimum fee of membership type'),
          'spec' => ts('manually specified (see right =>)'),
          ) + $this->getSuitableMembershipFeeCustomFields(),
        ),
      'membership_fee_override' => array(
        'title' => ts('Custom annual membership fee'),
        'type' => 'money',
        'default' => 0,
      ),
      'check_period' => array(
        'title' => ts('Check horizon'),
        'type' => 'select',
        'default' => '1 YEAR',
        'options' => array(
          '1 YEAR' => ts('one year'),
          '2 YEAR' => ts('two years'),
          '6 MONTH' => ts('half a year'),
          '3 MONTH' => ts('quarter'),
          '2 MONTH' => ts('two months'),
          '1 MONTH' => ts('one month'),
          ),
        ),
      'check_grace' => array(
        'title' => ts('Grace period in days'),
        'type' => 'text',
        'default' => 30,
        ),
    );

    $this->_columns = array(
      'civicrm_membership' => array(
        'dao' => 'CRM_Member_DAO_Membership',
        'fields' => array(
          'membership_dues' => array(
            'title' => ts('Money Owed'),
            'required' => TRUE,
            'no_repeat' => FALSE,
          ),
          'membership_id' => array(
            'title' => ts('Membership ID'),
            'no_display' => TRUE,
            'required' => TRUE,
            'no_repeat' => FALSE,
          ),
          'membership_type_id' => array(
            'title' => ts('Membership Type'),
            'required' => TRUE,
            'no_repeat' => TRUE,
          ),
          'start_date' => array(
            'title' => ts('Start Date'),
            'required' => TRUE,
          ),
          'join_date' => array(
            'title' => ts('Join Date'),
            'default' => TRUE,
          ),
        ),
        'filters' => array(
          'join_date' => array(
            'title' => ts("Join Date"),
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
          'start_date' => array(
            'title' => ts("Start Date"),
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
          'owner_membership_id' => array(
            'title' => ts('Membership Owner ID'),
            'operatorType' => CRM_Report_Form::OP_INT,
          ),
          'tid' => array(
            'name' => 'membership_type_id',
            'title' => ts('Membership Types'),
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Member_PseudoConstant::membershipType(),
          ),
        ),
        'grouping' => 'member-fields',
      ), 

      'civicrm_contact' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => array(
          'sort_name' => array(
            'title' => ts('Contact Name'),
            'required' => TRUE,
            'default' => TRUE,
            'no_repeat' => FALSE,
          ),
          'id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
        ),
        'filters' => array(
          'sort_name' => array(
            'title' => ts('Contact Name'),
            'operator' => 'like',
          ),
          'id' => array(
            'no_display' => TRUE,
          ),
        ),
        'grouping' => 'contact-fields',
      ),
      
      'civicrm_membership_status' => array(
        'dao' => 'CRM_Member_DAO_MembershipStatus',
        'alias' => 'mem_status',
        'fields' => array(
          'name' => array(
            'title' => ts('Status'),
            'default' => TRUE,
          ),
        ),
        'filters' => array(
          'sid' => array(
            'name' => 'id',
            'title' => ts('Status'),
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Member_PseudoConstant::membershipStatus(NULL, NULL, 'label'),
            'default' => array(1,2,3),
          ),
        ),
        'grouping' => 'member-fields',
      ),

      'civicrm_contribution' => array(
        'dao' => 'CRM_Contribute_DAO_Contribution',
        'alias' => 'mem_contribution',
        'fields' => array(
          'total_amount' => array(
            'title' => ts('Membership Fees Received'),
            'default' => TRUE,
          ),
        ),
        'grouping' => 'contribution-fields',
      ),
    );
    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;

    // if you calculate these, they are calculated on a 'by second' basis and end up crooked
    $this->period_factors = array(
          '1 YEAR' => 1.0,
          '2 YEAR' => 0.5,
          '6 MONTH' => 2.0,
          '3 MONTH' => 4.0,
          '2 MONTH' => 6.0,
          '1 MONTH' => 12.0,
      );

    parent::__construct();
  }

  /**
   * returns SQL term representing the expected membership fee / year
   */
  function getExpectedAmount() {
    $expected_amount = '0.00';
    if (empty($this->_params['membership_fee'])) {
      $expected_amount_source = 'type'; 
    } else {
      $expected_amount_source = $this->_params['membership_fee'];
    }

    switch ($expected_amount_source) {
      case 'spec':
        // 'spec' means, the amount was specified in the override
        if (!empty($this->_params['membership_fee_override'])) {
          $expected_amount = (float) $this->_params['membership_fee_override'];
        }
        break;
      
      case 'type':
        // 'type' means, the minumum amount as specified by the membership type is expected
        $expected_amount = '`civicrm_membership_type`.`minimum_fee`';
        break;

      default:
        // the others are custom fields
        $expected_amount = $this->_params['membership_fee'];
        break;
    }

    return $expected_amount;
  }

  /**
   * returns SQL term representing the first date of membership payment
   */
  function getCheckPeriodFrom() {
    $check_period = $this->_params['check_period'];
    $check_grace = $this->_params['check_grace'].' DAY';
    return "NOW() - INTERVAL $check_period - INTERVAL $check_grace";
  }

  /**
   * looks for custom fields that could potentially hold a membership fee
   *  i.e. CustomGroups for memberships with a monetary type
   *
   * @return an array of SQL specifiers like `civicrm_value_test_01`.`my_fee_23`
   *           mapped to a display name
   */
  function getSuitableMembershipFeeCustomFields() {
    $suitable_fields = array();
    $gsearch = civicrm_api3('CustomGroup', 'get', array('extends'=>'Membership'));
    if ($gsearch['count'] > 0) {
      foreach ($gsearch['values'] as $potential_group_id => $potential_group) {
        $fsearch = civicrm_api3('CustomField', 'get', array('data_type'=>'Money','custom_group_id'=>$potential_group_id));
        foreach ($fsearch['values'] as $field_id => $field) {
          $sql_term = '`' . $potential_group['table_name'] . '`.`' . $field['column_name'] . '`';
          $display = $potential_group['title'] . ' :: ' . $field['label'];
          $suitable_fields[$sql_term] = $display;
        }
      }
    }
    return $suitable_fields;
  }


  // override this function, since it only creates checkboxes and selects!
  function addOptions() {
    if (!empty($this->_options)) {      
      foreach ($this->_options as $fieldName => $field) {
        if ($field['type'] == 'money') {
          $this->addElement('text', "{$fieldName}", $field['title'], array('value' => $field['default']));
          $this->addRule("{$fieldName}", ts('Please enter a valid amount.'), 'money');
        } elseif ($field['type'] == 'text') {
          $this->addElement('text', "{$fieldName}", $field['title'], array('value' => $field['default']));
        }
      }
    }
    return parent::addOptions();
  }

  function preProcess() {
    $this->assign('reportTitle', ts('Outstanding Membership Fees'));
    parent::preProcess();
  }

  function select() {
    $select = $this->_columnHeaders = array();

    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {
          if (CRM_Utils_Array::value('required', $field) ||
            CRM_Utils_Array::value($fieldName, $this->_params['fields'])
          ) {
            if ($fieldName=='membership_dues') {
              // 'dues' is a calculated field
              $select[] = $this->getExpectedAmount() . " as {$tableName}_{$fieldName}";
            } elseif ($fieldName=='total_amount') {
              $select[] = "SUM({$field['dbAlias']}) as {$tableName}_{$fieldName}";
            } else {
              $select[] = "{$field['dbAlias']} as {$tableName}_{$fieldName}";
            }
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = $field['title'];
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = CRM_Utils_Array::value('type', $field);              
          }
        }
      }
    }

    $this->_select = "SELECT " . implode(', ', $select) . " ";
  }

  function from() {
    $payment_minimum_date = $this->getCheckPeriodFrom();
    $this->_from = "
         FROM  civicrm_membership {$this->_aliases['civicrm_membership']} {$this->_aclFrom}
               INNER JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
                          ON {$this->_aliases['civicrm_contact']}.id =
                             {$this->_aliases['civicrm_membership']}.contact_id AND {$this->_aliases['civicrm_membership']}.is_test = 0
               LEFT  JOIN civicrm_membership_status {$this->_aliases['civicrm_membership_status']}
                          ON {$this->_aliases['civicrm_membership_status']}.id =
                             {$this->_aliases['civicrm_membership']}.status_id 
               LEFT  JOIN civicrm_membership_payment
                          ON {$this->_aliases['civicrm_membership']}.id = civicrm_membership_payment.membership_id 
               LEFT  JOIN civicrm_membership_type
                          ON {$this->_aliases['civicrm_membership']}.membership_type_id = civicrm_membership_type.id 
               LEFT  JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
                          ON {$this->_aliases['civicrm_contribution']}.id = civicrm_membership_payment.contribution_id
                          AND {$this->_aliases['civicrm_contribution']}.receive_date >= ($payment_minimum_date)
                          AND {$this->_aliases['civicrm_contribution']}.contribution_status_id = 1
                           ";

    // join custom fields if necessary
    $membership_source = $this->_params['membership_fee'];
    if ($membership_source != 'spec' && $membership_source != 'type') {
      $components = explode('.', $membership_source);
      $this->_from .= " LEFT JOIN " . $components[0] . " ON {$this->_aliases['civicrm_membership']}.id = " . $components[0] . ".`entity_id`";
    }
  }

  function where() {
    $clauses = array();
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('filters', $table)) {
        foreach ($table['filters'] as $fieldName => $field) {
          $clause = NULL;
          if (CRM_Utils_Array::value('operatorType', $field) & CRM_Utils_Type::T_DATE) {
            $relative = CRM_Utils_Array::value("{$fieldName}_relative", $this->_params);
            $from     = CRM_Utils_Array::value("{$fieldName}_from", $this->_params);
            $to       = CRM_Utils_Array::value("{$fieldName}_to", $this->_params);

            $clause = $this->dateClause($field['name'], $relative, $from, $to, $field['type']);
          }
          else {
            $op = CRM_Utils_Array::value("{$fieldName}_op", $this->_params);
            if ($op) {
              $clause = $this->whereClause($field,
                $op,
                CRM_Utils_Array::value("{$fieldName}_value", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_min", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_max", $this->_params)
              );
            }
          }

          if (!empty($clause)) {
            $clauses[] = $clause;
          }
        }
      }
    }

    if (empty($clauses)) {
      $this->_where = "WHERE ( 1 ) ";
    }
    else {
      $this->_where = "WHERE " . implode(' AND ', $clauses);
    }

    if ($this->_aclWhere) {
      $this->_where .= " AND {$this->_aclWhere} ";
    }
  }

  function groupBy() {
    $this->_groupBy = " GROUP BY {$this->_aliases['civicrm_membership']}.id";
  }

  function orderBy() {
    $this->_orderBy = " ORDER BY civicrm_membership_membership_dues DESC";
    //error_log($this->_select . ' ' . $this->_from . ' ' . $this->_where . ' ' . $this->_groupBy . ' ' . $this->_orderBy);
  }

  function postProcess() {
    $this->beginPostProcess();

    // get the acl clauses built before we assemble the query
    $this->buildACLClause($this->_aliases['civicrm_contact']);
    $sql = $this->buildQuery(TRUE);

    $rows = array();
    $this->buildRows($sql, $rows);

    $this->formatDisplay($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }



  function alterDisplay(&$rows) {
    $check_period = $this->_params['check_period'];
    $check_period_seconds = strtotime($check_period) - strtotime('now');

    // custom code to alter rows
    $entryFound = FALSE;
    $checkList = array();
    foreach ($rows as $rowNum => $row) {
      // adjust accumulated contributions by time factor
      $membership_age = strtotime('now') - strtotime($row['civicrm_membership_start_date']);
      if ($membership_age < $check_period_seconds) {
        // this membership is younger than the check period
        $period_factor = strtotime('1 year') / $membership_age;
      } else {
        $period_factor = $this->period_factors[$check_period];
      }

      // treat missing entries as zero
      if (empty($row['civicrm_membership_membership_dues'])) {
        $rows[$rowNum]['civicrm_membership_membership_dues'] = '0.00';
      }
      if (empty($row['civicrm_contribution_total_amount'])) {
        $rows[$rowNum]['civicrm_contribution_total_amount'] = '0.00';
      }

      // calculate membership dues (currenty, membership_dues only contains the annual fee)
      $rows[$rowNum]['civicrm_membership_membership_dues'] = sprintf("%01.2f", 
        ($rows[$rowNum]['civicrm_membership_membership_dues'] / $period_factor) - $row['civicrm_contribution_total_amount']);

      // filter out zero or negtive debts
      //  reason: depends on the SUM (i.e. multiple lines), subquery needed
      if ($rows[$rowNum]['civicrm_membership_membership_dues'] <= 0) {
        unset($rows[$rowNum]);
        continue;
      }
      
      if (!empty($this->_noRepeats) && $this->_outputMode != 'csv') {
        // not repeat contact display names if it matches with the one
        // in previous row
        $repeatFound = FALSE;
        foreach ($row as $colName => $colVal) {
          if (CRM_Utils_Array::value($colName, $checkList) &&
            is_array($checkList[$colName]) &&
            in_array($colVal, $checkList[$colName])
          ) {
            $rows[$rowNum][$colName] = "";
            $repeatFound = TRUE;
          }
          if (in_array($colName, $this->_noRepeats)) {
            $checkList[$colName][] = $colVal;
          }
        }
      }

      if (array_key_exists('civicrm_membership_membership_type_id', $row)) {
        if ($value = $row['civicrm_membership_membership_type_id']) {
          // write the type name instead of the ID
          $membershipTypeField = CRM_Member_PseudoConstant::membershipType($value, FALSE);

          // also link it to the membership view
          $url = CRM_Utils_System::url("civicrm/contact/view/membership", "action=view&reset=1&cid={$row['civicrm_contact_id']}&id={$row['civicrm_membership_membership_id']}");
          $membershipTypeField = "<a href='$url'>" . $membershipTypeField . "</a>";
          $rows[$rowNum]['civicrm_membership_membership_type_id'] = $membershipTypeField;
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_contact_sort_name', $row) &&
        $rows[$rowNum]['civicrm_contact_sort_name'] &&
        array_key_exists('civicrm_contact_id', $row)
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view",
          'reset=1&cid=' . $row['civicrm_contact_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_contact_sort_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_sort_name_hover'] = ts("View Contact Summary for this Contact.");
        $entryFound = TRUE;
      }

      if (!$entryFound) {
        break;
      }
    }
  }
}
