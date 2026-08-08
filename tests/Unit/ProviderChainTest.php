<?php

declare(strict_types=1);

use DigitalCoreHub\LaravelAiTranslator\Contracts\TranslationProvider;
use DigitalCoreHub\LaravelAiTranslator\Exceptions\AllProvidersFailedException;
use DigitalCoreHub\LaravelAiTranslator\Providers\ProviderChain;
use DigitalCoreHub\LaravelAiTranslator\Providers\ProviderRegistry;
use DigitalCoreHub\LaravelAiTranslator\Tests\Fakes\FakeProvider;

function chain(array $providers, array $order): ProviderChain
{
    $registry = new class($providers) extends ProviderRegistry
    {
        /** @param array<string, TranslationProvider> $instances */
        public function __construct(protected array $instances)
        {
            parent::__construct(app(), []);
        }

        public function names(): array
        {
            return array_keys($this->instances);
        }

        public function has(string $name): bool
        {
            return isset($this->instances[$name]);
        }

        public function get(string $name): TranslationProvider
        {
            return $this->instances[$name];
        }
    };

    return new ProviderChain($registry, $order);
}

it('ilk sağlayıcı yeterse ikinciye hiç gitmez', function () {
    $first = new FakeProvider('first', ['k0' => 'Kaydet']);
    $second = new FakeProvider('second', ['k0' => 'Yanlış']);

    $result = chain(['first' => $first, 'second' => $second], ['first', 'second'])
        ->translate(['k0' => 'Save'], 'en', 'tr');

    expect($result->translations)->toBe(['k0' => 'Kaydet'])
        ->and($second->calls)->toBe(0);
});

it('ilk sağlayıcı patlarsa ikinciye düşer', function () {
    $first = FakeProvider::failing('first', 'kota doldu');
    $second = new FakeProvider('second', ['k0' => 'Kaydet']);

    $result = chain(['first' => $first, 'second' => $second], ['first', 'second'])
        ->translate(['k0' => 'Save'], 'en', 'tr');

    expect($result->translations)->toBe(['k0' => 'Kaydet'])
        ->and($result->providerFor('k0'))->toBe('second')
        ->and($result->failures)->toHaveKey('first');
});

/**
 * Kısmi başarı: ilk sağlayıcı 2 anahtarın 1'ini döndürürse
 * ikinciye sadece kalan 1 anahtar gitmeli.
 */
it('sadece eksik kalan anahtarları bir sonraki sağlayıcıya sorar', function () {
    $first = new FakeProvider('first', ['k0' => 'Kaydet']);
    $second = new FakeProvider('second', ['k1' => 'İptal']);

    $result = chain(['first' => $first, 'second' => $second], ['first', 'second'])
        ->translate(['k0' => 'Save', 'k1' => 'Cancel'], 'en', 'tr');

    expect($result->translations)->toBe(['k0' => 'Kaydet', 'k1' => 'İptal'])
        ->and($second->lastBatch)->toBe(['k1' => 'Cancel']);
});

it('yapılandırılmamış sağlayıcıyı hiç denemez', function () {
    $unconfigured = FakeProvider::unconfigured('first');
    $second = new FakeProvider('second', ['k0' => 'Kaydet']);

    chain(['first' => $unconfigured, 'second' => $second], ['first', 'second'])
        ->translate(['k0' => 'Save'], 'en', 'tr');

    expect($unconfigured->calls)->toBe(0);
});

it('grubu sağlayıcının batch sınırına göre parçalar', function () {
    $provider = new FakeProvider('first', array_combine(
        array_map(static fn ($i) => "k{$i}", range(0, 4)),
        array_fill(0, 5, 'çeviri'),
    ), maxBatch: 2);

    $texts = array_combine(
        array_map(static fn ($i) => "k{$i}", range(0, 4)),
        array_fill(0, 5, 'Save'),
    );

    chain(['first' => $provider], ['first'])->translate($texts, 'en', 'tr');

    // 5 anahtar / 2'lik gruplar = 3 istek
    expect($provider->calls)->toBe(3);
});

it('hepsi düşerse toplu istisna fırlatır', function () {
    chain([
        'first' => FakeProvider::failing('first', 'bir'),
        'second' => FakeProvider::failing('second', 'iki'),
    ], ['first', 'second'])->translate(['k0' => 'Save'], 'en', 'tr');
})->throws(AllProvidersFailedException::class, '[first] bir | [second] iki');

it('hiçbiri çeviremezse anahtarları untranslated olarak işaretler', function () {
    $result = chain(['first' => new FakeProvider('first', [])], ['first'])
        ->translate(['k0' => 'Save'], 'en', 'tr');

    expect($result->translations)->toBe([])
        ->and($result->untranslated)->toBe(['k0']);
});

it('geçersiz sağlayıcı adlarını sıradan atar', function () {
    $chain = chain(['first' => new FakeProvider('first', [])], ['yok', 'first']);

    expect($chain->order())->toBe(['first']);
});

it('override edilen sağlayıcıyı başa alır', function () {
    $chain = chain([
        'first' => new FakeProvider('first', []),
        'second' => new FakeProvider('second', []),
    ], ['first', 'second']);

    expect($chain->order('second'))->toBe(['second', 'first']);
});

it('boş grup için ağa hiç çıkmaz', function () {
    $provider = new FakeProvider('first', []);

    $result = chain(['first' => $provider], ['first'])->translate([], 'en', 'tr');

    expect($result->translations)->toBe([])
        ->and($provider->calls)->toBe(0);
});
