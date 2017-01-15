Project60 Memebership Extension
===

This Extensions aims to provide tools for a "European" interpretation of the "membership" concept. In the Anglo-Saxon countries (CiviCRM's native land) a membership is regarded as a service. If you don't pay anymore, you won't get the service - that's it. In a lot of other countries the conception is different: it's more like a indeterminate binding contract.

A number of problems arise from this slightly different interpretation:

1. Memebership fees are not necessarily entered manually, or collected via a payment processor, but can enter CiviCRM by other means (e.g. import, SEPA collection, etc.)
2. Memberships should be automatically extended, when the appropriate payment is in the system.
3. You need to know, who is behind on their payments and by how much.
4. …


What does it do?
===

This extension currently (version 0.3) offers only two features:

1. Associate existing (completed) contributions with existing memberships. You will be able to configure this ``civicrm/admin/setting/membership`` and find the front-end at ``civicrm/membership/payments``. You can also call this as a scheduled job via API: ``MembershipPayment.synchronize``
2. Automatically extend rolling memeberships if an appropriate contribution has been associatend with it. This is also intended to be a scheduled job: ``Membership.extend``. Since people have all sorts of different payment rhythms and different amounts, this job has variety of parameters:
   1. ``horizon``: how far back into the past (in days) should the algorithm go, to check whether a member has "paid up"?
   2. ``precision``: how tolerant is the alogorithm wiht respect to the payment date. This is relative to the payment payment interval, so 0.7 for a monthly cycle means a tolarance of ~10 days.
   3. ``status_ids``: the IDs of the membership statuses that should be investigated. Usually this would be "1,2,3", meaning "new", "current" and "grace".
   4. ``custom_fee``: If you have a custom field with your membership defining the expected yearly fee, you can set the field name here (e.g. "custom_28"). Otherwise the minimum fee as defined by the membership type is used.
   5. ``custom_interval``: If you have a custom field with your membership defining the payment cycle, you can set the field name here (e.g. "custom_29"). The values here refer to the number of months, i.e. "1" indicates monthly payment, "3" quarterly, and so on. Otherwise the period defined by the membership type is used.


What's next?
===
Well, that depends a lot on you. If you want to use this extension it's probably best to contact us: 

    B. Endres, SYSTOPIA (endres@systopia.de)
