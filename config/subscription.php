<?php

return [
    'provider' => env('SUBSCRIPTION_PROVIDER', 'paystack'),

    // Paystack
    'paystack_public_key' => env('PAYSTACK_PUBLIC_KEY'),
    'paystack_secret_key' => env('PAYSTACK_SECRET_KEY'),
    'paystack_webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
    'paystack_callback_url' => env('PAYSTACK_CALLBACK_URL', '/payments/paystack/callback'),

    // Marketplace (Phase 6)
    'marketplace_fee_pct' => env('NOLBASE_MARKETPLACE_FEE_PCT', 10),
];
