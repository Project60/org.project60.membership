{*-------------------------------------------------------+
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
+-------------------------------------------------------*}

<div id="help">
  {ts}Please select the recurring payment to be connected to this membership below.{/ts}
</div>

<table class="selector row-highlight p60-paid-via">
  <thead>
    <tr class="sticky">
      <th>&nbsp;</th>
      <th>{ts}ID{/ts}</th>
      <th>{ts}Mode{/ts}</th>
      <th>{ts}Contact{/ts}</th>
      <th>{ts}Annual{/ts}</th>
      <th>{ts}status{/ts}</th>
    </tr>
  </thead>
  <tbody id='p60_paid_via_options'>
  </tbody>
</table>


<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

<script type="text/javascript">
var owner_id = "{$membership.contact_id}";
var paid_by  = "{$membership.contact_id}"; // TODO

{literal}
function addRecurring(contact_id) {
  CRM.api3('ContributionRecur', 'render', {
    'sequential': 1,
    'contact_id': contact_id
  }).done(function(result) {
    for (var i in result.values) {
      // add every one to the list
      var rcont = result.values[i]
      cj("#p60_paid_via_options").append('\
      <tr class=" ' + rcont.class + '">\
        <td></td>\
        <td>[' + rcont.id + ']</td>\
        <td>' + rcont.display_cycle + '</td>\
        <td><span class="icon crm-icon ' + rcont.contact.contact_type + '-icon"></span>' + rcont.contact.display_name + '</td>\
        <td>' + rcont.display_annual + '</td>\
        <td>' + rcont.display_status + '</td>\
      </tr>');
    }
  });
}

// trigger with owner and paid_by
cj(document).ready(addRecurring(owner_id));

if (paid_by.length > 0 && owner_id != paid_by) {
  cj(document).ready(addRecurring(paid_by));
}

{/literal}
</script>