<?php

declare(strict_types=1);

use DigitalCoreHub\LaravelAiTranslator\Exceptions\InvalidLocalePathException;
use DigitalCoreHub\LaravelAiTranslator\Translation\Files\PhpLocaleFile;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->driver = new PhpLocaleFile(new Filesystem);
    $this->path = $this->langPath.'/tr/messages.php';
});

/**
 * v0.x regresyonu: var_export + str_replace(')' => ']') çeviri metinlerini bozuyordu.
 */
it('parantez içeren metinleri bozmadan yazar', function () {
    $data = ['save' => 'Kaydet (zorunlu)', 'note' => 'Bak (1) ve (2)'];

    $this->driver->write($this->path, $data);

    expect($this->driver->read($this->path))->toBe($data)
        ->and(file_get_contents($this->path))->not->toContain('zorunlu]');
});

it('tırnak, ters bölü ve satır sonlarını doğru kaçışlar', function () {
    $data = [
        'apostrophe' => "It's a 'test'",
        'quotes' => 'He said "hello"',
        'backslash' => 'C:\\path\\to\\file',
        'newline' => "İlk satır\nİkinci satır",
        'dollar' => 'Toplam $100 ve {$var}',
        'unicode' => 'Ağrı Dağı — çığ 🚀',
    ];

    $this->driver->write($this->path, $data);

    expect($this->driver->read($this->path))->toBe($data);
});

it('placeholder ve html içeriğini olduğu gibi korur', function () {
    $data = [
        'greet' => 'Merhaba :name, :count mesajın var',
        'blade' => 'Toplam {{ $total }} adet',
        'html' => 'Devam etmek için <a href="/x?a=1&b=2">tıkla</a>',
        'sprintf' => '%s ürün, %1$s tekrar',
    ];

    $this->driver->write($this->path, $data);

    expect($this->driver->read($this->path))->toBe($data);
});

it('iç içe dizileri ve karışık tipleri korur', function () {
    $data = [
        'auth' => [
            'failed' => 'Kimlik bilgileri hatalı.',
            'throttle' => ['title' => 'Çok fazla deneme', 'retry' => 60],
        ],
        'list' => ['bir', 'iki', 'üç'],
        'flags' => ['on' => true, 'off' => false, 'none' => null],
        'ratio' => 1.5,
        'whole' => 2.0,
    ];

    $this->driver->write($this->path, $data);

    expect($this->driver->read($this->path))->toBe($data);
});

it('geçerli ve okunabilir php üretir', function () {
    $this->driver->write($this->path, ['a' => 'b', 'nested' => ['c' => 'd']]);

    $contents = file_get_contents($this->path);

    expect($contents)->toStartWith("<?php\n\nreturn [")
        ->and($contents)->toEndWith("];\n")
        ->and($contents)->toContain("    'a' => 'b',")
        ->and($contents)->toContain("        'c' => 'd',");
});

it('boş dizi için tek satır yazar', function () {
    $this->driver->write($this->path, []);

    expect(file_get_contents($this->path))->toBe("<?php\n\nreturn [];\n")
        ->and($this->driver->read($this->path))->toBe([]);
});

it('var olmayan dosya için boş dizi döner', function () {
    expect($this->driver->read($this->langPath.'/yok/olmayan.php'))->toBe([]);
});

it('dizi döndürmeyen dosyada anlamlı hata fırlatır', function () {
    $path = $this->writeLangFile('tr/bozuk.php', '<?php return "dizi degil";');

    $this->driver->read($path);
})->throws(InvalidLocalePathException::class, 'geçerli bir dil dosyası değil');

it('yazma sırasında geçici dosya bırakmaz', function () {
    $this->driver->write($this->path, ['a' => 'b']);

    $leftovers = glob($this->langPath.'/tr/*.tmp') ?: [];

    expect($leftovers)->toBe([]);
});
