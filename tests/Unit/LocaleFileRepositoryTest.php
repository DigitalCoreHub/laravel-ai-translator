<?php

declare(strict_types=1);

use DigitalCoreHub\LaravelAiTranslator\Exceptions\InvalidLocalePathException;
use DigitalCoreHub\LaravelAiTranslator\Translation\LocaleFileRepository;

beforeEach(function () {
    $this->repo = app(LocaleFileRepository::class);
});

it('php ve json dosyalarını kaynak-hedef çifti olarak eşler', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);
    $this->writeLangFile('en/admin/users.php', ['title' => 'Users']);
    $this->writeLangFile('en.json', ['Save' => 'Save']);

    $pairs = collect($this->repo->pairs('en', 'tr'));

    expect($pairs->pluck('sourceRelative')->all())
        ->toEqualCanonicalizing(['en/admin/users.php', 'en/auth.php', 'en.json'])
        ->and($pairs->pluck('targetRelative')->all())
        ->toEqualCanonicalizing(['tr/admin/users.php', 'tr/auth.php', 'tr.json']);
});

/**
 * v0.x regresyonu: ai:sync göreli yolu proje köküne göre çözüyor,
 * base_path('en/auth.php') gibi var olmayan yollara yazmaya çalışıyordu.
 */
it('göreli yolları proje köküne değil dil köküne göre çözer', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);

    $pair = $this->repo->pairs('en', 'tr')[0];

    expect($pair->sourcePath)->toBe($this->langPath.'/en/auth.php')
        ->and($pair->targetPath)->toBe($this->langPath.'/tr/auth.php')
        ->and($pair->targetPath)->not->toBe(base_path('tr/auth.php'));
});

it('sadece ilk dil segmentini hedefe çevirir', function () {
    $this->writeLangFile('en/en/nested.php', ['a' => 'b']);

    expect($this->repo->pairs('en', 'tr')[0]->targetRelative)->toBe('tr/en/nested.php');
});

it('dil kökünün dışına çıkan yolları reddeder', function () {
    $this->repo->absolute($this->langPath, '../../.env');
})->throws(InvalidLocalePathException::class, 'dışına çıkıyor');

it('dil kökü içindeki normal yollara izin verir', function () {
    expect($this->repo->absolute($this->langPath, 'tr/auth.php'))
        ->toBe($this->langPath.'/tr/auth.php');
});

it('dizinlerden ve json dosyalarından dilleri toplar', function () {
    $this->writeLangFile('en/auth.php', ['a' => 'b']);
    $this->writeLangFile('de/auth.php', ['a' => 'b']);
    $this->writeLangFile('fr.json', ['a' => 'b']);

    expect($this->repo->locales())->toBe(['de', 'en', 'fr']);
});

it('only filtresini dosya adı, tam yol ve dizin öneki olarak kabul eder', function () {
    $this->writeLangFile('en/auth.php', ['a' => 'b']);
    $this->writeLangFile('en/admin/users.php', ['a' => 'b']);

    expect($this->repo->pairs('en', 'tr', 'auth.php'))->toHaveCount(1)
        ->and($this->repo->pairs('en', 'tr', 'auth'))->toHaveCount(1)
        ->and($this->repo->pairs('en', 'tr', 'en/admin'))->toHaveCount(1)
        ->and($this->repo->pairs('en', 'tr', 'en/auth.php'))->toHaveCount(1)
        ->and($this->repo->pairs('en', 'tr', 'yok.php'))->toHaveCount(0);
});

it('hedef dosya yoksa geçerli boş dosya bırakır', function () {
    $path = $this->langPath.'/tr/auth.php';

    $this->repo->ensureExists($path);

    expect($this->repo->read($path))->toBe([]);
});
