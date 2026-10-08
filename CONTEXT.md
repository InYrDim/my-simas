# My SIMAS

A multi-school management system run by a provider that sells access to schools. This file holds the language of the provider's subscription business.

## Language

**Provider**:
The operator of the system, who sells access to schools and runs the console.
_Avoid_: Admin pusat, vendor

**Plan**:
A priced package of modules and limits that a school can subscribe to.
_Avoid_: Paket (in code and specs), tier

**Private plan**:
A Plan not offered at sign-up, which the Provider assigns to one school for a custom deal.
_Avoid_: Custom price, special plan

**Plan limit**:
A ceiling a Plan sets on one measure of a school's size, such as its students, staff accounts, or stored files.
_Avoid_: Quota, cap

**Subscription**:
A school's single ongoing relationship with one Plan, covering its trial and paid periods.
_Avoid_: Langganan (in code and specs), membership

**Trial**:
The free, time-limited first stage of a Subscription, started when a school's application is approved.
_Avoid_: Free period, demo

**Invoice**:
A request for payment issued to a school for one period of its Subscription.
_Avoid_: Tagihan, bill

**Payment**:
One attempt to pay an Invoice, which is pending until it is confirmed paid, or fails or expires.
_Avoid_: Pembayaran (in code and specs), transaction

**Billing suspension**:
A school's access stopped because its Trial or paid period ran out past the Grace period, lifted automatically by payment.
_Avoid_: Penangguhan, lockout

**Manual suspension**:
A school's access stopped by the Provider for a reason unrelated to billing, lifted only by the Provider.
_Avoid_: Ban

**Billing-exempt**:
A school the Provider has marked as owing nothing, so it is never suspended, reminded, or counted in revenue.
_Avoid_: Gratis, whitelisted

**Billing contact**:
The one name and email address where a school receives its Invoices and billing messages.
_Avoid_: Penerima tagihan, finance email

**Billing notice**:
A record of one billing message sent to a school's Billing contact.
_Avoid_: Notification, reminder log

**Grace period**:
The days after a Trial ends or an Invoice falls due during which the school keeps access before it is suspended.
_Avoid_: Tenggang (in code and specs), buffer
