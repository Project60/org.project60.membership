# Configuration

The membership extension works with specific custom fields that are often used when working with memberships. It provides additional tokens for email templates or other helpful features. The usage of most of these fields is optional, but some of them are necessary or help with the mapping of contributions to memberships.

If you want to use the fields, you usually need to define corresponding custom fields first, as is described below. Afterwards, you can select them in the settings page of the membership extension, which can be found at `civicrm/admin/setting/membership`. They will only appear if all the criteria specified below are met.

## Membership number

Define a custom field to store the membership number at `civicrm/admin/custom/group`. It should be defined as follows:

- Part of a custom field set that is used for **Memberships**
- **Active?** is checked
- **Is this Field searchable?** is checked
- Data Type: **Alphanumeric**
- **View Only** is not checked

You can name the field however you want to.

Go to `civicrm/admin/setting/membership` and choose the field you created for the setting **Membership Number Field**.

If you select **Show in Summary View**, the current membership number of the contact will be shown in the contact's summary view, underneath the external identifier.

### Automatic generation

Configure a **Number Pattern** in the settings, if you want the membership number to be automatically generated for new memberships.

Any characters are allowed, but it is advisable to stick to alphanumeric characters, and to avoid whitespaces and special characters.

Additionally, the following special tokens are currently available::

    {mid[+offset]}: CiviCRM Membership ID, with optional offset value.
    {cid[+offset]}: CiviCRM Contact ID, with optional offset value.

For example, if you want your membership numbers to take the form `Membership-MyOrganisation-[CiviCRMID]-[UniqueNumber]` where the unique number should always have 6 digits, you can set the pattern as `Membership-MyOrganisation-{cid}-{mid+100000}`.

## Cancellations

The cancellation date is the date when the cancellation request reached your organisation. It is usually earlier than the end date of the membership, which is specified in the cancellation request.

Configuring the cancellation date and reason in the membership extension will _not_ lead to additional automatic processes. The advantage in contrast to working with custom fields only is the availability of tokens that you can use in emails or other templates.

Define a custom field to store the membership number at `civicrm/admin/custom/group`. It should be defined as follows:

- Part of a custom field set that is used for **Memberships**
- **Active?** is checked
- **Is this Field searchable?** is checked
- Data Type: **Alphanumeric**
- **View Only** is not checked

The cancellation reason should not allow for **Multi-Select**.

Additionally, open the membership extension settings at `civicrm/admin/setting/membership`. Add the fields you created in the block **Membership cancellation**.

## Payment integration

### Payment Contract

You can select a membership custom field to record the connection to any recurring contribution. The most typical use case is to select a SEPA mandate for this field.

Conditions for the definition of the custom field:

- Part of a custom field set that is used for **Memberships**
- **Active?** is checked
- **Is this Field searchable?** is checked
- Data Type: **Integer**
- **View Only** is checked

!!!note
    If you want to change or define the payment contract of an existing membership, you need to click on **View** instead of **Edit**. The payment contract is not editable in the edit menu of a membership.

!!!note "Note for developers"
    The id that is saved in this field is not the internal id of the SEPA mandate but the id of the corresponding recurring contribution.

### Annual fee

This option is only available if you selected a payment contract field.

If you define a new Membership type at `civicrm/admin/member/membershipType`, you need to provide a **Minimum Fee**. However, in many cases, membership fees are more volatile and people agree to pay more than the minimum fee. In this field, you can record the amount that you expect this member to pay.

Conditions for the definition of the custom field:

- Part of a custom field set that is used for **Memberships**
- **Active?** is checked
- **Is this Field searchable?** is checked
- Data Type: **Money**
- **View Only** is not checked

### Record Annual Fee Changes

If you select this option, an activity of type **Membership Fee Update** is created whenever you change the annual fee of a membership.

### End with Statuses

If a membership is ended and the membership is connected to a payment contract, you usually want to cancel the payment contract at the same date. The cancellation of the payment contract will be done automatically only, if you choose a status here. Select one or more membership states, for which the corresponding payment contract and the corresponding recurring contribution shall be set to _completed_.

### Hide built-in features

The built-in auto renewal feature interferes with the concept of the  Membership Extension, so we recommend not to use it. To avoid confusion, you can activate the option **Hide Auto Renewal**. The interfering fields will then be hidden from the UI and the column in the tab and the search result will be freed up for something else.

### Update membership status and end date

Extend the membership as soon as the membership contribution is set to _completed_. This will calculate the new end date and a new status.

Remark: This only works if there is exactly one contribution per membership period.

## Derived Fields

You can define additional custom fields and select them here. These fields will then be calculated automatically out of the payment contract that is related to the membership. All derived fields defined here will be available as tokens for email communication.

You can update all derived fields by selecting **Update Derived Fiels on Save** below.

!!!note
    All derived fields are calculated out of the payment contract only. Additional contributions that are not connected to the payment contract are ignored.

### Installment Amount Field

Fhe amount that is paid during each payment cycle. For example, if the annual amount of the payment contract is given as 500€ and is paid quarterly, the installment amount is calculated to be 125€.

Condition for the custom field configuration:

- Part of a custom field set that is used for **Memberships**
- **Active?** is checked
- **Is this Field searchable?** is checked
- Data Type: **Money**
- **View Only** is checked

### Annual Gap Field

Difference between the expected membership fee and the amount of the payment contract. The expected membership fee is given by the **Annual Amount Field**. If this field is not set, the expected membership fee is set as the minimum fee defined in the configuration of the membership type.

If the value of the Annual Gap field is positive, the money that can be received via the payment contract is less than the expected membership fee.

Condition for the custom field configuration:

- Part of a custom field set that is used for **Memberships**
- **Active?** is checked
- **Is this Field searchable?** is checked
- Data Type: **Money**
- **View Only** is checked

!!!note
    This field compares only the annual values and does not take into account how many actual payments are registered with the membership. If you want to take existing contributions into account, configure the field `Missing Amount (current period)`.

### Payment Frequency Field

The intervall between payments, for example three month.

Condition for the custom field configuration:

- Part of a custom field set that is used for **Memberships**
- **Active?** is checked
- **Is this Field searchable?** is checked
- Data Type: **Alphanumeric**
- **View Only** is checked
- Field Input Type: **Drop-down** (select list)
- Values of Select Options: **1, 3, 6, 12**. They correspond to monthly, quarterly, semi-annualy and annualy payment frequencies.

### Payment Type Field and Mapping

You can choose to work with the default contribution payment types that can be found at **Administer > CiviContribute > Payment Methods**. However, it can be helpful to group them together to get a better overview when you analyse the data. For example, you might want to group the three SEPA payment methoths `SEPA DD First Transaction`, `SEPA DD Recurring Transaction` and `SEPA DD One-off Transaction` together. In this case, you can define your own custom field and choose it in the setting **Payment Type Field**

Condition for the custom field configuration:

- Part of a custom field set that is used for **Memberships**
- **Active?** is checked
- **Is this Field searchable?** is checked
- Data Type: **Alphanumeric**
- **View Only** is checked

You also need to configure the **Payment Type Field Mapping** below. This is not a setting, but will rebuild all information derived from the paid-via field when you press the save button. You can enter a string to map the ids of the built-in payment instrument ids to the values of your option group. The format is a comma separated list of `payment_instrument_id`:`option_group_value` mappings, e.g. `1:3,2:2,5:4,6:4`.

### Update Derived Fiels on Save

If you check this box, all your derived fields will be re-calculated based on the currently linked recurring payment.

## Missing payments

The field selected for **Missing Amount (current period)** holds the outstanding amount for the current membership period.

The value of this field is not derived by the payment contract but calculated by the `Membership.process` job. Therefore, this scheduled job needs to be configured as well. See the [automation](./automation.md) chapter for more details.

Condition for the custom field configuration:

- Part of a custom field set that is used for **Memberships**
- **Active?** is checked
- **Is this Field searchable?** is checked
- Data Type: **Money**
- **View Only** is checked

## Additional Tokens

You can define arbitrary membership custom fields to be added to the membership token list. All fields configured in this configuration menu are already part of the token list.

Usually, the custom fields that are used for memberships can be accessed as tokens only in cases when you work with a search result from the menu **Memberships → Find Memberships**. In other circumstances they will not be listed, i.e. when working with a search result form the extended search or writing an email out of the contact summary page. The Membership Extension provides a token list that is accessible from everywhere and you can add additional custom tokens to this list.

## Payment Synchronisation Tool

The mapping of contributions to memberships can be done in two ways: Via the UI at **Memberships → Synchronise Payments** or as a scheduled job that uses the API call `MembershipPayment.synchronize`. In this section you can configure the default parameters.

### General

- **Only Synchronise between**: This defines which contributions will be considered for synchronisation by defining a time frame. This is optional. You can enter any [valid `strtotime()`](https://www.php.net/manual/en/datetime.formats.php) string like `2017-01-01` or `now - 2 days`.
- **Backward horizon (in days)**: If a membership payment is received within this number of days before the current starting date, the start and the join date will be extended to the payment's received date.
- **Grace period (in days)**: In order to associate contributions with expired memberships, you will first have to manually extend those memberships.
- **Paid by Field**: Sometimes the member is not paying the membership fee themselves but somebody else is undertaking the payments. To integrate these cases into CiviCRM, you can configure a custom field. If the field is empty, it is assumed that the member and paying contact are identical.

The custom field _Paid by_ should have the following configuration:

- Part of a custom field set that is used for **Memberships**
- **Active?** is checked
- **Is this Field searchable?** is checked
- Data Type: **Contact Reference**
- **View Only** is not checked

### Membership Status

Only if a membership has a _live_ status, membership payments will be assigned to it by the job [Membership.process](./automation.md#extend-memberships).

### Financial Type Mapping

Configure here which financial type should be associated to which membership type. We advise to create a financial type for each membership type and use this type only for contributions with the purpose of paying membership fees of this membership type.

This setting is additional to the payment contract given above, but you can select the same financial type for the SEPA mandates.
