<?php

declare(strict_types=1);

use DigitalCoreHub\LaravelAiTranslator\Translation\PlaceholderGuard;

beforeEach(fn () => $this->guard = new PlaceholderGuard);

it('laravel yer tutucularını maskeler', function () {
    [$masked, $tokens] = $this->guard->mask('Merhaba :name, :count mesajın var');

    expect($masked)->not->toContain(':name')
        ->and($tokens)->toHaveCount(2)
        ->and(array_values($tokens))->toBe([':name', ':count']);
});

it('html, blade ve sprintf parçalarını maskeler', function () {
    $text = 'Devam için <a href="/x">tıkla</a>, toplam {{ $total }} adet, %s kaldı';

    [$masked, $tokens] = $this->guard->mask($text);

    expect($masked)->not->toContain('<a href')
        ->and($masked)->not->toContain('{{')
        ->and($masked)->not->toContain('%s')
        ->and($tokens)->toContain('<a href="/x">', '</a>', '{{ $total }}', '%s');
});

it('maskeleyip geri koyduğunda orijinali verir', function (string $text) {
    [$masked, $tokens] = $this->guard->mask($text);

    expect($this->guard->restore($masked, $tokens))->toBe($text);
})->with([
    'Merhaba :name',
    'Toplam {{ $count }} ürün',
    '<strong>:count</strong> yeni mesaj',
    '%1$s ve %2$s',
    'Yer tutucusuz düz metin',
    'Karma: :user <b>{{ $x }}</b> %d',
]);

it('etiket içindeki yer tutucuyu bölmez', function () {
    [$masked, $tokens] = $this->guard->mask('<a href=":url">Tıkla</a>');

    expect($this->guard->restore($masked, $tokens))->toBe('<a href=":url">Tıkla</a>')
        ->and($tokens)->toHaveCount(2);
});

/**
 * v0.x burada sessizce ÇEVRİLMEMİŞ kaynak metni geri döndürüyor,
 * İngilizce metin Türkçe dosyaya yazılıyordu.
 */
it('yer tutucu kaybolduysa null döner', function () {
    [, $tokens] = $this->guard->mask('Merhaba :name');

    expect($this->guard->restore('Merhaba', $tokens))->toBeNull();
});

it('yer tutucunun etrafındaki fazla boşluğu tolere eder', function () {
    [$masked, $tokens] = $this->guard->mask('Merhaba :name');

    $mangled = str_replace('⟦0⟧', '⟦ 0 ⟧', $masked);

    expect($this->guard->restore($mangled, $tokens))->toBe('Merhaba :name');
});

it('yer tutucusuz metni olduğu gibi geçirir', function () {
    expect($this->guard->restore('Merhaba dünya', []))->toBe('Merhaba dünya');
});
