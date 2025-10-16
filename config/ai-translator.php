<?php

return [
    'provider' => env('AI_TRANSLATOR_PROVIDER', 'openai'),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => 'gpt-4o-mini',
        ],

        'deepl' => [
            'api_key' => env('DEEPL_API_KEY'),
        ],

        'google' => [
            'api_key' => env('GOOGLE_API_KEY'),
        ],
    ],

    'cache_enabled' => true,
    'cache_driver' => 'file',

    // Multiple language directories
    'paths' => [
        base_path('lang'),
        base_path('resources/lang'),
    ],

    'auto_create_missing_files' => true,
];
