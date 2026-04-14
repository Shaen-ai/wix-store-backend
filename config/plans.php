<?php

/**
 * SaaS plan limits (prices live in Wix; keys must match tenants.plan).
 *
 * max_products: null = unlimited
 */
return [
    'definitions' => [
        'basic' => [
            'max_products' => 5,
            'monthly_image_to_3d' => 3,
            'show_powered_by' => true,
        ],
        'light' => [
            'max_products' => 20,
            'monthly_image_to_3d' => 10,
            'show_powered_by' => false,
        ],
        'business' => [
            'max_products' => null,
            'monthly_image_to_3d' => 50,
            'show_powered_by' => false,
        ],
        'business-pro' => [
            'max_products' => null,
            'monthly_image_to_3d' => 200,
            'show_powered_by' => false,
        ],
    ],
];
