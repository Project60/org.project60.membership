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

<!--div class="crm-accordion-wrapper" id="general">
  <div class="crm-accordion-header">
    {ts}General Settings{/ts}
  </div>
  <div class="crm-accordion-body">
     <div class="crm-block crm-form-block crm-form-title-here-form-block">
       {ts}General Options{/ts}
     </div>
   </div>
</div-->

<div class="crm-accordion-wrapper" id="general">
  <div class="crm-accordion-header">
    {ts}Membership Number Integration{/ts}
  </div>
  <div class="crm-accordion-body">
    <div class="crm-block crm-form-block crm-form-title-here-form-block">
      <h3>{ts}Membership Number Integration{/ts}</h3>
      <table>
        <tr>
          <td>{$form.membership_number_field.label}&nbsp;<a onclick='CRM.help("{ts}Membership Number Field{/ts}", {literal}{"id":"id-number","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon"></a></td>
          <td>{$form.membership_number_field.html}</td>
        </tr>
        <tr>
          <td>{$form.membership_number_generator.label}&nbsp;<a onclick='CRM.help("{ts}Generator Pattern{/ts}", {literal}{"id":"id-number-generator","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon"></a></td>
          <td>{$form.membership_number_generator.html}</td>
        </tr>
      </table>
    </div>
  </div>
</div>

<div class="crm-accordion-wrapper" id="general">
  <div class="crm-accordion-header">
    {ts}Payment Integration{/ts}
  </div>
  <div class="crm-accordion-body">
     <div class="crm-block crm-form-block crm-form-title-here-form-block">
       <h3>{ts}Payment Integration{/ts}</h3>
       <table>
         <tr>
           <td>{$form.paid_via_field.label}&nbsp;<a onclick='CRM.help("{ts}Paid Via Field{/ts}", {literal}{"id":"id-paid-via","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon"></a></td>
           <td>{$form.paid_via_field.html}</td>
         </tr>
         <tr>
           <td>{$form.paid_via_linked.label}&nbsp;<a onclick='CRM.help("{ts}Paid Via Linked{/ts}", {literal}{"id":"id-paid-via-linked","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon"></a></td>
           <td>{$form.paid_via_linked.html}</td>
         </tr>
       </table>
     </div>
   </div>
</div>



<div class="crm-accordion-wrapper" id="synchronization">
  <div class="crm-accordion-header">
    {ts}Payment Synchronisation Tool{/ts}
  </div>
  <div class="crm-accordion-body">
    <div class="crm-block crm-form-block crm-form-title-here-form-block">
      <h3>{ts}General{/ts}</h3>
      <table>
        <tr>
          <td>{$form.sync_range.label}&nbsp;<a onclick='CRM.help("{ts}Backward Horizon{/ts}", {literal}{"id":"id-sync-range","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon"></a></td>
          <td>{$form.sync_range.html}</td>
        </tr>
        <tr>
          <td>{$form.grace_period.label}&nbsp;<a onclick='CRM.help("{ts}Forward Horizon{/ts}", {literal}{"id":"id-grace-period","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon"></a></td>
          <td>{$form.grace_period.html}</td>
        </tr>
         <tr>
           <td>{$form.paid_by_field.label}&nbsp;<a onclick='CRM.help("{ts}Paid By Field{/ts}", {literal}{"id":"id-paid-by","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon"></a></td>
           <td>{$form.paid_by_field.html}</td>
         </tr>
      </table>
    </div>

    <div class="crm-block crm-form-block crm-form-title-here-form-block">
      <h3>{ts}Membership Status{/ts}</h3>
      <table>
        <tr>
          <td>{$form.live_statuses.label}&nbsp;<a onclick='CRM.help("{ts}Live Status{/ts}", {literal}{"id":"id-live-status","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon"></a></td>
          <td>{$form.live_statuses.html}</td>
        </tr>
      </table>
    </div>

    <div class="crm-block crm-form-block crm-form-title-here-form-block">
      <h3>{ts}Financial Type Mapping{/ts}&nbsp;<a onclick='CRM.help("{ts}Financial Type Mapping{/ts}", {literal}{"id":"id-mapping","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></h3>
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
