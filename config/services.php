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
         * Meshy image-to-3d (see https://docs.meshy.ai/api/image-to-3d).
         * Defaults match mebel/metrics_platform `src/app/api/meshy/generate/route.ts` (implicit standard + GLB-only).
         */
        'meshy' => [
            /** `standard` (same as mebel) or `lowpoly` for smaller Meshy-optimized meshes. */
            'model_type' => env('MESHY_MODEL_TYPE', 'standard'),
            'should_texture' => filter_var(env('MESHY_SHOULD_TEXTURE', true), FILTER_VALIDATE_BOOLEAN),
            'enable_pbr' => filter_var(env('MESHY_ENABLE_PBR', false), FILTER_VALIDATE_BOOLEAN),
            /** Used when model_type is standard only. */
            'ai_model' => env('MESHY_AI_MODEL', 'latest'),
            /**
             * When false (Meshy-6 default): highest-precision mesh; when true: decimate to target_polycount.
             * Remesh rounds corners — keep false for silhouette fidelity unless you need a fixed triangle budget.
             */
            'should_remesh' => filter_var(env('MESHY_SHOULD_REMESH', false), FILTER_VALIDATE_BOOLEAN),
            'topology' => env('MESHY_TOPOLOGY', 'triangle'),
            /** Meshy default is 30000; low values (e.g. 5000) flatten detail. */
            'target_polycount' => (int) env('MESHY_TARGET_POLYCOUNT', 30_000),
            /**
             * Meshy-6 / latest only. When false, input is not "optimized" — preserves silhouette vs. reference photo.
             * @see https://docs.meshy.ai/api/image-to-3d (image_enhancement)
             */
            'image_enhancement' => filter_var(env('MESHY_IMAGE_ENHANCEMENT', false), FILTER_VALIDATE_BOOLEAN),
            /** Optional: off | auto | on — empty uses API default (auto). Use `off` if symmetry distorts asymmetric objects. */
            'symmetry_mode' => env('MESHY_SYMMETRY_MODE', ''),
            /**
             * When should_remesh is true, ask Meshy to keep the high-detail GLB (needed for prefer_pre_remeshed_glb).
             */
            'save_pre_remeshed_model' => filter_var(env('MESHY_SAVE_PRE_REMESHED_MODEL', false), FILTER_VALIDATE_BOOLEAN),
            /** Prefer downloading pre_remeshed_model.glb (sharper) when remesh is enabled and Meshy returned it. */
            'prefer_pre_remeshed_glb' => filter_var(env('MESHY_PREFER_PRE_REMESHED_GLB', false), FILTER_VALIDATE_BOOLEAN),
        ],
    ],
];
