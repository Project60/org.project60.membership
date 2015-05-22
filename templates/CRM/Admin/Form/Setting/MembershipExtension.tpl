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

<div class="crm-accordion-wrapper" id="synchronization">
  <div class="crm-accordion-header">
    {ts}General Settings{/ts}
  </div>
  <div class="crm-accordion-body">
     <div class="crm-block crm-form-block crm-form-title-here-form-block">
       {ts}General Options{/ts}
     </div>
   </div>
</div>

<div class="crm-accordion-wrapper collapsed" id="synchronization">
  <div class="crm-accordion-header">
    {ts}Payment Synchronisation Tool{/ts}
  </div>
  <div class="crm-accordion-body">
    <div class="crm-block crm-form-block crm-form-title-here-form-block">
      <h3>{ts}Time Horizon{/ts} <a onclick='CRM.help("{ts}Time Horizon{/ts}", {literal}{"id":"id-sync-range","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></h3>
      <table>
        <tr>
          <td>{$form.sync_range.label}</td>
          <td>{$form.sync_range.html}</td>
        </tr>
        <tr>
          <td>{$form.grace_period.label}</td>
          <td>{$form.grace_period.html}</td>
        </tr>
      </table>
    </div>
    <div class="crm-block crm-form-block crm-form-title-here-form-block">
      <h3>{ts}Financial Type Mapping{/ts} <a onclick='CRM.help("{ts}Financial Type Mapping{/ts}", {literal}{"id":"id-sync-range","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></h3>
      <table>
{foreach from=$financial_types item=financial_type_name key=financial_type_id}
        {capture assign=itemid}syncmap_{$financial_type_id}{/capture}
        <tr>
          <td>{$form.$itemid.label}</td>
          <td id="syncmap_item">{$form.$itemid.html}</td>
        </tr>
{/foreach}
      </table>
    </div>
  </div>
</div>

<div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div> 


<script type="text/javascript">
var syncmap_values = {$sync_mapping};
{literal}

// set the current values
var items = cj("#syncmap_item > select");
for (var i = 0; i < items.length; i++) {
  var item_id = parseInt(items[i].id.substr(8));
  if (syncmap_values[item_id]) {
    cj(items[i]).val(syncmap_values[item_id]);
  } else {
    cj(items[i]).val(0);
  }
};

// activate accordion code
cj().crmAccordions();
</script>
{/literal}
