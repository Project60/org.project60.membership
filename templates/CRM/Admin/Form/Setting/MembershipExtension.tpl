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
    {ts domain="org.project60.membership"}General Settings{/ts}
  </div>
  <div class="crm-accordion-body">
     <div class="crm-block crm-form-block crm-form-title-here-form-block">
       {ts domain="org.project60.membership"}General Options{/ts}
     </div>
   </div>
</div-->
<div class="crm-accordion-wrapper" id="general">
  <div class="crm-accordion-header">
    {ts domain="org.project60.membership"}Membership Number Integration{/ts}
  </div>
  <div class="crm-accordion-body">
    <div class="crm-block crm-form-block crm-form-title-here-form-block">
      <h3>{ts domain="org.project60.membership"}Membership Number Integration{/ts}</h3>
      <table>
        <tr>
          <td>{$form.membership_number_field.label}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}Membership Number Field{/ts}", {literal}{"id":"id-number","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership"}Help{/ts}" class="helpicon"></a></td>
          <td>{$form.membership_number_field.html}</td>
        </tr>
        <tr>
          <td>{$form.membership_number_show.label}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}Show Number in SummaryView{/ts}", {literal}{"id":"id-number-show","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership"}Help{/ts}" class="helpicon"></a></td>
          <td>{$form.membership_number_show.html}</td>
        </tr>
        <tr>
          <td>{$form.membership_number_generator.label}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}Generator Pattern{/ts}", {literal}{"id":"id-number-generator","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership"}Help{/ts}" class="helpicon"></a></td>
          <td>{$form.membership_number_generator.html}</td>
        </tr>
      </table>
    </div>
  </div>
</div>


<div class="crm-accordion-wrapper" id="general">
  <div class="crm-accordion-header">
    {ts domain="org.project60.membership"}Membership Cancellation{/ts}
  </div>
  <div class="crm-accordion-body">
    <div class="crm-block crm-form-block crm-form-title-here-form-block">
      <h3>{ts domain="org.project60.membership"}Membership Cancellation{/ts}</h3>
      <table>
        <tr>
          <td>{$form.membership_cancellation_date_field.label}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}Cancel Date Field{/ts}", {literal}{"id":"id-cancel-date","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership"}Help{/ts}" class="helpicon"></a></td>
          <td>{$form.membership_cancellation_date_field.html}</td>
        </tr>
        <tr>
          <td>{$form.membership_cancellation_reason_field.label}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}Cancel Reason Field{/ts}", {literal}{"id":"id-cancel-reason","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership"}Help{/ts}" class="helpicon"></a></td>
          <td>{$form.membership_cancellation_reason_field.html}</td>
        </tr>
      </table>
    </div>
  </div>
</div>

<div class="crm-accordion-wrapper" id="general">
  <div class="crm-accordion-header">
    {ts domain="org.project60.membership"}Payment Integration{/ts}
  </div>
  <div class="crm-accordion-body">
     <div class="crm-block crm-form-block crm-form-title-here-form-block">
       <h3>{ts domain="org.project60.membership"}Payment Integration{/ts}</h3>
       <table>
         <tr>
           <td>{$form.paid_via_field.label}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}Paid Via Field{/ts}", {literal}{"id":"id-paid-via","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership"}Help{/ts}" class="helpicon"></a></td>
           <td>{$form.paid_via_field.html}</td>
         </tr>
         <tr class="p60-paid-via-dependent">
           <td>{$form.annual_amount_field.label}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}Annual Amount Field{/ts}", {literal}{"id":"id-annual-amount-field","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership"}Help{/ts}" class="helpicon"></a></td>
           <td>{$form.annual_amount_field.html}</td>
         </tr>
         <tr class="p60-record-fee-updates">
           <td>{$form.record_fee_updates.label}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}Record Fee Updates{/ts}", {literal}{"id":"id-record-fee-updates","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership"}Help{/ts}" class="helpicon"></a></td>
           <td>{$form.record_fee_updates.html}</td>
         </tr>
         <tr>
           <td>{$form.paid_via_end_with_status.label}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}End with status{/ts}", {literal}{"id":"id-paid-via-end-status","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership"}Help{/ts}" class="helpicon"></a></td>
           <td>{$form.paid_via_end_with_status.html}</td>
         </tr>
         <tr>
           <td>{$form.hide_auto_renewal.label}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}Hide Auto Renewal{/ts}", {literal}{"id":"id-hide-auto-renewal","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership"}Help{/ts}" class="helpicon"></a></td>
           <td>{$form.hide_auto_renewal.html}</td>
         </tr>
         <tr>
           <td>{$form.update_membership_status.label}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}Update membership status and end date{/ts}", {literal}{"id":"update-membership-status","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership"}Help{/ts}" class="helpicon"></a></td>
           <td>{$form.update_membership_status.html}</td>
         </tr>
       </table>
       <br/>
     </div>

     <div class="crm-block crm-form-block crm-form-title-here-form-block">
       <h3>{ts domain="org.project60.membership"}Derived Fields{/ts}</h3>
       <table>
         <tr class="p60-paid-via-dependent">
           <td>{$form.installment_amount_field.label}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}Installment Amount Field{/ts}", {literal}{"id":"id-installment-amount-field","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership"}Help{/ts}" class="helpicon"></a></td>
           <td>{$form.installment_amount_field.html}</td>
         </tr>
         <tr class="p60-paid-via-dependent">
           <td>{$form.diff_amount_field.label}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}Annual Gap Field{/ts}", {literal}{"id":"id-annual-gap-field","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership"}Help{/ts}" class="helpicon"></a></td>
           <td>{$form.diff_amount_field.html}</td>
         </tr>
         <tr class="p60-paid-via-dependent">
           <td>{$form.payment_frequency_field.label}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}Payment Frequency Field{/ts}", {literal}{"id":"id-payment-frequency-field","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership"}Help{/ts}" class="helpicon"></a></td>
           <td>{$form.payment_frequency_field.html}</td>
         </tr>
         <tr class="p60-paid-via-dependent">
           <td>{$form.payment_type_field.label}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}Payment Type Field{/ts}", {literal}{"id":"id-payment-type-field","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership"}Help{/ts}" class="helpicon"></a></td>
           <td>{$form.payment_type_field.html}</td>
         </tr>
         <tr class="p60-paid-via-dependent p60-payment-type-dependent">
           <td>{$form.payment_type_field_mapping.label}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}Payment Type Field Mapping{/ts}", {literal}{"id":"id-payment-type-field-mapping","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership"}Help{/ts}" class="helpicon"></a></td>
           <td>{$form.payment_type_field_mapping.html}</td>
         </tr>
         <tr class="p60-paid-via-dependent p60-payment-type-dependent">
           <td>{$form.synchronise_payment_now.label}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}Update Derived Fields on Save{/ts}", {literal}{"id":"id-update-now","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership"}Help{/ts}" class="helpicon"></a></td>
           <td>{$form.synchronise_payment_now.html}</td>
         </tr>
       </table>
       <br/>
     </div>
   </div>
</div>



<div class="crm-accordion-wrapper" id="synchronization">
  <div class="crm-accordion-header">
    {ts domain="org.project60.membership"}Payment Synchronisation Tool{/ts}
  </div>
  <div class="crm-accordion-body">
    <div class="crm-block crm-form-block crm-form-title-here-form-block">
      <h3>{ts domain="org.project60.membership"}General{/ts}</h3>
      <table>
        <tr>
          <td>{$form.sync_range.label}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}Backward Horizon{/ts}", {literal}{"id":"id-sync-range","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership"}Help{/ts}" class="helpicon"></a></td>
          <td>{$form.sync_range.html}</td>
        </tr>
        <tr>
          <td>{$form.grace_period.label}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}Forward Horizon{/ts}", {literal}{"id":"id-grace-period","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership""}Help{/ts}" class="helpicon"></a></td>
          <td>{$form.grace_period.html}</td>
        </tr>
         <tr>
           <td>{$form.paid_by_field.label}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}Paid By Field{/ts}", {literal}{"id":"id-paid-by","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership""}Help{/ts}" class="helpicon"></a></td>
           <td>{$form.paid_by_field.html}</td>
         </tr>
      </table>
      <br/>
    </div>

    <div class="crm-block crm-form-block crm-form-title-here-form-block">
      <h3>{ts domain="org.project60.membership"}Membership Status{/ts}</h3>
      <table>
        <tr>
          <td>{$form.live_statuses.label}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}Live Status{/ts}", {literal}{"id":"id-live-status","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership""}Help{/ts}" class="helpicon"></a></td>
          <td>{$form.live_statuses.html}</td>
        </tr>
      </table>
      <br/>
    </div>

    <div class="crm-block crm-form-block crm-form-title-here-form-block">
      <h3>{ts domain="org.project60.membership""}Financial Type Mapping{/ts}&nbsp;<a onclick='CRM.help("{ts domain="org.project60.membership"}Financial Type Mapping{/ts}", {literal}{"id":"id-mapping","file":"CRM\/Admin\/Form\/Setting\/MembershipExtension"}{/literal}); return false;' href="#" title="{ts domain="org.project60.membership""}Help{/ts}" class="helpicon">&nbsp;</a></h3>
      <table>
{foreach from=$financial_types item=financial_type_name key=financial_type_id}
        {capture assign=itemid}syncmap_{$financial_type_id}{/capture}
        <tr>
          <td>{$form.$itemid.label}</td>
          <td id="syncmap_item">{$form.$itemid.html}</td>
        </tr>
{/foreach}
      </table>
      <br/>
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

// visualise dependecies
cj(document).ready(function() {
    cj("#paid_via_field").change(function() {
        var paid_via =cj("#paid_via_field").val();
        if (paid_via) {
            cj("tr.p60-paid-via-dependent").show(200);
        } else {
            cj("tr.p60-paid-via-dependent").hide(200);
        }
    });

    cj("#payment_type_field").change(function() {
        var payment_type_field =cj("#payment_type_field").val();
        if (payment_type_field) {
            cj("tr.p60-payment-type-dependent").show(200);
        } else {
            cj("tr.p60-payment-type-dependent").hide(200);
        }
    });

    cj("#payment_type_field").change();
    cj("#paid_via_field").change();
});



</script>
{/literal}
