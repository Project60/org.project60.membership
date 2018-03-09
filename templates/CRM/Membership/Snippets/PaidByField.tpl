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
        <span>
          <a href="{$p60paidby_edit}" class="action-item crm-hover-button crm-popup medium-popup" title="{ts}change{/ts}">{ts}change{/ts}</a>
        </span>
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