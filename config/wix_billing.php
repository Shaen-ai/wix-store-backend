<?php

/**
 * Map Wix paid plan vendor identifiers (from billing webhooks) to app plan keys.
 * Set env vars to vendor product / package IDs from your Wix app monetization dashboard.
 *
 * @see https://dev.wix.com/docs/build-apps/develop-your-app/monetization
 */
$map = [];
foreach (
    [
        'WIX_PLAN_VENDOR_LIGHT' => 'light',
        'WIX_PLAN_VENDOR_BUSINESS' => 'business',
        'WIX_PLAN_VENDOR_BUSINESS_PRO' => 'business-pro',
    ] as $envKey => $planKey
) {
    $id = env($envKey);
    if ($id !== null && $id !== '') {
        $map[$id] = $planKey;
    }
}

return [
    'vendor_product_to_plan' => $map,
    /** When Wix sends a paid plan event but no env vendor id matches, assign this tier (legacy “premium”). */
    'default_paid_plan' => env('WIX_DEFAULT_PAID_PLAN', 'business'),
];
