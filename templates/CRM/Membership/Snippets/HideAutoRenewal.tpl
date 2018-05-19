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


<script type="text/javascript">
// just hide the auto-renewal field
var auto_renew_label = "{$auto_renewal_label}";
console.log(auto_renew_label);
cj("#MembershipView")
    .find("tr td.label:contains('" + auto_renew_label + "')")
    .parent()
    .hide();
</script>