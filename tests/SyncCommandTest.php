<?php

use DigitalCoreHub\LaravelAiTranslator\Jobs\ProcessTranslationJob;
use DigitalCoreHub\LaravelAiTranslator\Support\ReportStore;
use DigitalCoreHub\LaravelAiTranslator\Tests\TestCase;
use Illuminate\Support\Facades\Bus;

it('dispatches queued jobs from the sync command', function () {
    Bus::fake();

    $files = app('files');
    $files->deleteDirectory(base_path('lang'));
    $files->makeDirectory(base_path('lang/en'), 0755, true, true);
    $files->put(base_path('lang/en/messages.php'), "<?php return ['welcome' => 'Hello'];");

    $this->artisan('ai:sync', [
        'from' => 'en',
        'to' => ['tr'],
        '--provider' => 'openai',
        '--queue' => true,
    ])->assertExitCode(0);

    Bus::assertDispatched(ProcessTranslationJob::class, function (ProcessTranslationJob $job) {
        return $job->file === 'en/messages.php';
    });
})->uses(TestCase::class);

it('runs sync command immediately and records reports', function () {
    $files = app('files');
    $files->deleteDirectory(base_path('lang'));
    $files->makeDirectory(base_path('lang/en'), 0755, true, true);
    $files->put(base_path('lang/en/messages.php'), "<?php return ['welcome' => 'Hello'];");
    $files->delete(storage_path('logs/ai-translator-report.json'));

    $this->artisan('ai:sync', [
        'from' => 'en',
        'to' => ['tr'],
        '--provider' => 'openai',
    ])->assertExitCode(0);

    $reports = app(ReportStore::class)->all();
    expect($reports)->not->toBeEmpty();
})->uses(TestCase::class);
