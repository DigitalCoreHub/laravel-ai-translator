<?php

declare(strict_types=1);

use DigitalCoreHub\LaravelAiTranslator\Jobs\TranslateFileJob;
use DigitalCoreHub\LaravelAiTranslator\Support\ReportStore;
use DigitalCoreHub\LaravelAiTranslator\Translation\Translator;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => config()->set('ai-translator.providers.null.prefix', '[tr] '));

it('eksik anahtarları çevirip hedef dosyaya yazar', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed', 'password' => 'Wrong password']);

    $this->artisan('ai:translate en tr')->assertSuccessful();

    expect($this->readLangFile('tr/auth.php'))->toBe([
        'failed' => '[tr] Failed',
        'password' => '[tr] Wrong password',
    ]);
});

it('mevcut çevirilere dokunmaz, sadece eksikleri tamamlar', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed', 'password' => 'Wrong password']);
    $this->writeLangFile('tr/auth.php', ['failed' => 'Elle yazılmış çeviri']);

    $this->artisan('ai:translate en tr')->assertSuccessful();

    expect($this->readLangFile('tr/auth.php'))->toBe([
        'failed' => 'Elle yazılmış çeviri',
        'password' => '[tr] Wrong password',
    ]);
});

it('boş değerleri eksik sayar', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);
    $this->writeLangFile('tr/auth.php', ['failed' => '   ']);

    $this->artisan('ai:translate en tr')->assertSuccessful();

    expect($this->readLangFile('tr/auth.php')['failed'])->toBe('[tr] Failed');
});

it('force ile mevcut çevirileri de yeniler', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);
    $this->writeLangFile('tr/auth.php', ['failed' => 'Eski çeviri']);

    $this->artisan('ai:translate en tr --force')->assertSuccessful();

    expect($this->readLangFile('tr/auth.php')['failed'])->toBe('[tr] Failed');
});

it('dry çalıştırmada hiçbir dosyaya yazmaz', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);

    $this->artisan('ai:translate en tr --dry')->assertSuccessful();

    expect(file_exists($this->langPath.'/tr/auth.php'))->toBeFalse();
});

it('iç içe anahtarları yapısını koruyarak çevirir', function () {
    $this->writeLangFile('en/messages.php', [
        'nav' => ['home' => 'Home', 'about' => 'About'],
        'title' => 'Welcome',
    ]);

    $this->artisan('ai:translate en tr')->assertSuccessful();

    expect($this->readLangFile('tr/messages.php'))->toBe([
        'nav' => ['home' => '[tr] Home', 'about' => '[tr] About'],
        'title' => '[tr] Welcome',
    ]);
});

it('json dil dosyalarını da işler', function () {
    $this->writeLangFile('en.json', ['Save changes' => 'Save changes']);

    $this->artisan('ai:translate en tr')->assertSuccessful();

    expect($this->readLangFile('tr.json'))->toBe(['Save changes' => '[tr] Save changes']);
});

it('metin olmayan değerleri sağlayıcıya göndermez', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed', 'retry_after' => 60, 'enabled' => true]);

    $this->artisan('ai:translate en tr')->assertSuccessful();

    // Sayı ve bool değerler çeviriye gitmez; hedef dosyada da yer almazlar.
    expect($this->readLangFile('tr/auth.php'))->toBe(['failed' => '[tr] Failed']);
});

it('birden fazla hedef dili tek koşuda halleder', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);

    $this->artisan('ai:translate en tr de')->assertSuccessful();

    expect($this->readLangFile('tr/auth.php')['failed'])->toBe('[tr] Failed')
        ->and($this->readLangFile('de/auth.php')['failed'])->toBe('[tr] Failed');
});

it('hedef verilmezse dizindeki kaynak dışı dilleri hedef alır', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);
    $this->writeLangFile('tr/auth.php', []);
    $this->writeLangFile('de/auth.php', []);

    $this->artisan('ai:translate en')->assertSuccessful();

    expect($this->readLangFile('tr/auth.php')['failed'])->toBe('[tr] Failed')
        ->and($this->readLangFile('de/auth.php')['failed'])->toBe('[tr] Failed');
});

it('only ile tek dosyaya odaklanır', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);
    $this->writeLangFile('en/validation.php', ['required' => 'Required']);

    $this->artisan('ai:translate en tr --only=auth.php')->assertSuccessful();

    expect($this->readLangFile('tr/auth.php')['failed'])->toBe('[tr] Failed')
        ->and(file_exists($this->langPath.'/tr/validation.php'))->toBeFalse();
});

it('kaynak dille aynı hedefi atlar', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);

    $this->artisan('ai:translate en en')
        ->expectsOutputToContain('kaynak dille aynı')
        ->assertSuccessful();
});

it('anahtar sırasını kaynak dosyaya göre korur', function () {
    $this->writeLangFile('en/auth.php', ['zebra' => 'Zebra', 'apple' => 'Apple', 'mango' => 'Mango']);

    $this->artisan('ai:translate en tr')->assertSuccessful();

    expect(array_keys($this->readLangFile('tr/auth.php')))->toBe(['zebra', 'apple', 'mango']);
});

it('queue ile dosya başına bir iş kuyruğa alır', function () {
    Queue::fake();
    config()->set('ai-translator.queue.name', 'ceviriler');

    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);
    $this->writeLangFile('en/validation.php', ['required' => 'Required']);

    $this->artisan('ai:translate en tr --queue')->assertSuccessful();

    Queue::assertPushed(TranslateFileJob::class, 2);

    // v0.x regresyonu: iş config'teki kuyruğa değil default'a gidiyordu.
    Queue::assertPushed(TranslateFileJob::class, fn (TranslateFileJob $job) => $job->queue === 'ceviriler');
});

it('kuyruğa aldığı iş çalıştığında dosyayı yazar', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);

    (new TranslateFileJob(file: 'en/auth.php', from: 'en', to: 'tr'))
        ->handle(app(Translator::class), app(ReportStore::class));

    expect($this->readLangFile('tr/auth.php')['failed'])->toBe('[tr] Failed');
});
