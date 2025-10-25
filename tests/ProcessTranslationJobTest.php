<?php

use DigitalCoreHub\LaravelAiTranslator\Jobs\ProcessTranslationJob;
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

    $job = new ProcessTranslationJob('en/messages.php', 'en', 'tr', 'openai');
    $job->handle(app(\DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager::class));

    expect($files->exists(base_path('lang/tr/messages.php')))->toBeTrue();

    // Check if report was created
    $reportPath = storage_path('logs/ai-translator-report.json');
    expect($files->exists($reportPath))->toBeTrue();

    $report = json_decode($files->get($reportPath), true);
    expect($report)->toBeArray();
    expect($report)->not->toBeEmpty();
    expect($report[0]['file'])->toBe('en/messages.php');
    expect($report[0]['status'])->toBe('completed');
})->uses(TestCase::class);
