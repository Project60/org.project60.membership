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

{$form.selected_contribution_rcur_id.html}
{$form.membership_id.html}

<table class="selector row-highlight p60-paid-via">
  <thead>
    <tr class="sticky">
      <th>&nbsp;</th>
      <th>{ts}ID{/ts}</th>
      <th>{ts}Annual{/ts}</th>
      <th>{ts}Payment Mode{/ts}</th>
      <th>{ts}Finacial Type{/ts}</th>
      <th>{ts}Paid By{/ts}</th>
      <th>{ts}status{/ts}</th>
    </tr>
  </thead>
  <tbody id='p60_paid_via_options'>
    <tr class="p60-paid-via-row p60-paid-via-row-eligible sticky" id="p60_paid_via_0">
      <td><img class="p60-paid-via-checkmark" src="{$config->resourceBase}i/check.gif" alt="{ts}Selected{/ts}"/></td>
      <td>[0]</td>
      <td>{0.00|crmMoney}</td>
      <td colspan="4">{ts}manual{/ts}</td>
    </tr>
  </tbody>
</table>


<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

<script type="text/javascript">
var owner_id  = "{$membership.contact_id}";
var paid_by   = "{$membership.paid_by}"; // TODO
var checkmark = '<img class="p60-paid-via-checkmark" src="{$config->resourceBase}i/check.gif" alt="{ts}Selected{/ts}"/>';

{literal}
function p60m_updateSelection() {
  // remove selected tag
  cj("table.p60-paid-via")
    .find("tr.p60-paid-via-row-selected")
    .removeClass("p60-paid-via-row-selected");

  // hide all checkmarks
  cj("table.p60-paid-via")
    .find("img.p60-paid-via-checkmark")
    .hide();

  // select current
  var current = cj("input[name=selected_contribution_rcur_id]").val();
  if (current =='') current = 0;
  cj("tr[id=p60_paid_via_" + current + "]")
    .addClass("p60-paid-via-row-selected")
    .find("img.p60-paid-via-checkmark")
    .show();
}

function addRecurring(contact_id) {
  CRM.api3('ContributionRecur', 'render', {
    'sequential': 1,
    'contact_id': contact_id
  }).done(function(result) {
    for (var i in result.values) {
      // add every one to the list
      var rcont = result.values[i];
      cj("#p60_paid_via_options").append('\
      <tr id="p60_paid_via_' + rcont.id + '" class="p60-paid-via-row ' + rcont.classes + '">\
        <td>' + checkmark + '</td>\
        <td>[' + rcont.id + ']</td>\
        <td>' + rcont.display_annual + '</td>\
        <td>' + rcont.display_cycle + '</td>\
        <td>' + rcont.financial_type + '</td>\
        <td><span class="icon crm-icon ' + rcont.contact.contact_type + '-icon"></span>' + rcont.contact.display_name + '</td>\
        <td>' + rcont.display_status + '</td>\
      </tr>');
    }

    p60m_updateSelection();
  });
}

// trigger with owner and paid_by
cj(document).ready(function() {
  // select default
  p60m_updateSelection();

  // add click handler
  cj("table.p60-paid-via").click(function(e) {
    // tag row
    var row = cj(e.target).closest("tr.p60-paid-via-row");
    if (row.hasClass("p60-paid-via-row-eligible")) {
      // remove selected tag from other row
      cj("table.p60-paid-via")
        .find("tr.p60-paid-via-row-selected")
        .removeClass("p60-paid-via-row-selected");

      // now add it to the new one
      row.addClass("p60-paid-via-row-selected");
      var rcur_id = row.attr('id').substr(13);

      // update ID field
      cj("input[name=selected_contribution_rcur_id]").val(rcur_id);
      p60m_updateSelection();

    } else {
      // create a a little "not selectable" animation
      row.addClass("p60-paid-via-row-error")
         .delay(300)
         .queue(function(next) {
            row.removeClass("p60-paid-via-row-error");
            next();})
         .delay(300)
         .queue(function(next) {
            row.addClass("p60-paid-via-row-error");
            next();})
         .delay(300)
         .queue(function(next) {
            row.removeClass("p60-paid-via-row-error");
            next();});
    }
  });

  // pull recurring contributions from membership owner
  addRecurring(owner_id);
});

if (paid_by.length > 0 && owner_id != paid_by) {
  // pull recurring contributions from membership 'paid by' field
  cj(document).ready(addRecurring(paid_by));
}

{/literal}
</script>