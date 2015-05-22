<?php
/*-------------------------------------------------------+
| Project 60 - Membership Extension                      |
| Copyright (C) 2015 SYSTOPIA                            |
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

return array(
  'sync_mapping' => array(
    'group_name' => 'Membership Payments',
    'group' => 'org.project60',
    'name' => 'sync_mapping',
    'type' => 'String',
    'default' => "undefined",
    'add' => '4.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'membership <-> payment mappings',
    'help_text' => 'This mapping of membership_type_id to contribution_type_id will be used when synchronizing memberships with payments.',
  ),
  'sync_rangeback' => array(
    'group_name' => 'Membership Payments',
    'group' => 'org.project60',
    'name' => 'sync_rangeback',
    'type' => 'Integer',
    'default' => 400,
    'add' => '4.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'membership <-> payment time horizon',
    'help_text' => 'Defines how far back in time the algorithm will look.',
  ),
  'synce_graceperiod' => array(
    'group_name' => 'Membership Payments',
    'group' => 'org.project60',
    'name' => 'synce_graceperiod',
    'type' => 'Integer',
    'default' => 32,
    'add' => '4.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'membership <-> payment grace period',
    'help_text' => 'Defines within which period a payment will still be assigned to a running-out membership.',
  )
 );