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
<table>
  <tr id="p60paidby_row">
    <td class="label">{$p60paidby_label}</td>
    <td class="html-adjust">
      {$p60paidby_current}
      {if $p60paidby_edit}
      {/if}
      {if $p60paidby_remove}
      {/if}
    </td>
  </tr>
</table>

{literal}
<script type="text/javascript">
// move the snippet above to the right place
cj(document).ready(function() {
  cj("#MembershipView")
    .find("tr td.label:contains('SUPERLINKE')")
    .parent()
    .before(cj("tr[id=p60paidby_row]"))
    .remove();
});
{/literal}

</script>

<!--
<script type="text/javascript">
var membership_types    = {$membership_types};
var membership_statuses = {$membership_statuses};

var default_contact_id    = {$default_contact_id};
var default_contact_label = "{$default_contact_label}";

{literal}

cj("#contact").change(function() {
  // remove all old options
  cj("[name=membership] option").remove();

  // load memberships of new contact
  var contact_id = cj("#contact").val();

  CRM.api3('Membership', 'get', {
    "contact_id": contact_id

  }).done(function(result) {
    // do something
    for (membership_id in result.values) {
      var membership = result.values[membership_id];
      var label = "[" + membership['id'] + "] ";
      label += membership_types[membership['membership_type_id']];
      label += " (" + membership_statuses[membership['status_id']] + "): ";
      label += membership['start_date'] + " - " + membership['end_date'];

      // add as an option
      cj("[name=membership]").append(new Option(label, membership_id, true, true));
      cj("[name=membership]").select2('val', membership_id);
    }
  });
});

// set the default contact (if any)
cj(document).ready(function() {
  if (default_contact_id) {
    cj("#s2id_contact a").prepend('<span id="select2-chosen-2" class="select2-chosen">' + default_contact_label + '</span>');
    cj("[name=contact]").append(new Option(default_contact_label, default_contact_id, true, true));
    cj("[name=contact]").select2('val', default_contact_id).trigger('change');
  }
});

{/literal}
</script>
 -->