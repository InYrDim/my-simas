<?php

/**
 * Provider-side subscription billing settings (Platform).
 */
return [

    /*
    | Trial started automatically when a school application is approved.
    | trial_plan is a plans.key; when no such plan exists (master data not
    | seeded) onboarding still succeeds and the tenant simply has no
    | subscription yet.
    */
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),
    'trial_plan' => env('BILLING_TRIAL_PLAN', 'starter'),

    /*
    | An active subscription ending within this many days shows as "due".
    */
    'due_soon_days' => 7,

    /*
    | Days between an invoice's issue date and its due date.
    */
    'invoice_due_days' => 7,

];
