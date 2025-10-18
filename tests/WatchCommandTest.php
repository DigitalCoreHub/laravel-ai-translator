<?php

use DigitalCoreHub\LaravelAiTranslator\Jobs\ProcessTranslationJob;
use DigitalCoreHub\LaravelAiTranslator\Tests\TestCase;
use Illuminate\Support\Facades\Bus;

it('dispatches translation jobs when watch command detects changes', function () {
    Bus::fake();

    $files = app('files');
    $files->deleteDirectory(base_path('lang'));
    $files->makeDirectory(base_path('lang/en'), 0755, true, true);
    $files->put(base_path('lang/en/messages.php'), "<?php return ['welcome' => 'Hello'];");

    $this->artisan('ai:watch', [
        '--from' => 'en',
        '--to' => ['tr'],
        '--provider' => 'openai',
        '--once' => true,
    ])->assertExitCode(0);

    Bus::assertDispatched(ProcessTranslationJob::class, function (ProcessTranslationJob $job) {
        return $job->from === 'en'
            && $job->to === 'tr'
            && $job->relativePath === 'lang/tr/messages.php';
    });
})->uses(TestCase::class);
