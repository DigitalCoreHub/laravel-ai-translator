<?php

namespace DigitalCoreHub\LaravelAiTranslator\Tests\Feature;

use DigitalCoreHub\LaravelAiTranslator\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Queue;

class SyncCommandTest extends TestCase
{
    protected string $testPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testPath = storage_path('test-lang');
        $this->filesystem = new Filesystem();
        
        // Create test directory structure
        $this->filesystem->makeDirectory($this->testPath . '/en', 0755, true);
        $this->filesystem->makeDirectory($this->testPath . '/tr', 0755, true);
        
        // Create test language files
        $this->filesystem->put($this->testPath . '/en/test.php', "<?php\n\nreturn [\n    'hello' => 'Hello World',\n    'goodbye' => 'Goodbye World',\n];\n");
        $this->filesystem->put($this->testPath . '/en/auth.php', "<?php\n\nreturn [\n    'login' => 'Login',\n    'logout' => 'Logout',\n];\n");
        $this->filesystem->put($this->testPath . '/tr/test.php', "<?php\n\nreturn [\n    'hello' => 'Merhaba Dünya',\n];\n");
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
    public function it_can_sync_with_queue_mode()
    {
        Queue::fake();
        
        $this->artisan('ai:sync', [
            'from' => 'en',
            'to' => 'tr',
            '--queue' => true,
            '--paths' => $this->testPath
        ])
        ->assertExitCode(0);
        
        // Should queue jobs for each file
        Queue::assertPushed(\DigitalCoreHub\LaravelAiTranslator\Jobs\ProcessTranslationJob::class, 2);
    }

    /** @test */
    public function it_can_sync_with_direct_mode()
    {
        // Mock the translation manager
        $this->mock(\DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager::class, function ($mock) {
            $mock->shouldReceive('translatePath')
                ->twice()
                ->andReturn([
                    'file' => 'test.php',
                    'missing' => 1,
                    'translated' => 1,
                    'stats' => [],
                    'preview' => [],
                    'reviews' => [],
                    'report' => []
                ]);
        });
        
        $this->artisan('ai:sync', [
            'from' => 'en',
            'to' => 'tr',
            '--paths' => $this->testPath
        ])
        ->assertExitCode(0);
    }

    /** @test */
    public function it_validates_required_arguments()
    {
        $this->expectException(\RuntimeException::class);
        
        $this->artisan('ai:sync', [
            'from' => 'en'
            // Missing 'to' argument
        ]);
    }

    /** @test */
    public function it_handles_multiple_target_languages()
    {
        Queue::fake();
        
        $this->artisan('ai:sync', [
            'from' => 'en',
            'to' => ['tr', 'es'],
            '--queue' => true,
            '--paths' => $this->testPath
        ])
        ->assertExitCode(0);
        
        // Should queue jobs for each file and each target language
        Queue::assertPushed(\DigitalCoreHub\LaravelAiTranslator\Jobs\ProcessTranslationJob::class, 4);
    }

    /** @test */
    public function it_uses_custom_provider()
    {
        Queue::fake();
        
        $this->artisan('ai:sync', [
            'from' => 'en',
            'to' => 'tr',
            '--queue' => true,
            '--provider' => 'deepl',
            '--paths' => $this->testPath
        ])
        ->assertExitCode(0);
        
        Queue::assertPushed(\DigitalCoreHub\LaravelAiTranslator\Jobs\ProcessTranslationJob::class, function ($job) {
            return $job->provider === 'deepl';
        });
    }

    /** @test */
    public function it_handles_force_option()
    {
        // Mock the translation manager
        $this->mock(\DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager::class, function ($mock) {
            $mock->shouldReceive('translatePath')
                ->twice()
                ->withArgs(function ($file, $from, $to, $progress, $force, $provider) {
                    return $force === true;
                })
                ->andReturn([
                    'file' => 'test.php',
                    'missing' => 0,
                    'translated' => 2,
                    'stats' => [],
                    'preview' => [],
                    'reviews' => [],
                    'report' => []
                ]);
        });
        
        $this->artisan('ai:sync', [
            'from' => 'en',
            'to' => 'tr',
            '--force' => true,
            '--paths' => $this->testPath
        ])
        ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_missing_source_directory()
    {
        $this->artisan('ai:sync', [
            'from' => 'en',
            'to' => 'tr',
            '--paths' => '/non-existent-path'
        ])
        ->assertExitCode(0)
        ->expectsOutput('No language files found for en in the specified paths.');
    }

    /** @test */
    public function it_logs_sync_operations()
    {
        $this->artisan('ai:sync', [
            'from' => 'en',
            'to' => 'tr',
            '--paths' => $this->testPath
        ])
        ->assertExitCode(0);
        
        // Check if sync log was created
        $this->assertTrue(file_exists(storage_path('logs/ai-translator-sync.log')));
    }
}
