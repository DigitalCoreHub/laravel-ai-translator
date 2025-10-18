<?php

use Illuminate\Support\Str;

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
            'base_url' => env('DEEPSEEK_API_BASE', 'https://api.deepseek.com/v1'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    | Çeviri önbelleği aktif olsun mu ve hangi driver kullanılsın.
    */
    'cache_enabled' => (bool) env('AI_TRANSLATOR_CACHE_ENABLED', true),
    'cache_driver' => env('AI_TRANSLATOR_CACHE_DRIVER'),

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    | Tarama yapılacak dil dizinleri.
    */
    'paths' => (static function () {
        $paths = env('AI_TRANSLATOR_PATHS');

        if (is_string($paths) && trim($paths) !== '') {
            $segments = array_filter(array_map('trim', explode(',', $paths)));

            $resolved = array_map(static function (string $segment) {
                if ($segment === '') {
                    return null;
                }

                if (Str::startsWith($segment, ['/'])) {
                    return $segment;
                }

                return base_path($segment);
            }, $segments);

            $resolved = array_filter($resolved, static fn ($path) => is_string($path) && $path !== '');

            return array_values(array_unique($resolved));
        }

        return [
            base_path('lang'),
            base_path('resources/lang'),
        ];
    })(),

    /*
    |--------------------------------------------------------------------------
    | Auto File Creation
    |--------------------------------------------------------------------------
    | Eksik dil dosyaları otomatik oluşturulsun mu.
    */
    'auto_create_missing_files' => true,

    /*
    |--------------------------------------------------------------------------
    | Panel Middleware
    |--------------------------------------------------------------------------
    | Web paneli için kullanılacak middleware zinciri.
    */
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | API Middleware
    |--------------------------------------------------------------------------
    | JSON API uç noktasında kullanılacak middleware zinciri.
    */
    'api_middleware' => ['api'],
];
