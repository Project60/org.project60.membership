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

<form method="post" name="Control" id="Control" enctype="multipart/form-data" >
	<input type="hidden" name="run" />

	<div class="crm-block crm-form-block crm-basic-criteria-form-block">
	<div class="crm-accordion-wrapper crm-case_search-accordion collapsed">

		<div class="crm-accordion-header crm-master-accordion-header">{ts}Synchronization Options{/ts}</div>
		
		<div style="display: none;" class="crm-accordion-body">
			
			<div class="crm-section">
				<div class="label"><label for="rebuild">{ts}Rebuild Mapping{/ts}</label>
				<a onclick='CRM.help("{ts}Rebuild Mapping{/ts}", {literal}{"id":"id-rebuild","file":"CRM\/Membership\/Page\/MembershipPayments"}{/literal}); return false;' href="#" title="{ts}Help{/ts}" class="helpicon">&nbsp;</a>
				</div>
				<div class="content"><input type="checkbox" value="1" name="rebuild" id="rebuild" {if $smarty.request.rebuild}checked{/if}></div>
				<div class="clear"></div>
			</div>
      <div class="crm-section">
        {capture assign=settingsURL}{crmURL p='civicrm/admin/setting/membership' q="reset=1"}{/capture}
        <p>{ts 1=$settingsURL}Find more settings the payment synchronisation <a href="%1">here</a>.{/ts}</p>
      </div>
		</div>
	</div>
	</div>

	<span class="crm-button crm-button-type-upload">
	<input type="submit" value="{ts}Synchronize{/ts}" class="validate form-submit default">
	</span>
</form>


{if $executed}
<br/>
<br/>
<br/>

{if $mapped}
<h2>{ts}Results of this run{/ts}</h2>
{if $mapped|@count eq 500}
<h3>&gt;500(!) {ts}contributions have been newly assigned to memberships{/ts}</h3>
{else}
<h3>{$mapped|@count} {ts}contributions have been newly assigned to memberships{/ts}</h3>
{/if}
<div>
<table>
<thead>
	<tr>
		<th class="ui-state-default">
			<div class="DataTables_wrapper">{ts}Contribution{/ts}</div>
		</th>
		<th class="ui-state-default">
			<div class="DataTables_wrapper">{ts}Date{/ts}</div>
		</th>
		<th class="ui-state-default">
			<div class="DataTables_wrapper">{ts}Contributor{/ts}</div>
		</th>
		<th class="ui-state-default">	
		</th>
	</tr>
</thead>
<tbody>
  {foreach from=$mapped item=row}
  <tr class="{cycle values="odd,even"}">
    <td><a href="{$row.contribution_link}">{$row.contribution_amount} {$row.contribution_type}</a></td>
    <td><a href="{$row.contribution_link}">{$row.contribution_date}</a></td>
    <td><a href="{$row.contact_link}"><div class="icon crm-icon {$row.contact_type}-icon"></div>{$row.contact_name}</a></td>
	<td><a href="{$row.membership_link}">{ts}view membership{/ts}</a></td>
  </tr>
  {/foreach}
</tbody>
</table>
</div>
{else}
<h3>0 {ts}contributions have been newly assigned to memberships{/ts}</h3>
{/if}

<br/>
<br/>

<h2>{ts}The following contributions could not be assigned:{/ts}</h2>
{if $no_membership}
{if $no_membership|@count eq 500}
<h3>&gt;500(!) {ts}contributions with no active membership{/ts}</h3>
{else}
<h3>{$no_membership|@count} {ts}contributions with no active membership{/ts}</h3>
{/if}

<div>
<table>
<thead>
	<tr>
		<th class="ui-state-default">
			<div class="DataTables_wrapper">{ts}Contribution{/ts}</div>
		</th>
		<th class="ui-state-default">
			<div class="DataTables_wrapper">{ts}Date{/ts}</div>
		</th>
		<th class="ui-state-default">
			<div class="DataTables_wrapper">{ts}Contributor{/ts}</div>
		</th>
	</tr>
</thead>
<tbody>
  {foreach from=$no_membership item=row}
  <tr class="{cycle values="odd,even"}">
    <td><a href="{$row.contribution_link}">{$row.contribution_amount} {$row.contribution_type}</a></td>
    <td><a href="{$row.contribution_link}">{$row.contribution_date}</a></td>
    <td><a href="{$row.contact_link}"><div class="icon crm-icon {$row.contact_type}-icon"></div>{$row.contact_name}</a></td>
  </tr>
  {/foreach}
</tbody>
</table>
</div>
{else}
<h3>0 {ts}contributions with no active membership{/ts}</h3>
{/if}

{if $ambiguous}
{if $ambiguous|@count eq 500}
<h3>&gt;500(!) {ts}contributions with ambiguous memberships{/ts}</h3>
{else}
<h3>{$ambiguous|@count} {ts}contributions with ambiguous memberships{/ts}</h3>
{/if}

<div>
<table>
<thead>
	<tr>
		<th class="ui-state-default">
			<div class="DataTables_wrapper">{ts}Contribution{/ts}</div>
		</th>
		<th class="ui-state-default">
			<div class="DataTables_wrapper">{ts}Date{/ts}</div>
		</th>
		<th class="ui-state-default">
			<div class="DataTables_wrapper">{ts}Contributor{/ts}</div>
		</th>
	</tr>
</thead>
<tbody>
  {foreach from=$ambiguous item=row}
  <tr class="{cycle values="odd,even"}">
    <td><a href="{$row.contribution_link}">{$row.contribution_amount} {$row.contribution_type}</a></td>
    <td><a href="{$row.contribution_link}">{$row.contribution_date}</a></td>
    <td><a href="{$row.contact_link}"><div class="icon crm-icon {$row.contact_type}-icon"></div>{$row.contact_name}</a></td>
  </tr>
  {/foreach}
</tbody>
</table>
</div>
{else}
<h3>0 {ts}contributions with ambiguous memberships{/ts}</h3>
{/if}


{/if}



{literal}
<script type="text/javascript">
cj(function() {
   cj().crmAccordions();
});

cj("#adjust").change(function() {
	if (cj("#adjust").attr('checked')) {
		cj("#rangeback").parent().parent().show();
	} else {
		cj("#rangeback").parent().parent().hide();
	}
});

</script>
{/literal}