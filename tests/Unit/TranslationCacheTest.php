<?php

declare(strict_types=1);

use DigitalCoreHub\LaravelAiTranslator\Cache\TranslationCache;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->cache = new TranslationCache(Cache::store('array'), enabled: true);
});

it('yazdığını geri okur', function () {
    $this->cache->put('openai', 'en', 'tr', 'Save', 'Kaydet');

    expect($this->cache->get('openai', 'en', 'tr', 'Save'))->toBe('Kaydet');
});

it('sağlayıcı, kaynak ve hedef dile göre ayrı anahtar tutar', function () {
    $this->cache->put('openai', 'en', 'tr', 'Save', 'Kaydet');

    expect($this->cache->get('deepl', 'en', 'tr', 'Save'))->toBeNull()
        ->and($this->cache->get('openai', 'en', 'de', 'Save'))->toBeNull()
        ->and($this->cache->get('openai', 'de', 'tr', 'Save'))->toBeNull();
});

it('birden fazla anahtarı tek turda çeker', function () {
    $this->cache->put('openai', 'en', 'tr', 'Save', 'Kaydet');
    $this->cache->put('openai', 'en', 'tr', 'Cancel', 'İptal');

    $hits = $this->cache->getMany('openai', 'en', 'tr', [
        'a' => 'Save',
        'b' => 'Cancel',
        'c' => 'Delete',
    ]);

    expect($hits)->toBe(['a' => 'Kaydet', 'b' => 'İptal']);
});

/**
 * v0.x regresyonu: clear() doğrudan $repository->clear() çağırıyor,
 * uygulamanın bütün cache'ini uçuruyordu.
 */
it('temizlerken uygulamanın diğer cache kayıtlarına dokunmaz', function () {
    Cache::store('array')->put('alakasiz', 'deger', 600);
    $this->cache->put('openai', 'en', 'tr', 'Save', 'Kaydet');

    $this->cache->flush();

    expect(Cache::store('array')->get('alakasiz'))->toBe('deger')
        ->and($this->cache->get('openai', 'en', 'tr', 'Save'))->toBeNull();
});

it('temizledikten sonra sürüm numarasını artırır', function () {
    $before = $this->cache->version();

    $this->cache->flush();

    expect($this->cache->version())->toBe($before + 1);
});

it('kapalıyken hiçbir şey saklamaz', function () {
    $disabled = new TranslationCache(Cache::store('array'), enabled: false);

    $disabled->put('openai', 'en', 'tr', 'Save', 'Kaydet');

    expect($disabled->get('openai', 'en', 'tr', 'Save'))->toBeNull()
        ->and($disabled->getMany('openai', 'en', 'tr', ['a' => 'Save']))->toBe([]);
});

it('tek anahtarı unutabilir', function () {
    $this->cache->put('openai', 'en', 'tr', 'Save', 'Kaydet');
    $this->cache->put('openai', 'en', 'tr', 'Cancel', 'İptal');

    $this->cache->forget('openai', 'en', 'tr', 'Save');

    expect($this->cache->get('openai', 'en', 'tr', 'Save'))->toBeNull()
        ->and($this->cache->get('openai', 'en', 'tr', 'Cancel'))->toBe('İptal');
});
