<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    | Belirtilen sağlayıcı aktif olarak kullanılacak.
    | openai, deepl, google, deepseek değerlerinden biri olabilir.
    */
    'provider' => env('AI_TRANSLATOR_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Providers Configuration
    |--------------------------------------------------------------------------
    | Her sağlayıcının API anahtarlarını ve yapılandırmasını burada tanımlayabilirsiniz.
    */
    'providers' => [

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        ],

        'deepl' => [
            'api_key' => env('DEEPL_API_KEY'),
        ],

        'google' => [
            'api_key' => env('GOOGLE_API_KEY'),
        ],

        'deepseek' => [
            'api_key' => env('DEEPSEEK_API_KEY'),
            'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    | Çeviri önbelleği aktif olsun mu ve hangi driver kullanılsın.
    */
    'cache_enabled' => true,
    'cache_driver' => 'file',

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    | Tarama yapılacak dil dizinleri.
    */
    'paths' => [
        base_path('lang'),
        base_path('resources/lang'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto File Creation
    |--------------------------------------------------------------------------
    | Eksik dil dosyaları otomatik oluşturulsun mu.
    */
    'auto_create_missing_files' => true,
];
