Project60 Membership Extension
===

This Extensions provides tools for a "European" interpretation of the membership concept. In the Anglo-Saxon countries (CiviCRM's native land) a membership is similar to a service subscription. If you don't pay anymore, your "subscription" will not be extended, and you will be excluded from the service (= your membership ends). In many other countries, the conception is different: it's more like a indeterminate binding contract. You don't necessarily pay upfront, and your membership is *not* terminated automatically if you don't pay for the next period (which is what CiviCRM assumes).

A number of problems arise from this different concept:

1. Payments are often not made upfront, the way CiviCRM expects.
2. Membership fees are not necessarily entered manually, or collected via a payment processor, but can enter CiviCRM by other means (e.g. import, SEPA collection, etc.). This means that they can be contribution records, but not (yet) linked to the corresponding membership. 
3. Memberships should be automatically extended, when the appropriate payment is in the system.
4. Despite all those possible irregularities, you need to know who is behind on their payments and by how much.
5. Etc.


Features
===

The membership extension replaces the built-in functionality of extending memberships. It provides the following features:

- Map contributions of a specific financial type onto memberships. This makes it possible to find contacts that did not pay enough membership fees.
- Automatically extend the end date of a membership if enough membership fees for the period are paid.
- Create membership numbers automatically with a configurable pattern.
- Provide additional tokens for communication, for example cancellation date and reason.

The project 60 membership extension does not interfere with the built-in mechanism of membership types and membership statuses, as well as the  automatic changing of membership statuses.


Documentation
===
Updated documentation is underway, but not yet published. For now, you can check the ```/docs``` folder to see what's already there. 

We need your support
===

This CiviCRM extension is provided as Free and Open Source Software, and we are happy if you find it useful. However, we have put a lot of work into it (and continue to do so), much of it unpaid for. So if you benefit from our software, please consider making a financial contribution so we can continue to maintain and develop it further.

If you are willing to support us in developing this CiviCRM extension, please send an email to info@systopia.de to get an invoice or agree a different payment method. Thank you! 
