<?php

declare(strict_types=1);

use DigitalCoreHub\LaravelAiTranslator\Cache\TranslationCache;
use Illuminate\Support\Facades\Cache;

/**
 * v0.x regresyonu: "çeviri önbelleğini temizle" komutu uygulamanın
 * bütün cache'ini uçuruyordu.
 */
it('sadece çeviri önbelleğini temizler', function () {
    config()->set('ai-translator.cache.enabled', true);

    Cache::put('kullanicinin-onemli-verisi', 'kaybolmamalı', 600);

    $cache = app(TranslationCache::class);
    $cache->put('openai', 'en', 'tr', 'Save', 'Kaydet');

    $this->artisan('ai:translate:cache-clear')->assertSuccessful();

    expect(Cache::get('kullanicinin-onemli-verisi'))->toBe('kaybolmamalı')
        ->and($cache->get('openai', 'en', 'tr', 'Save'))->toBeNull();
});

it('önbellek kapalıysa uyarır', function () {
    config()->set('ai-translator.cache.enabled', false);

    $this->artisan('ai:translate:cache-clear')
        ->expectsOutputToContain('zaten kapalı')
        ->assertSuccessful();
});
