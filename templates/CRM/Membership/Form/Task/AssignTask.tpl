{*-------------------------------------------------------+
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
+-------------------------------------------------------*}

<table>
  <tr>
    <h3>{ts 1=$contribution_count}Assigning %1 Contributions:{/ts}</h3>
  </tr>
  <tr>
    <td class="label">
      {$form.contact.label}&nbsp;<a onclick='CRM.help("{ts}Contact Search{/ts}", {literal}{"id":"id-contact","file":"CRM\/Membership\/Form\/Task\/AssignTask"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon"></a>
    </td>
    <td>
      {$form.contact.html}
      {include file="CRM/Custom/Form/ContactReference.tpl" element_name="contact"}
    </td>
  </tr>
  <tr>
    <td class="label">
      {$form.membership.label}
    </td>
    <td>
      {$form.membership.html}
    </td>
  </tr>
  <tr>
    <td></td>
    <td>
      <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl" location="bottom"}
      </div>
    </td>
  </tr>
</table>


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
