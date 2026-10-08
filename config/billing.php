<?php

/**
 * Provider-side subscription billing settings (Platform).
 */
return [

    /*
    | The provider's calendar for billing. The application runs in UTC; a
    | trial end, a period end or a reminder day is the date in this zone
    | (see BillingClock).
    */
    'timezone' => env('BILLING_TIMEZONE', 'Asia/Jakarta'),

    /*
    | Trial started automatically when a school application is approved.
    | trial_plan is a plans.key; when no such plan exists (master data not
    | seeded) onboarding still succeeds and the tenant simply has no
    | subscription yet.
    */
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),
    'trial_plan' => env('BILLING_TRIAL_PLAN', 'starter'),

    /*
    | Days of full access after a trial or a paid period has ended, before
    | the school is suspended. A cancelled subscription has no grace.
    */
    'trial_grace_days' => (int) env('BILLING_TRIAL_GRACE_DAYS', 7),
    'overdue_grace_days' => (int) env('BILLING_OVERDUE_GRACE_DAYS', 7),

    /*
    | An active subscription ending within this many days shows as "due".
    */
    'due_soon_days' => 7,

    /*
    | Days between an invoice's issue date and its due date. A renewal
    | invoice is due on the day the current paid period ends instead.
    */
    'invoice_due_days' => 7,

    /*
    | The renewal invoice is issued this many days before the paid period
    | ends.
    */
    'renewal_invoice_days_before' => 14,

    /*
    | Reminder days, as the distance to the date they are anchored on:
    | trial_ending = days before the trial ends; due = days before the
    | invoice falls due; overdue = days after it fell due.
    */
    'reminders' => [
        'trial_ending' => [3],
        'due' => [7, 1],
        'overdue' => [1, 5],
    ],

    /*
    | One billing:daily run suspends nobody past this many schools, or this
    | share (percent) of the schools that are billed, unless it is run
    | with --force. A guard against a clock or payment-recording mistake
    | closing many schools at once.
    */
    'suspend_cap' => [
        'count' => 5,
        'percent' => 20,
    ],

    /*
    | The console warns when billing:daily has not finished for this many
    | days (the cron job has probably stopped).
    */
    'cron_stale_days' => 2,

    /*
    | Who the school hears from on invoices and the "access stopped" page,
    | and where it transfers the money. Read live, never snapshotted: a
    | changed account shows on every invoice still unpaid.
    */
    'issuer' => [
        'name' => env('BILLING_ISSUER_NAME', 'SIMAS'),
        'email' => env('BILLING_ISSUER_EMAIL'),
        'whatsapp' => env('BILLING_ISSUER_WHATSAPP'),
        'phone' => env('BILLING_ISSUER_PHONE'),
        'address' => env('BILLING_ISSUER_ADDRESS'),
        'bank' => [
            'name' => env('BILLING_ISSUER_BANK_NAME'),
            'account' => env('BILLING_ISSUER_BANK_ACCOUNT'),
            'holder' => env('BILLING_ISSUER_BANK_HOLDER'),
        ],
    ],

];
