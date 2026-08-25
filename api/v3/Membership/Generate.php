<?php
/*-------------------------------------------------------+
| Project 60 - Membership Extension                      |
| Copyright (C) 2019 SYSTOPIA                            |
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
declare(strict_types = 1);

/**
 * API3 action specs
 */
function _civicrm_api3_membership_generate_spec(&$params) {
  $params['generate'] = [
    'name'         => 'generate',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Generate what?',
    'description'  => 'Current options: fee_change_activities',
  ];
  $params['dry_run'] = [
    'name'         => 'dry_run',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Dry Run?',
    'description'  => 'If active, no changes will be performed',
  ];
  $params['from_date'] = [
    'name'         => 'from_date',
    'api.default'  => '',
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'From Date',
    'description'  => 'The generation will only run for events/data after this date.',
  ];
  $params['to_date'] = [
    'name'         => 'to_date',
    'api.default'  => '',
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'To Date',
    'description'  => 'The generation will only run for events/data up to this date. Default is today.',
  ];
  $params['limit'] = [
    'name'         => 'limit',
    'api.default'  => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Processing Limit',
    'description'  => 'If given, only this amount of records will be created',
  ];
  $params['extra'] = [
    'name'         => 'extra',
    'api.default'  => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Extra Parameters',
    'description'  => 'Additional parameters for the given generator',
  ];
}

/**
 * The Membership.generate API can generate various aspects
 */
function civicrm_api3_membership_generate($params) {
  // compile dates
  if (!isset($params['from_date']) || $params['from_date'] === '' || $params['from_date'] === '0') {
    $from_date = NULL;
  }
  else {
    $from_date = date('Y-m-d', strtotime($params['from_date']));
  }
  if (!isset($params['to_date']) || $params['to_date'] === '' || $params['to_date'] === '0') {
    $to_date = date('Y-m-d');
  }
  else {
    $to_date = date('Y-m-d', strtotime($params['to_date']));
  }

  // make sure there is a contact ID set
  $userContactID = CRM_Core_Session::getLoggedInContactID();
  if ((int) $userContactID === 0) {
    // TODO: have a fallback setting for user
    $session = CRM_Core_Session::singleton();
    $session->set('userID', 47218);
  }

  // compile parameters
  $parameters = [
    'dry_run' => isset($params['dry_run']) && (int) $params['dry_run'] !== 0,
    'limit'   => $params['limit'],
  ];
  if (isset($params['extra']) && $params['extra'] !== '' && $params['extra'] !== '0') {
    // first: try json
    $extra = json_decode($params['extra'], TRUE);
    if ($extra !== NULL && $extra !== FALSE && $extra !== 0 && $extra !== 0.0
      && $extra !== '' && $extra !== '0' && $extra !== []) {
      $parameters = array_merge($parameters, $extra);
    }
    else {
      // try parsing cs xx=yy pairs
      $parts = explode(',', $params['extra']);
      foreach ($parts as $part) {
        if (strpos($part, '=') !== FALSE) {
          // this should be a xx=yy pair
          list($key, $value) = explode('=', $part, 2);
          $parameters[$key] = $value;
        }
        else {
          // no '='? we'll interpret that as a flag
          $parameters[$part] = 1;
        }
      }
    }
  }

  // decide what to do
  switch ($params['generate']) {
    case 'fee_change_activities':
      return CRM_Membership_Generator::generateFeeUpdateActivities($from_date, $to_date, $parameters);

    default:
      return civicrm_api3_create_error("Unknown generator: '{$params['generate']}'");
  }
}
