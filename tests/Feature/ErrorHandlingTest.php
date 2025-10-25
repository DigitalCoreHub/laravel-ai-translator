<?php

namespace DigitalCoreHub\LaravelAiTranslator\Tests\Feature;

use DigitalCoreHub\LaravelAiTranslator\Jobs\ProcessTranslationJob;
use DigitalCoreHub\LaravelAiTranslator\Services\TranslationWatcher;
use DigitalCoreHub\LaravelAiTranslator\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class ErrorHandlingTest extends TestCase
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
    public function it_handles_watcher_with_invalid_paths()
    {
        $watcher = new TranslationWatcher(
            $this->filesystem,
            base_path(),
            ['/invalid/path', '/another/invalid/path'],
            'en',
            'tr',
            'openai'
        );
        
        // Should not throw any errors
        $watcher->checkForChanges();
        
        // Should not dispatch any jobs
        Queue::fake();
        $watcher->checkForChanges();
        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_handles_watcher_with_permission_denied()
    {
        // Create a file that we can't read
        $this->filesystem->put($this->testPath . '/en/test.php', "<?php\n\nreturn [\n    'hello' => 'Hello World',\n];\n");
        
        $watcher = new TranslationWatcher(
            $this->filesystem,
            base_path(),
            [$this->testPath],
            'en',
            'tr',
            'openai'
        );
        
        // Should handle gracefully even if there are permission issues
        $watcher->checkForChanges();
    }

    /** @test */
    public function it_handles_job_failure_gracefully()
    {
        // Mock a failing translation manager
        $this->mock(\DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager::class, function ($mock) {
            $mock->shouldReceive('translatePath')
                ->once()
                ->andThrow(new \Exception('Translation service unavailable'));
        });
        
        $job = new ProcessTranslationJob('en/test.php', 'en', 'tr', 'openai');
        
        // Should handle the exception
        $this->expectException(\Exception::class);
        $job->handle(app(\DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager::class));
    }

    /** @test */
    public function it_handles_job_failed_method()
    {
        $job = new ProcessTranslationJob('en/test.php', 'en', 'tr', 'openai');
        
        // Should handle failed method without errors
        $job->failed(new \Exception('Job failed'));
        
        // Check that error was logged
        $this->assertTrue(Storage::exists('ai-translator-sync.log'));
    }

    /** @test */
    public function it_handles_sync_command_with_invalid_arguments()
    {
        // Test with missing required arguments
        $this->artisan('ai:sync', [])
            ->assertExitCode(1);
    }

    /** @test */
    public function it_handles_sync_command_with_invalid_provider()
    {
        $this->artisan('ai:sync', [
            'from' => 'en',
            'to' => 'tr',
            '--provider' => 'invalid-provider',
            '--paths' => $this->testPath
        ])->assertExitCode(0);
    }

    /** @test */
    public function it_handles_watch_command_with_invalid_options()
    {
        $this->artisan('ai:watch', [
            '--from' => 'invalid-lang',
            '--to' => 'tr',
            '--provider' => 'openai'
        ])->assertExitCode(0);
    }

    /** @test */
    public function it_handles_corrupted_log_files()
    {
        // Create corrupted log files
        Storage::put('ai-translator-watch.log', 'corrupted log content');
        Storage::put('ai-translator-report.json', 'invalid json');
        
        // Should not throw errors when reading logs
        $watcher = new TranslationWatcher(
            $this->filesystem,
            base_path(),
            [$this->testPath],
            'en',
            'tr',
            'openai'
        );
        
        $watcher->checkForChanges();
    }

    /** @test */
    public function it_handles_disk_space_issues()
    {
        // Create a large file to simulate disk space issues
        $largeContent = str_repeat('x', 1024 * 1024); // 1MB
        $this->filesystem->put($this->testPath . '/en/large.php', "<?php\n\nreturn [\n    'content' => '{$largeContent}',\n];\n");
        
        $watcher = new TranslationWatcher(
            $this->filesystem,
            base_path(),
            [$this->testPath],
            'en',
            'tr',
            'openai'
        );
        
        // Should handle large files gracefully
        $watcher->checkForChanges();
    }

    /** @test */
    public function it_handles_concurrent_file_modifications()
    {
        // Create a file
        $this->filesystem->put($this->testPath . '/en/test.php', "<?php\n\nreturn [\n    'hello' => 'Hello World',\n];\n");
        
        $watcher = new TranslationWatcher(
            $this->filesystem,
            base_path(),
            [$this->testPath],
            'en',
            'tr',
            'openai'
        );
        
        // Simulate concurrent modifications
        for ($i = 0; $i < 10; $i++) {
            $this->filesystem->put($this->testPath . '/en/test.php', "<?php\n\nreturn [\n    'hello' => 'Hello World {$i}',\n];\n");
            $watcher->checkForChanges();
        }
        
        // Should handle concurrent modifications gracefully
        $this->assertTrue(true); // If we get here without errors, the test passes
    }

    /** @test */
    public function it_handles_memory_limits()
    {
        // Create many files to test memory limits
        for ($i = 0; $i < 1000; $i++) {
            $this->filesystem->put($this->testPath . "/en/test{$i}.php", "<?php\n\nreturn [\n    'message' => 'Test {$i}',\n];\n");
        }
        
        $watcher = new TranslationWatcher(
            $this->filesystem,
            base_path(),
            [$this->testPath],
            'en',
            'tr',
            'openai'
        );
        
        // Should handle many files without memory issues
        $watcher->checkForChanges();
    }

    /** @test */
    public function it_handles_network_timeouts()
    {
        // Mock a slow translation manager
        $this->mock(\DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager::class, function ($mock) {
            $mock->shouldReceive('translatePath')
                ->once()
                ->andThrow(new \Exception('Connection timeout'));
        });
        
        $this->filesystem->put($this->testPath . '/en/test.php', "<?php\n\nreturn [\n    'hello' => 'Hello World',\n];\n");
        
        $this->artisan('ai:sync', [
            'from' => 'en',
            'to' => 'tr',
            '--paths' => $this->testPath
        ])->assertExitCode(0);
    }

    /** @test */
    public function it_handles_invalid_file_permissions()
    {
        // Create a file with restricted permissions
        $this->filesystem->put($this->testPath . '/en/test.php', "<?php\n\nreturn [\n    'hello' => 'Hello World',\n];\n");
        
        // Try to make it read-only (this might not work on all systems)
        if (function_exists('chmod')) {
            chmod($this->testPath . '/en/test.php', 0444);
        }
        
        $watcher = new TranslationWatcher(
            $this->filesystem,
            base_path(),
            [$this->testPath],
            'en',
            'tr',
            'openai'
        );
        
        // Should handle permission issues gracefully
        $watcher->checkForChanges();
    }

    /** @test */
    public function it_handles_malformed_php_files()
    {
        // Create malformed PHP files
        $this->filesystem->put($this->testPath . '/en/malformed.php', "<?php\n\nreturn [\n    'hello' => 'Hello World',\n    // Missing closing bracket\n");
        
        $watcher = new TranslationWatcher(
            $this->filesystem,
            base_path(),
            [$this->testPath],
            'en',
            'tr',
            'openai'
        );
        
        // Should handle malformed files gracefully
        $watcher->checkForChanges();
    }

    /** @test */
    public function it_handles_empty_directories()
    {
        // Create empty directories
        $this->filesystem->makeDirectory($this->testPath . '/en/empty', 0755, true);
        
        $watcher = new TranslationWatcher(
            $this->filesystem,
            base_path(),
            [$this->testPath],
            'en',
            'tr',
            'openai'
        );
        
        // Should handle empty directories gracefully
        $watcher->checkForChanges();
    }

    /** @test */
    public function it_handles_symlinks()
    {
        // Create a symlink (if supported)
        if (function_exists('symlink')) {
            $this->filesystem->put($this->testPath . '/en/test.php', "<?php\n\nreturn [\n    'hello' => 'Hello World',\n];\n");
            
            try {
                symlink($this->testPath . '/en/test.php', $this->testPath . '/en/link.php');
            } catch (\Exception $e) {
                // Symlinks might not be supported on this system
                $this->markTestSkipped('Symlinks not supported on this system');
            }
            
            $watcher = new TranslationWatcher(
                $this->filesystem,
                base_path(),
                [$this->testPath],
                'en',
                'tr',
                'openai'
            );
            
            // Should handle symlinks gracefully
            $watcher->checkForChanges();
        } else {
            $this->markTestSkipped('Symlinks not supported on this system');
        }
    }
}
