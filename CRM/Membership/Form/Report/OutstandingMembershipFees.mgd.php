<?php
/*-------------------------------------------------------+
| Project 60 - Membership Extension                      |
| Copyright (C) 2013-2015 SYSTOPIA                       |
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


// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 => 
  array (
    'name' => 'CRM_Membership_Form_Report_OutstandingMembershipFees',
    'entity' => 'ReportTemplate',
    'params' => 
    array (
      'version' => 3,
      'label' => ts('Outstanding Membership Fees'),
      'description' => ts('Will identify memberships that are behind on paying their membersip fees (org.project60.membership)'),
      'class_name' => 'CRM_Membership_Form_Report_OutstandingMembershipFees',
      'report_url' => 'org.project60.membership/outstandingmembershipfees',
      'component' => 'CiviMember',
    ),
  ),
);