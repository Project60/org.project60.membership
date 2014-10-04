{*-------------------------------------------------------+
| Project 60 - Membership Extension                      |
| Copyright (C) 2013-2014 SYSTOPIA                       |
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
       TODO

     </div>
   </div>
</div>

<div class="crm-accordion-wrapper coll apsed" id="synchronization">
  <div class="crm-accordion-header">
    {ts}Payment Synchronisation Tool{/ts}
  </div>
  <div class="crm-accordion-body">
    <div class="crm-block crm-form-block crm-form-title-here-form-block">
      <h3>{ts}Financial Type Mapping{/ts} <a onclick='CRM.help("{ts}Creditor Contact{/ts}", {literal}{"id":"id-contact","file":"CRM\/Admin\/Form\/Setting\/SepaSettings"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a></h3>
      <table>
{foreach from=$membership_types item=membership_name key=membership_id}
        {capture assign=itemid}syncmap_{$membership_id}{/capture}
        <tr>
          <td>{$form.$itemid.label}</td>
          <td>{$form.$itemid.html}</td>
        </tr>
{/foreach}
      </table>
    </div>
  </div>
</div>

{literal}
<script type="text/javascript">
  cj().crmAccordions();
</script>
{/literal}
