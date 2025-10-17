<?php

use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Dashboard;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Logs;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Settings;
use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use DigitalCoreHub\LaravelAiTranslator\Tests\Fakes\FakeProvider;
use Illuminate\Filesystem\Filesystem;

it('counts missing translations on the dashboard', function () {
    $filesystem = new Filesystem;
    $filesystem->deleteDirectory(storage_path('logs'));
    $filesystem->deleteDirectory(base_path('lang'));

    seedEnglishLanguageFiles();
    seedTurkishPartials();

    $dashboard = new Dashboard;
    $dashboard->mount();
    $dashboard->from = 'en';
    $dashboard->to = 'tr';
    $dashboard->refreshEntries();

    $missing = collect($dashboard->entries)
        ->where('status', 'missing')
        ->count();

    expect($missing)->toBe(3);
});

it('writes translations when triggered from the dashboard', function () {
    $filesystem = new Filesystem;
    $filesystem->deleteDirectory(storage_path('logs'));
    $filesystem->deleteDirectory(base_path('lang'));

    seedEnglishLanguageFiles();

    $dashboard = new Dashboard;
    $dashboard->mount();
    $dashboard->from = 'en';
    $dashboard->to = 'tr';
    $dashboard->translateMissing();

    $turkishMessages = require base_path('lang/tr/messages.php');

    expect($turkishMessages['farewell'] ?? null)->toBe('[en->tr] Goodbye')
        ->and($dashboard->progressLabel)->toContain('Tamamlandı');
});

it('successfully tests provider connections from settings', function () {
    $filesystem = new Filesystem;
    $filesystem->deleteDirectory(storage_path('logs'));
    $filesystem->deleteDirectory(base_path('lang'));

    seedEnglishLanguageFiles();
    seedTurkishPartials();

    config()->set('ai-translator.provider', 'deepseek');
    config()->set('ai-translator.providers.deepseek.class', FakeProvider::class);

    $this->app->instance(FakeProvider::class, new FakeProvider);
    $this->app->forgetInstance('ai-translator.providers');
    $this->app->forgetInstance(TranslationManager::class);

    $settings = new Settings;
    $settings->mount();
    $settings->from = 'en';
    $settings->to = 'tr';
    $settings->testConnection('deepseek');

    expect($settings->status['deepseek']['ok'])->toBeTrue()
        ->and($settings->status['deepseek']['message'])->toContain('✅');
});

it('reads the latest translation statistics from logs', function () {
    $filesystem = new Filesystem;
    $filesystem->deleteDirectory(storage_path('logs'));
    $filesystem->makeDirectory(storage_path('logs'), 0755, true);

    $payload = [[
        'from' => 'en',
        'to' => 'tr',
        'provider' => 'openai',
        'executed_at' => now()->toIso8601String(),
        'files' => [[
            'file' => 'lang/tr/messages.php',
            'translated' => 2,
            'missing' => 1,
            'primary_provider' => 'openai',
            'duration_ms' => 123.45,
        ]],
    ]];

    file_put_contents(
        storage_path('logs/ai-translator-report.json'),
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    $logs = new Logs;
    $logs->mount();

    expect($logs->entries)->toHaveCount(1)
        ->and($logs->entries[0]['file'])->toBe('lang/tr/messages.php')
        ->and($logs->entries[0]['translated'])->toBe(2);
});
