<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_filter([
        env('FRONTEND_WIDGET_URL'),
        env('FRONTEND_DASHBOARD_URL'),
        env('FRONTEND_SETTINGS_URL'),
        'http://localhost:5174',
        'http://localhost:5175',
        'http://localhost:5176',
    ]),
    'allowed_origins_patterns' => [
        '#^https://.*\.wix\.com$#',
        '#^https://.*\.editorx\.com$#',
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
