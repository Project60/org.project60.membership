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

<div class="crm-summary-row">
    <div class="crm-label">{ts}Membership Nr.{/ts}</div>
    <div class="crm-content crm-contact_membership_number_label">{$membership_number_string}</div>
</div>

<script type="text/javascript">


// move label to the right place
cj("div.crm-contact_external_identifier_label")
    .parent()
    .parent()
    .append(cj("div.crm-contact_membership_number_label").parent());
</script>