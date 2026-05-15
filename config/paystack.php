<?php

return [
    'public_key' => env('PAYSTACK_PUBLIC_KEY'),
    'secret_key' => env('PAYSTACK_SECRET_KEY'),
    'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),

    'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),

    'callback_url' => env('PAYSTACK_CALLBACK_URL', '/payments/paystack/callback'),

    'currency' => env('PAYSTACK_CURRENCY', 'NGN'),

    // Marketplace (Phase 6) — platform fee on Client→Developer payments.
    'marketplace_fee_pct' => (float) env('NOLBASE_MARKETPLACE_FEE_PCT', 10),
];
