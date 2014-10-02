<?php

require_once 'membership.civix.php';

/**
 * Implementation of hook_civicrm_config
 */
function membership_civicrm_config(&$config) {
  _membership_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function membership_civicrm_xmlMenu(&$files) {
  _membership_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function membership_civicrm_install() {
  return _membership_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function membership_civicrm_uninstall() {
  return _membership_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function membership_civicrm_enable() {
  return _membership_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function membership_civicrm_disable() {
  return _membership_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function membership_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _membership_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function membership_civicrm_managed(&$entities) {
  return _membership_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_caseTypes
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 */
function membership_civicrm_caseTypes(&$caseTypes) {
  _membership_civix_civicrm_caseTypes($caseTypes);
}
