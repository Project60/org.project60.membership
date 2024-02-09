# How To Guide

The most important feature of the membership extension is the automatic processing of memberships and membership fees as described [here](./automation.md).

This chapater is a loose combination of manual tasks that arise when working with the membership extension.

## How to assign a SEPA mandate to a membership

In European countries it is common to use SEPA mandates as recurring payment method for membership fees.

To use this feature, you need to first install the [CiviSEPA Extension](https://docs.civicrm.org/civisepa/en/latest/).
Additionally, at least the **payment contract** field needs to be configured in the settings `civicrm/admin/setting/membership`.

The SEPA mandate needs to be assigned manually for every membership.

1. Open the contact page of your member, open the tab **SEPA Mandates** and create a new SEPA mandate. Choose the financial type that is associated with the desired membership type.
2. Open the tab **Memberships** and create a new membership if it doesn't exist yet. Edit all relevant data besides the SEPA mandate.
3. In the overview of all memberships, click on **View** beside the membership you just created. Here you can change the **Payment Contract** and select the SEPA mandate you created.

## How to find contacts with missing payments

If you want to identify all active members that are behind with their payment, you can configure the custom field [missing abount](./configuration.md#missing-payments). The value of this field is calculated automatically by the job `Membership.process`. Search all contacts with a positive entry in the missing amount field, for example by using the report **Membership (Detail)**. This should be done regularly, for example every month, if you want to send reminders about forgotten payments.

You can also search all contacts with a membership of status _grace period_ and a positive entry in the missing amount field. This will exclude the contacts whose memberships are inactive since a longer time period. You can define at **Administer > CiviMember > Membership Status Rules** how long after the end date a membership should be in status _grace period_.

## How to find contributions where the automatic assignment failed

The scheduled job `MembershipPayment.synchronize` assigns contributions of a certain financial type to memberships. If there are contacts for which the job cannot assign the contribution correctly, it doesn't assign any contribution and goes on with processing the next contact.

To find out which contacts were omitted during the assignment step, you can manually start the job. Go to **Membership → Synchronise Payments** and click on **Synchronise Now**. After the synchronization you will get an overview about all assignments that have been done and all contacts where problems occurred during the synchronization.

## How to automatically generate membership numbers

You first need to configure the corresponding settings [here](./configuration.md#membership-number). Then a membership number is generated automatically whenever you create a new membership. However, old membership numbers are not generated or updated. You can do this manually, for example with an API call.

## How to let someone else pay the membership fee

It is possible to define another contact to do the membership payments and to take that into account during the automatic extension of a membership. The configurations needed for this are described [here](./configuration.md#general) in the description **Paid By Field**.

If the other contact takes over the payment only once and not every time, you can manually assign the contribution to the membership it belongs to.

## How to manually assign contributions to a membership

In some rare cases it might be necessary to assign a contribution manually to a membership. An example could be a person who paid their membership fee long before the start date of the membership. The [automatic synchronization](./automation.md#assign-contributions-to-memberships) will fail in this case. Another example could be a contact with several active memberships of the same type.

Go to **Contributions > Find Contributions** and edit the search criteria in order to find the contributions. Select the contributions and click on **Actions > Assign to Membership**. You will be able to choose the person and membership explicitly to which the contribution should be assigned.

## How to manually detach contributions from a membership

The workflow is the same as above: Select the contributions in the search result at **Contributions > Find Contributions** and choose the action **Detach from Membership**.
