<?php

declare(strict_types=1);

use DigitalCoreHub\LaravelAiTranslator\Jobs\TranslateFileJob;
use DigitalCoreHub\LaravelAiTranslator\Translation\TranslationWatcher;
use DigitalCoreHub\LaravelAiTranslator\Translation\Translator;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    $this->watcher = fn (array $targets = ['tr'], string $queue = 'ai-translations') => new TranslationWatcher(
        translator: app(Translator::class),
        from: 'en',
        targets: $targets,
        queue: $queue,
    );
});

it('değişen kaynak dosyayı kuyruğa alır', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);

    expect(($this->watcher)()->tick())->toBe(1);

    Queue::assertPushed(
        TranslateFileJob::class,
        fn (TranslateFileJob $job) => $job->file === 'en/auth.php' && $job->to === 'tr'
    );
});

it('prime sonrası değişmemiş dosyayı kuyruğa almaz', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);

    expect(($this->watcher)()->prime()->tick())->toBe(0);

    Queue::assertNothingPushed();
});

it('dosya tekrar değişirse yeniden kuyruğa alır', function () {
    $path = $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);

    $watcher = ($this->watcher)()->prime();

    touch($path, time() + 10);

    expect($watcher->tick())->toBe(1);
});

it('her hedef dil için ayrı iş üretir', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);

    expect(($this->watcher)(['tr', 'de', 'fr'])->tick())->toBe(3);

    Queue::assertPushed(TranslateFileJob::class, 3);
});

it('verilen kuyruk adını kullanır', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);

    ($this->watcher)(['tr'], 'ceviriler')->tick();

    Queue::assertPushed(TranslateFileJob::class, fn (TranslateFileJob $job) => $job->queue === 'ceviriler');
});

it('değişiklik geri çağrısını tetikler', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);

    $seen = [];

    ($this->watcher)()->onChange(function (string $file, int $jobs) use (&$seen) {
        $seen[$file] = $jobs;
    })->tick();

    expect($seen)->toBe(['en/auth.php' => 1]);
});

it('once ile tek tur çalışıp çıkar', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);
    $this->writeLangFile('tr/auth.php', []);

    $this->artisan('ai:watch --from=en --to=tr --once')->assertSuccessful();

    Queue::assertPushed(TranslateFileJob::class, 1);
});

it('hedef dil yoksa anlamlı hata verir', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);

    $this->artisan('ai:watch --from=en --once')
        ->expectsOutputToContain('İzlenecek hedef dil yok')
        ->assertExitCode(2);
});
