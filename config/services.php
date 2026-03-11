<?php

return [
    'wix' => [
        'app_id' => env('WIX_APP_ID', ''),
        'app_secret' => env('WIX_APP_SECRET', ''),
        'dev_instance_token' => env('WIX_DEV_INSTANCE_TOKEN', 'dev'),
        'dev_instance_id' => env('WIX_DEV_INSTANCE_ID', 'dev-local'),
        'dev_comp_id' => env('WIX_DEV_COMP_ID', 'comp-dev-local'),
    ],

    'paypal' => [
        'sandbox' => env('PAYPAL_SANDBOX', true),
        'ipn_url' => env('PAYPAL_IPN_URL', ''),
        'return_url' => env('PAYPAL_RETURN_URL', ''),
        'cancel_url' => env('PAYPAL_CANCEL_URL', ''),
    ],

    'fx' => [
        'provider' => env('FX_PROVIDER', 'exchangerate'),
        'api_key' => env('FX_API_KEY', ''),
        'cache_ttl' => env('FX_CACHE_TTL_MINUTES', 60),
    ],

    'image_to_3d' => [
        'provider' => env('IMAGE_TO_3D_PROVIDER', 'meshy'),
        'api_key' => env('IMAGE_TO_3D_API_KEY', ''),
    ],
];
