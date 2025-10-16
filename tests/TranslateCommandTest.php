<?php

use DigitalCoreHub\LaravelAiTranslator\Services\TranslationCache;
use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use DigitalCoreHub\LaravelAiTranslator\Tests\Fakes\FailingProvider;
use DigitalCoreHub\LaravelAiTranslator\Tests\Fakes\FakeProvider;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->filesystem = new Filesystem;
    $this->langPath = base_path('lang');
    $this->logPath = storage_path('logs/ai-translator.log');

    $this->filesystem->deleteDirectory($this->langPath);
    $this->filesystem->deleteDirectory(dirname($this->logPath));
    $this->filesystem->makeDirectory($this->langPath, 0755, true);

    $this->app->instance(FakeProvider::class, new FakeProvider);
    $this->app->forgetInstance('ai-translator.providers');
    $this->app->forgetInstance(TranslationManager::class);
    $this->app->forgetInstance(TranslationCache::class);
});

it('translates missing keys into multiple target locales', function () {
    seedEnglishLanguageFiles();
    seedTurkishPartials();

    $this->artisan('ai:translate', ['from' => 'en', 'to' => ['tr', 'fr']])
        ->expectsOutput('en -> tr çeviri işlemi başlatılıyor…')
        ->expectsTable(
            ['File', 'Missing', 'Translated'],
            [
                ['lang/tr/messages.php', 2, 2],
                ['lang/tr.json', 1, 1],
            ]
        )
        ->expectsOutput('Total missing: 3 | Translated: 3')
        ->expectsOutput('✔ Çeviri işlemi tamamlandı!')
        ->expectsOutput('en -> fr çeviri işlemi başlatılıyor…')
        ->expectsTable(
            ['File', 'Missing', 'Translated'],
            [
                ['lang/fr/messages.php', 3, 3],
                ['lang/fr.json', 2, 2],
            ]
        )
        ->expectsOutput('Total missing: 5 | Translated: 5')
        ->expectsOutput('✔ Çeviri işlemi tamamlandı!')
        ->assertSuccessful();

    $provider = $this->app->make(FakeProvider::class);

    expect($provider->calls)->toHaveCount(8);

    $turkishMessages = require base_path('lang/tr/messages.php');
    $turkishJson = json_decode(file_get_contents(base_path('lang/tr.json')), true, 512, JSON_THROW_ON_ERROR);
    $frenchMessages = require base_path('lang/fr/messages.php');
    $frenchJson = json_decode(file_get_contents(base_path('lang/fr.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($turkishMessages['farewell'])->toBe('[en->tr] Goodbye')
        ->and($turkishMessages['nested']['html'])->toBe('[en->tr] <strong>Bold</strong> text')
        ->and($turkishJson['Logout'])->toBe('[en->tr] Logout');

    expect($frenchMessages['greeting'])->toBe('[en->fr] Hello')
        ->and($frenchMessages['nested']['html'])->toBe('[en->fr] <strong>Bold</strong> text')
        ->and($frenchJson['Login'])->toBe('[en->fr] Login')
        ->and($frenchJson['Logout'])->toBe('[en->fr] Logout');

    $log = file_get_contents($this->logPath);

    expect($log)
        ->toContain('from=en to=tr')
        ->and($log)->toContain('from=en to=fr');
});

it('previews translations without touching files in dry mode', function () {
    seedEnglishLanguageFiles();

    $this->artisan('ai:translate', ['from' => 'en', 'to' => ['tr'], '--dry' => true])
        ->expectsOutput('en -> tr çeviri işlemi başlatılıyor…')
        ->expectsOutput('Dry run: Çeviriler dosyalara yazılmadı.')
        ->expectsOutput('  lang/tr/messages.php:')
        ->expectsOutput('  lang/tr.json:')
        ->expectsTable(
            ['File', 'Missing', 'Translated'],
            [
                ['lang/tr/messages.php', 3, 3],
                ['lang/tr.json', 2, 2],
            ]
        )
        ->expectsOutput('Total missing: 5 | Translated: 5')
        ->expectsOutput('✔ Çeviri işlemi tamamlandı!')
        ->assertSuccessful();

    expect($this->filesystem->exists(base_path('lang/tr/messages.php')))->toBeFalse()
        ->and($this->filesystem->exists(base_path('lang/tr.json')))->toBeFalse();

    $provider = $this->app->make(FakeProvider::class);

    expect($provider->calls)->toHaveCount(5);

    $log = file_get_contents($this->logPath);

    expect($log)->toContain('dry=true');
});

it('retranslates existing keys when forcing', function () {
    seedEnglishLanguageFiles();
    seedTurkishCompleted();

    $this->artisan('ai:translate', ['from' => 'en', 'to' => ['tr'], '--force' => true])
        ->expectsOutput('en -> tr çeviri işlemi başlatılıyor…')
        ->expectsTable(
            ['File', 'Missing', 'Translated'],
            [
                ['lang/tr/messages.php', 0, 3],
                ['lang/tr.json', 0, 2],
            ]
        )
        ->expectsOutput('Total missing: 0 | Translated: 5')
        ->expectsOutput('✔ Çeviri işlemi tamamlandı!')
        ->assertSuccessful();

    $provider = $this->app->make(FakeProvider::class);

    expect($provider->calls)->toHaveCount(5);

    $turkishMessages = require base_path('lang/tr/messages.php');
    $turkishJson = json_decode(file_get_contents(base_path('lang/tr.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($turkishMessages['greeting'])->toBe('[en->tr] Hello')
        ->and($turkishMessages['farewell'])->toBe('[en->tr] Goodbye')
        ->and($turkishJson['Login'])->toBe('[en->tr] Login')
        ->and($turkishJson['Logout'])->toBe('[en->tr] Logout');

    $log = file_get_contents($this->logPath);

    expect($log)->toContain('force=true');
});

it('uses cache to avoid duplicate translations', function () {
    config()->set('ai-translator.cache_enabled', true);
    config()->set('ai-translator.cache_driver', 'array');
    config()->set('cache.default', 'array');

    $this->app->forgetInstance(TranslationCache::class);
    $this->app->forgetInstance(TranslationManager::class);

    seedEnglishLanguageFiles();

    $provider = $this->app->make(FakeProvider::class);

    $this->artisan('ai:translate', ['from' => 'en', 'to' => ['tr']])
        ->assertSuccessful();

    expect($provider->calls)->toHaveCount(5);

    $this->artisan('ai:translate', ['from' => 'en', 'to' => ['tr'], '--force' => true])
        ->assertSuccessful();

    expect($provider->calls)->toHaveCount(5);
});

it('falls back to secondary provider when primary fails', function () {
    config()->set('ai-translator.providers.openai.class', FailingProvider::class);
    config()->set('ai-translator.providers.deepl.class', FakeProvider::class);
    config()->set('ai-translator.providers.google.class', FakeProvider::class);

    $failing = new FailingProvider;
    $backup = new FakeProvider;

    $this->app->instance(FailingProvider::class, $failing);
    $this->app->instance(FakeProvider::class, $backup);

    $this->app->forgetInstance('ai-translator.providers');
    $this->app->forgetInstance(TranslationManager::class);

    seedEnglishLanguageFiles();

    $this->artisan('ai:translate', ['from' => 'en', 'to' => ['tr'], '--dry' => true])
        ->assertSuccessful();

    expect($failing->calls)->toBe(5)
        ->and($backup->calls)->toBe(5);
});

it('clears cache when using the cache-clear flag', function () {
    config()->set('ai-translator.cache_enabled', true);
    config()->set('ai-translator.cache_driver', 'array');
    config()->set('cache.default', 'array');

    $this->app->forgetInstance(TranslationCache::class);
    $this->app->forgetInstance(TranslationManager::class);

    seedEnglishLanguageFiles();

    $this->artisan('ai:translate', ['from' => 'en', 'to' => ['tr'], '--cache-clear' => true])
        ->expectsOutput('Çeviri önbelleği temizlendi.')
        ->assertSuccessful();
});

it('writes a translation report json file', function () {
    seedEnglishLanguageFiles();

    $reportPath = storage_path('logs/ai-translator-report.json');

    $this->artisan('ai:translate', ['from' => 'en', 'to' => ['tr']])
        ->assertSuccessful();

    expect(file_exists($reportPath))->toBeTrue();

    $payload = json_decode(file_get_contents($reportPath), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toBeArray()
        ->and($payload[0]['files'][0]['primary_provider'])->toBe('openai')
        ->and($payload[0]['files'][0]['cache']['hit_rate'])->toBe(0.0);
});

function seedEnglishLanguageFiles(): void
{
    $filesystem = new Filesystem;
    $filesystem->makeDirectory(base_path('lang/en'), 0755, true, true);

    file_put_contents(base_path('lang/en/messages.php'), <<<'PHP'
<?php

return [
    'greeting' => 'Hello',
    'farewell' => 'Goodbye',
    'nested' => [
        'html' => '<strong>Bold</strong> text',
    ],
];
PHP);

    file_put_contents(base_path('lang/en.json'), json_encode([
        'Login' => 'Login',
        'Logout' => 'Logout',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function seedTurkishPartials(): void
{
    $filesystem = new Filesystem;
    $filesystem->makeDirectory(base_path('lang/tr'), 0755, true, true);

    file_put_contents(base_path('lang/tr/messages.php'), <<<'PHP'
<?php

return [
    'greeting' => 'Merhaba',
];
PHP);

    file_put_contents(base_path('lang/tr.json'), json_encode([
        'Login' => 'Giriş',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function seedTurkishCompleted(): void
{
    $filesystem = new Filesystem;
    $filesystem->makeDirectory(base_path('lang/tr'), 0755, true, true);

    file_put_contents(base_path('lang/tr/messages.php'), <<<'PHP'
<?php

return [
    'greeting' => 'Selam',
    'farewell' => 'Hoşçakal',
    'nested' => [
        'html' => '<strong>Kalın</strong> metin',
    ],
];
PHP);

    file_put_contents(base_path('lang/tr.json'), json_encode([
        'Login' => 'Giriş',
        'Logout' => 'Çıkış',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
