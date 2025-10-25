<?php

namespace DigitalCoreHub\LaravelAiTranslator\Tests\Feature;

use DigitalCoreHub\LaravelAiTranslator\Jobs\ProcessTranslationJob;
use DigitalCoreHub\LaravelAiTranslator\Services\TranslationWatcher;
use DigitalCoreHub\LaravelAiTranslator\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class IntegrationTest extends TestCase
{
    protected string $testPath;
    protected Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testPath = storage_path('test-lang');
        $this->filesystem = new Filesystem();
        
        // Create test directory structure
        $this->filesystem->makeDirectory($this->testPath . '/en', 0755, true);
        $this->filesystem->makeDirectory($this->testPath . '/tr', 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        if ($this->filesystem->isDirectory($this->testPath)) {
            $this->filesystem->deleteDirectory($this->testPath);
        }
        
        // Clean up log files
        Storage::delete(['ai-translator-watch.log', 'ai-translator-sync.log', 'ai-translator-report.json']);
        
        parent::tearDown();
    }

    /** @test */
    public function it_handles_complete_watch_and_translate_workflow()
    {
        Queue::fake();
        
        // Create initial language file
        $this->filesystem->put($this->testPath . '/en/test.php', "<?php\n\nreturn [\n    'hello' => 'Hello World',\n];\n");
        
        // Create watcher
        $watcher = new TranslationWatcher(
            $this->filesystem,
            base_path(),
            [$this->testPath],
            'en',
            'tr',
            'openai'
        );
        
        // Check for changes
        $watcher->checkForChanges();
        
        // Should dispatch a job
        Queue::assertPushed(ProcessTranslationJob::class, function ($job) {
            return $job->file === 'en/test.php' && 
                   $job->from === 'en' && 
                   $job->to === 'tr' && 
                   $job->provider === 'openai';
        });
        
        // Check that watch log was created
        $this->assertTrue(Storage::exists('ai-translator-watch.log'));
        
        $logContent = Storage::get('ai-translator-watch.log');
        $this->assertStringContains('File change detected and queued for translation', $logContent);
    }

    /** @test */
    public function it_handles_complete_sync_workflow()
    {
        // Mock the translation manager
        $this->mock(\DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager::class, function ($mock) {
            $mock->shouldReceive('translatePath')
                ->twice()
                ->andReturn([
                    'file' => 'test.php',
                    'missing' => 2,
                    'translated' => 2,
                    'stats' => [
                        'providers' => ['openai' => 2],
                        'cache_hits' => 0,
                        'cache_misses' => 2,
                        'duration' => 3.5
                    ],
                    'preview' => ['hello' => 'Merhaba Dünya', 'goodbye' => 'Güle güle Dünya'],
                    'reviews' => [],
                    'report' => []
                ]);
        });
        
        // Create test files
        $this->filesystem->put($this->testPath . '/en/test.php', "<?php\n\nreturn [\n    'hello' => 'Hello World',\n    'goodbye' => 'Goodbye World',\n];\n");
        $this->filesystem->put($this->testPath . '/en/auth.php', "<?php\n\nreturn [\n    'login' => 'Login',\n];\n");
        
        // Run sync command
        $this->artisan('ai:sync', [
            'from' => 'en',
            'to' => 'tr',
            '--paths' => $this->testPath
        ])->assertExitCode(0);
        
        // Check that sync log was created
        $this->assertTrue(file_exists(storage_path('logs/ai-translator-sync.log')));
        
        // Check that report was created
        $this->assertTrue(file_exists(storage_path('logs/ai-translator-report.json')));
        
        $report = json_decode(file_get_contents(storage_path('logs/ai-translator-report.json')), true);
        $this->assertIsArray($report);
        $this->assertNotEmpty($report);
    }

    /** @test */
    public function it_handles_queue_workflow_end_to_end()
    {
        // Mock the translation manager
        $this->mock(\DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager::class, function ($mock) {
            $mock->shouldReceive('translatePath')
                ->once()
                ->andReturn([
                    'file' => 'en/test.php',
                    'missing' => 1,
                    'translated' => 1,
                    'stats' => [
                        'providers' => ['openai' => 1],
                        'cache_hits' => 0,
                        'cache_misses' => 1,
                        'duration' => 2.0
                    ],
                    'preview' => ['hello' => 'Merhaba Dünya'],
                    'reviews' => [],
                    'report' => []
                ]);
        });
        
        // Create test file
        $this->filesystem->put($this->testPath . '/en/test.php', "<?php\n\nreturn [\n    'hello' => 'Hello World',\n];\n");
        
        // Dispatch job
        $job = new ProcessTranslationJob('en/test.php', 'en', 'tr', 'openai');
        $job->handle(app(\DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager::class));
        
        // Check that report was updated
        $this->assertTrue(file_exists(storage_path('logs/ai-translator-report.json')));
        
        $report = json_decode(file_get_contents(storage_path('logs/ai-translator-report.json')), true);
        $this->assertIsArray($report);
        $this->assertNotEmpty($report);
        
        $lastEntry = end($report);
        $this->assertEquals('en/test.php', $lastEntry['file']);
        $this->assertEquals('completed', $lastEntry['status']);
        $this->assertEquals(1, $lastEntry['translated']);
    }

    /** @test */
    public function it_handles_error_scenarios_gracefully()
    {
        // Test watcher with non-existent directory
        $watcher = new TranslationWatcher(
            $this->filesystem,
            base_path(),
            ['/non-existent-path'],
            'en',
            'tr',
            'openai'
        );
        
        // Should not throw any errors
        $watcher->checkForChanges();
        
        // Test sync with no files
        $this->artisan('ai:sync', [
            'from' => 'en',
            'to' => 'tr',
            '--paths' => '/non-existent-path'
        ])->assertExitCode(0);
    }

    /** @test */
    public function it_handles_concurrent_operations()
    {
        Queue::fake();
        
        // Create multiple files
        for ($i = 1; $i <= 5; $i++) {
            $this->filesystem->put($this->testPath . "/en/test{$i}.php", "<?php\n\nreturn [\n    'message' => 'Test {$i}',\n];\n");
        }
        
        // Create watcher
        $watcher = new TranslationWatcher(
            $this->filesystem,
            base_path(),
            [$this->testPath],
            'en',
            'tr',
            'openai'
        );
        
        // Check for changes multiple times
        for ($i = 0; $i < 3; $i++) {
            $watcher->checkForChanges();
        }
        
        // Should dispatch jobs for all files
        Queue::assertPushed(ProcessTranslationJob::class, 5);
    }

    /** @test */
    public function it_maintains_data_consistency_across_operations()
    {
        // Create initial state
        $this->filesystem->put($this->testPath . '/en/test.php', "<?php\n\nreturn [\n    'hello' => 'Hello World',\n];\n");
        
        // Run sync
        $this->artisan('ai:sync', [
            'from' => 'en',
            'to' => 'tr',
            '--paths' => $this->testPath
        ])->assertExitCode(0);
        
        // Modify file
        $this->filesystem->put($this->testPath . '/en/test.php', "<?php\n\nreturn [\n    'hello' => 'Hello World',\n    'goodbye' => 'Goodbye World',\n];\n");
        
        // Run watcher
        $watcher = new TranslationWatcher(
            $this->filesystem,
            base_path(),
            [$this->testPath],
            'en',
            'tr',
            'openai'
        );
        
        Queue::fake();
        $watcher->checkForChanges();
        
        // Should detect the change and queue a job
        Queue::assertPushed(ProcessTranslationJob::class);
    }

    /** @test */
    public function it_handles_large_scale_operations()
    {
        // Create many files
        for ($i = 1; $i <= 100; $i++) {
            $this->filesystem->put($this->testPath . "/en/test{$i}.php", "<?php\n\nreturn [\n    'message' => 'Test {$i}',\n];\n");
        }
        
        // Run sync with queue
        Queue::fake();
        
        $this->artisan('ai:sync', [
            'from' => 'en',
            'to' => 'tr',
            '--queue' => true,
            '--paths' => $this->testPath
        ])->assertExitCode(0);
        
        // Should queue jobs for all files
        Queue::assertPushed(ProcessTranslationJob::class, 100);
    }

    /** @test */
    public function it_handles_mixed_file_types()
    {
        // Create both PHP and JSON files
        $this->filesystem->put($this->testPath . '/en/test.php', "<?php\n\nreturn [\n    'hello' => 'Hello World',\n];\n");
        $this->filesystem->put($this->testPath . '/en/test.json', '{"hello": "Hello World"}');
        
        Queue::fake();
        
        // Run sync
        $this->artisan('ai:sync', [
            'from' => 'en',
            'to' => 'tr',
            '--queue' => true,
            '--paths' => $this->testPath
        ])->assertExitCode(0);
        
        // Should queue jobs for both file types
        Queue::assertPushed(ProcessTranslationJob::class, 2);
    }
}
