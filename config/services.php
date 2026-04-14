<?php

return [
    'wix' => [
        'app_id' => env('WIX_APP_ID', ''),
        'app_secret' => env('WIX_APP_SECRET', ''),
        '3d_store_public_key' => env('WIX_3D_STORE_PUBLIC_KEY', ''),
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
        /**
         * Meshy image-to-3d tuning (see https://docs.meshy.ai/api/image-to-3d).
         * Defaults favor small GLBs for web (was ~7MB with implicit standard + all formats).
         */
        'meshy' => [
            /** `lowpoly` (smaller, Meshy-optimized) or `standard` (uses polycount/remesh below). */
            'model_type' => env('MESHY_MODEL_TYPE', 'lowpoly'),
            /** false drops texturing for much smaller files (plain mesh). */
            'should_texture' => filter_var(env('MESHY_SHOULD_TEXTURE', true), FILTER_VALIDATE_BOOLEAN),
            'enable_pbr' => filter_var(env('MESHY_ENABLE_PBR', false), FILTER_VALIDATE_BOOLEAN),
            /** Used when model_type is standard only. */
            'ai_model' => env('MESHY_AI_MODEL', 'latest'),
            'should_remesh' => filter_var(env('MESHY_SHOULD_REMESH', true), FILTER_VALIDATE_BOOLEAN),
            'topology' => env('MESHY_TOPOLOGY', 'triangle'),
            'target_polycount' => (int) env('MESHY_TARGET_POLYCOUNT', 2500),
        ],
    ],
];
