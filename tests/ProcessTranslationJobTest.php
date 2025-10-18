<?php

use DigitalCoreHub\LaravelAiTranslator\Jobs\ProcessTranslationJob;
use DigitalCoreHub\LaravelAiTranslator\Support\QueueMonitor;
use DigitalCoreHub\LaravelAiTranslator\Support\ReportStore;
use DigitalCoreHub\LaravelAiTranslator\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

it('processes translations and updates reports', function () {
    $files = app('files');
    $files->deleteDirectory(base_path('lang'));
    $files->makeDirectory(base_path('lang/en'), 0755, true, true);
    $files->put(base_path('lang/en/messages.php'), "<?php return ['welcome' => 'Hello'];");
    $files->delete(storage_path('logs/ai-translator-report.json'));
    $files->delete(storage_path('logs/ai-translator-queue.json'));

    Cache::flush();

    $job = new ProcessTranslationJob('en', 'tr', 'lang/tr/messages.php', 'openai');
    $job->handle(app(\DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager::class), app(ReportStore::class), app(QueueMonitor::class));

    expect($files->exists(base_path('lang/tr/messages.php')))->toBeTrue();
    expect($files->get(base_path('lang/tr/messages.php')))->toContain('[en->tr] Hello');

    $reports = app(ReportStore::class)->all();
    expect($reports)->not->toBeEmpty();

    $state = app(QueueMonitor::class)->state();
    expect($state['jobs'])->not->toBeEmpty();
    expect($state['jobs'][0]['status'])->toBe('completed');
})->uses(TestCase::class);
