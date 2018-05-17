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
 * API command: ContributionRecur.render
 *
 * behaves in the same way as ContributionRecur.get, but
 * will add some extra parameters
 */
function civicrm_api3_contribution_recur_render($params) {
  $recurring_contributions = civicrm_api3('ContributionRecur', 'get', $params);
  $logic = CRM_Membership_PaidByLogic::getSingleton();
  foreach ($recurring_contributions['values'] as &$recurring_contribution) {
    $logic->renderRecurringContribution($recurring_contribution);
  }
  return $recurring_contributions;
}
