<?php

namespace DigitalCoreHub\LaravelAiTranslator\Tests\Feature;

use DigitalCoreHub\LaravelAiTranslator\Services\TranslationWatcher;
use DigitalCoreHub\LaravelAiTranslator\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Queue;

class WatchModeTest extends TestCase
{
    protected TranslationWatcher $watcher;

    protected string $testPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testPath = storage_path('test-lang');
        $this->filesystem = new Filesystem;

        // Create test directory
        if (! $this->filesystem->isDirectory($this->testPath)) {
            $this->filesystem->makeDirectory($this->testPath, 0755, true);
        }

        $this->watcher = new TranslationWatcher(
            $this->filesystem,
            base_path(),
            [$this->testPath],
            'en',
            'tr',
            'openai'
        );
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
    public function it_can_detect_file_changes()
    {
        Queue::fake();

        // Create a test language file
        $enPath = $this->testPath.'/en';
        $this->filesystem->makeDirectory($enPath, 0755, true);

        $filePath = $enPath.'/test.php';
        $this->filesystem->put($filePath, "<?php\n\nreturn [\n    'hello' => 'Hello World',\n];\n");

        // Check for changes
        $this->watcher->checkForChanges();

        // Should dispatch a job for the file
        Queue::assertPushed(\DigitalCoreHub\LaravelAiTranslator\Jobs\ProcessTranslationJob::class);
    }

    /** @test */
    public function it_ignores_non_language_files()
    {
        Queue::fake();

        // Create a non-language file
        $filePath = $this->testPath.'/test.txt';
        $this->filesystem->put($filePath, 'This is not a language file');

        // Check for changes
        $this->watcher->checkForChanges();

        // Should not dispatch any jobs
        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_only_processes_source_language_files()
    {
        Queue::fake();

        // Create target language file (should be ignored)
        $trPath = $this->testPath.'/tr';
        $this->filesystem->makeDirectory($trPath, 0755, true);

        $filePath = $trPath.'/test.php';
        $this->filesystem->put($filePath, "<?php\n\nreturn [\n    'hello' => 'Merhaba Dünya',\n];\n");

        // Check for changes
        $this->watcher->checkForChanges();

        // Should not dispatch any jobs for target language files
        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_logs_file_changes()
    {
        $logFile = storage_path('logs/ai-translator-watch.log');

        // Ensure log directory exists
        if (! $this->filesystem->isDirectory(dirname($logFile))) {
            $this->filesystem->makeDirectory(dirname($logFile), 0755, true);
        }

        // Create a test language file
        $enPath = $this->testPath.'/en';
        $this->filesystem->makeDirectory($enPath, 0755, true);

        $filePath = $enPath.'/test.php';
        $this->filesystem->put($filePath, "<?php\n\nreturn [\n    'hello' => 'Hello World',\n];\n");

        // Check for changes
        $this->watcher->checkForChanges();

        // Check if log was created
        $this->assertTrue($this->filesystem->exists($logFile));

        $logContent = $this->filesystem->get($logFile);
        $this->assertStringContainsString('File change detected and queued for translation', $logContent);
    }

    /** @test */
    public function it_can_set_and_get_watch_paths()
    {
        $newPaths = ['/custom/path1', '/custom/path2'];

        $this->watcher->setWatchPaths($newPaths);

        $this->assertEquals($newPaths, $this->watcher->getWatchPaths());
    }

    /** @test */
    public function it_can_set_and_get_last_checked_times()
    {
        $times = ['file1.php' => 1234567890, 'file2.php' => 1234567891];

        $this->watcher->setLastCheckedTimes($times);

        $this->assertEquals($times, $this->watcher->getLastCheckedTimes());
    }
}
