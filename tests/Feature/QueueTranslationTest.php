<?php

namespace DigitalCoreHub\LaravelAiTranslator\Tests\Feature;

use DigitalCoreHub\LaravelAiTranslator\Jobs\ProcessTranslationJob;
use DigitalCoreHub\LaravelAiTranslator\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class QueueTranslationTest extends TestCase
{
    protected string $testPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testPath = storage_path('test-lang');
        $this->filesystem = new Filesystem;

        // Create test directory structure
        $this->filesystem->makeDirectory($this->testPath.'/en', 0755, true);
        $this->filesystem->makeDirectory($this->testPath.'/tr', 0755, true);

        // Create test language files
        $this->filesystem->put($this->testPath.'/en/test.php', "<?php\n\nreturn [\n    'hello' => 'Hello World',\n    'goodbye' => 'Goodbye World',\n];\n");
        $this->filesystem->put($this->testPath.'/tr/test.php', "<?php\n\nreturn [\n    'hello' => 'Merhaba Dünya',\n];\n");
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        if ($this->filesystem->isDirectory($this->testPath)) {
            $this->filesystem->deleteDirectory($this->testPath);
        }

        // Clean up log files
        Storage::delete(['ai-translator-sync.log', 'ai-translator-report.json']);

        parent::tearDown();
    }

    /** @test */
    public function it_can_dispatch_translation_job()
    {
        Queue::fake();

        ProcessTranslationJob::dispatch(
            'en/test.php',
            'en',
            'tr',
            'openai'
        );

        Queue::assertPushed(ProcessTranslationJob::class, function ($job) {
            return $job->file === 'en/test.php' &&
                   $job->from === 'en' &&
                   $job->to === 'tr' &&
                   $job->provider === 'openai';
        });
    }

    /** @test */
    public function it_can_process_translation_job()
    {
        // Mock the translation manager
        $this->mock(\DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager::class, function ($mock) {
            $mock->shouldReceive('translatePath')
                ->once()
                ->with('en/test.php', 'en', 'tr', null, false, 'openai')
                ->andReturn([
                    'file' => 'en/test.php',
                    'missing' => 1,
                    'translated' => 1,
                    'stats' => [
                        'providers' => ['openai' => 1],
                        'cache_hits' => 0,
                        'cache_misses' => 1,
                        'duration' => 1.5,
                    ],
                    'preview' => ['goodbye' => 'Güle güle Dünya'],
                    'reviews' => [],
                    'report' => [],
                ]);
        });

        $job = new ProcessTranslationJob('en/test.php', 'en', 'tr', 'openai');

        // Execute the job
        $job->handle(app(\DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager::class));

        // Check if report was created
        $reportPath = storage_path('logs/ai-translator-report.json');
        $this->assertTrue(file_exists($reportPath));

        $report = json_decode(file_get_contents($reportPath), true);
        $this->assertIsArray($report);
        $this->assertNotEmpty($report);

        // Check the last entry (most recent)
        $lastEntry = end($report);
        $this->assertEquals('en/test.php', $lastEntry['file']);
        $this->assertEquals('completed', $lastEntry['status']);
    }

    /** @test */
    public function it_logs_job_execution()
    {
        // Mock the translation manager
        $this->mock(\DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager::class, function ($mock) {
            $mock->shouldReceive('translatePath')
                ->once()
                ->andReturn([
                    'file' => 'en/test.php',
                    'missing' => 1,
                    'translated' => 1,
                    'stats' => [],
                    'preview' => [],
                    'reviews' => [],
                    'report' => [],
                ]);
        });

        $job = new ProcessTranslationJob('en/test.php', 'en', 'tr', 'openai');

        // Execute the job
        $job->handle(app(\DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager::class));

        // Check if sync log was created
        $this->assertTrue(Storage::exists('ai-translator-sync.log'));

        $logContent = Storage::get('ai-translator-sync.log');
        $this->assertStringContainsString('Job started', $logContent);
        $this->assertStringContainsString('Job completed successfully', $logContent);
    }

    /** @test */
    public function it_handles_job_failure()
    {
        $job = new ProcessTranslationJob('en/test.php', 'en', 'tr', 'openai');

        // Mock a failing translation manager
        $this->mock(\DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager::class, function ($mock) {
            $mock->shouldReceive('translatePath')
                ->once()
                ->andThrow(new \Exception('Translation failed'));
        });

        // Execute the job and expect it to throw
        $this->expectException(\Exception::class);
        $job->handle(app(\DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager::class));
    }

    /** @test */
    public function it_has_correct_job_tags()
    {
        $job = new ProcessTranslationJob('en/test.php', 'en', 'tr', 'openai');

        $tags = $job->tags();

        $this->assertContains('ai-translator', $tags);
        $this->assertContains('file:en/test.php', $tags);
        $this->assertContains('from:en', $tags);
        $this->assertContains('to:tr', $tags);
        $this->assertContains('provider:openai', $tags);
    }
}
