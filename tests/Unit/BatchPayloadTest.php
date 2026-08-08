<?php

declare(strict_types=1);

use DigitalCoreHub\LaravelAiTranslator\Support\BatchPayload;

it('varsayılan olarak id/text kayıtları içeren json üretir', function () {
    $encoded = (new BatchPayload)->encode(['k0' => 'Save', 'k1' => 'Cancel']);

    expect(json_decode($encoded, true))->toBe([
        ['id' => 'k0', 'text' => 'Save'],
        ['id' => 'k1', 'text' => 'Cancel'],
    ]);
});

it('unicode karakterleri kaçışlamaz', function () {
    $encoded = (new BatchPayload)->encode(['k0' => 'Ağrı Dağı']);

    expect($encoded)->toContain('Ağrı Dağı');
});

/**
 * laravel-toon opsiyonel bir bağımlılık; kurulu değilse json'a düşmeli,
 * paket bu yüzden patlamamalı.
 */
it('toon paketi kurulu değilse sessizce json kullanır', function () {
    $payload = new BatchPayload('toon');

    $encoded = $payload->encode(['k0' => 'Save']);

    if ($payload->usesToon()) {
        expect($encoded)->toContain('id')->and($encoded)->toContain('Save');

        return;
    }

    expect(json_decode($encoded, true))->toBe([['id' => 'k0', 'text' => 'Save']]);
});

it('boş grup için boş dizi üretir', function () {
    expect((new BatchPayload)->encode([]))->toBe('[]');
});
