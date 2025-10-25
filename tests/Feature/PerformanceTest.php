<?php

namespace DigitalCoreHub\LaravelAiTranslator\Tests\Feature;

use DigitalCoreHub\LaravelAiTranslator\Jobs\ProcessTranslationJob;
use DigitalCoreHub\LaravelAiTranslator\Services\TranslationWatcher;
use DigitalCoreHub\LaravelAiTranslator\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Queue;

class PerformanceTest extends TestCase
{
    protected string $testPath;

    protected Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testPath = storage_path('test-lang');
        $this->filesystem = new Filesystem;

        // Create test directory structure
        $this->filesystem->makeDirectory($this->testPath.'/en', 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        if ($this->filesystem->isDirectory($this->testPath)) {
            $this->filesystem->deleteDirectory($this->testPath);
        }

        parent::tearDown();
    }

    /** @test */
    public function it_handles_large_number_of_files_efficiently()
    {
        $startTime = microtime(true);

        // Create 1000 files
        for ($i = 0; $i < 1000; $i++) {
            $this->filesystem->put($this->testPath."/en/test{$i}.php", "<?php\n\nreturn [\n    'message' => 'Test {$i}',\n];\n");
        }

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

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete within reasonable time (5 seconds)
        $this->assertLessThan(5.0, $executionTime);

        // Should dispatch jobs for all files
        Queue::assertPushed(ProcessTranslationJob::class, 1000);
    }

    /** @test */
    public function it_handles_deep_directory_structures_efficiently()
    {
        $startTime = microtime(true);

        // Create deep directory structure
        $deepPath = $this->testPath.'/en/level1/level2/level3/level4/level5';
        $this->filesystem->makeDirectory($deepPath, 0755, true);

        // Create files in deep structure
        for ($i = 0; $i < 100; $i++) {
            $this->filesystem->put($deepPath."/test{$i}.php", "<?php\n\nreturn [\n    'message' => 'Deep test {$i}',\n];\n");
        }

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

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete within reasonable time (2 seconds)
        $this->assertLessThan(2.0, $executionTime);

        // Should dispatch jobs for all files
        Queue::assertPushed(ProcessTranslationJob::class, 100);
    }

    /** @test */
    public function it_handles_large_files_efficiently()
    {
        $startTime = microtime(true);

        // Create a large file (1MB)
        $largeContent = str_repeat('x', 1024 * 1024);
        $this->filesystem->put($this->testPath.'/en/large.php', "<?php\n\nreturn [\n    'content' => '{$largeContent}',\n];\n");

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

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete within reasonable time (1 second)
        $this->assertLessThan(1.0, $executionTime);

        // Should dispatch job for the large file
        Queue::assertPushed(ProcessTranslationJob::class, 1);
    }

    /** @test */
    public function it_handles_mixed_file_types_efficiently()
    {
        $startTime = microtime(true);

        // Create mixed file types
        for ($i = 0; $i < 500; $i++) {
            $this->filesystem->put($this->testPath."/en/test{$i}.php", "<?php\n\nreturn [\n    'message' => 'Test {$i}',\n];\n");
            $this->filesystem->put($this->testPath."/en/test{$i}.json", "{\"message\": \"Test {$i}\"}");
        }

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

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete within reasonable time (3 seconds)
        $this->assertLessThan(3.0, $executionTime);

        // Should dispatch jobs for all files
        Queue::assertPushed(ProcessTranslationJob::class, 1000);
    }

    /** @test */
    public function it_handles_concurrent_operations_efficiently()
    {
        $startTime = microtime(true);

        // Create files
        for ($i = 0; $i < 100; $i++) {
            $this->filesystem->put($this->testPath."/en/test{$i}.php", "<?php\n\nreturn [\n    'message' => 'Test {$i}',\n];\n");
        }

        $watcher = new TranslationWatcher(
            $this->filesystem,
            base_path(),
            [$this->testPath],
            'en',
            'tr',
            'openai'
        );

        Queue::fake();

        // Simulate concurrent operations
        for ($i = 0; $i < 10; $i++) {
            $watcher->checkForChanges();
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete within reasonable time (2 seconds)
        $this->assertLessThan(2.0, $executionTime);

        // Should only dispatch jobs once per file (not multiple times)
        Queue::assertPushed(ProcessTranslationJob::class, 100);
    }

    /** @test */
    public function it_handles_memory_usage_efficiently()
    {
        $initialMemory = memory_get_usage();

        // Create many files
        for ($i = 0; $i < 2000; $i++) {
            $this->filesystem->put($this->testPath."/en/test{$i}.php", "<?php\n\nreturn [\n    'message' => 'Test {$i}',\n];\n");
        }

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

        $finalMemory = memory_get_usage();
        $memoryUsed = $finalMemory - $initialMemory;

        // Should not use excessive memory (less than 50MB)
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed);
    }

    /** @test */
    public function it_handles_repeated_operations_efficiently()
    {
        // Create files
        for ($i = 0; $i < 100; $i++) {
            $this->filesystem->put($this->testPath."/en/test{$i}.php", "<?php\n\nreturn [\n    'message' => 'Test {$i}',\n];\n");
        }

        $watcher = new TranslationWatcher(
            $this->filesystem,
            base_path(),
            [$this->testPath],
            'en',
            'tr',
            'openai'
        );

        Queue::fake();

        $startTime = microtime(true);

        // Run multiple times
        for ($i = 0; $i < 5; $i++) {
            $watcher->checkForChanges();
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete within reasonable time (1 second)
        $this->assertLessThan(1.0, $executionTime);

        // Should only dispatch jobs once per file (not multiple times)
        Queue::assertPushed(ProcessTranslationJob::class, 100);
    }

    /** @test */
    public function it_handles_sync_command_performance()
    {
        // Create files
        for ($i = 0; $i < 500; $i++) {
            $this->filesystem->put($this->testPath."/en/test{$i}.php", "<?php\n\nreturn [\n    'message' => 'Test {$i}',\n];\n");
        }

        $startTime = microtime(true);

        // Run sync command
        $this->artisan('ai:sync', [
            'from' => 'en',
            'to' => 'tr',
            '--paths' => $this->testPath,
        ])->assertExitCode(0);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete within reasonable time (10 seconds)
        $this->assertLessThan(10.0, $executionTime);
    }

    /** @test */
    public function it_handles_queue_performance()
    {
        // Mock the translation manager for performance testing
        $this->mock(\DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager::class, function ($mock) {
            $mock->shouldReceive('translatePath')
                ->times(100)
                ->andReturn([
                    'file' => 'test.php',
                    'missing' => 1,
                    'translated' => 1,
                    'stats' => [],
                    'preview' => [],
                    'reviews' => [],
                    'report' => [],
                ]);
        });

        // Create files
        for ($i = 0; $i < 100; $i++) {
            $this->filesystem->put($this->testPath."/en/test{$i}.php", "<?php\n\nreturn [\n    'message' => 'Test {$i}',\n];\n");
        }

        $startTime = microtime(true);

        // Run sync with queue
        Queue::fake();
        $this->artisan('ai:sync', [
            'from' => 'en',
            'to' => 'tr',
            '--queue' => true,
            '--paths' => $this->testPath,
        ])->assertExitCode(0);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete within reasonable time (2 seconds)
        $this->assertLessThan(2.0, $executionTime);

        // Should queue jobs for all files
        Queue::assertPushed(ProcessTranslationJob::class, 100);
    }

    /** @test */
    public function it_handles_large_translation_jobs_efficiently()
    {
        // Mock a slow translation manager
        $this->mock(\DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager::class, function ($mock) {
            $mock->shouldReceive('translatePath')
                ->once()
                ->andReturn([
                    'file' => 'test.php',
                    'missing' => 1000,
                    'translated' => 1000,
                    'stats' => [
                        'providers' => ['openai' => 1000],
                        'cache_hits' => 0,
                        'cache_misses' => 1000,
                        'duration' => 30.0,
                    ],
                    'preview' => [],
                    'reviews' => [],
                    'report' => [],
                ]);
        });

        $this->filesystem->put($this->testPath.'/en/test.php', "<?php\n\nreturn [\n    'hello' => 'Hello World',\n];\n");

        $startTime = microtime(true);

        $job = new ProcessTranslationJob('en/test.php', 'en', 'tr', 'openai');
        $job->handle(app(\DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager::class));

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete within reasonable time (5 seconds)
        $this->assertLessThan(5.0, $executionTime);
    }
}
