<?php

namespace DigitalCoreHub\LaravelAiTranslator\Commands;

use DigitalCoreHub\LaravelAiTranslator\Jobs\ProcessTranslationJob;
use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use DigitalCoreHub\LaravelAiTranslator\Support\AiTranslatorLogger;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/**
 * Sync all language files by translating missing keys.
 * Eksik anahtarları çevirerek tüm dil dosyalarını senkronize eder.
 */
class SyncCommand extends Command
{
    /**
     * The console command signature.
     * Konsol komutunun imzası.
     */
    protected $signature = 'ai:sync
        {from : Source language code}
        {to* : Target language codes}
        {--queue : Process translations in the background using queue}
        {--provider= : Translation provider to use}
        {--force : Force retranslation of existing translations}
        {--paths= : Comma-separated list of paths to sync}';

    /**
     * The console command description.
     * Konsol komutunun açıklaması.
     */
    protected $description = 'Sync all language files by translating missing keys';

    public function __construct(
        protected TranslationManager $manager,
        protected Filesystem $filesystem
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     * Konsol komutunu çalıştırır.
     */
    public function handle(): int
    {
        $from = $this->argument('from');
        $targets = array_values(array_filter((array) $this->argument('to')));
        $useQueue = $this->option('queue');
        $provider = $this->option('provider') ?: config('ai-translator.provider', 'openai');
        $force = $this->option('force');
        $paths = $this->getSyncPaths();

        if (empty($targets)) {
            $this->error('At least one target language must be specified.');

            return self::INVALID;
        }

        $this->info("Starting sync for {$from} -> ".implode(', ', $targets));
        $this->info("Provider: {$provider}");
        $this->info('Mode: '.($useQueue ? 'Queue' : 'Direct'));
        $this->info('Paths: '.implode(', ', $paths));
        $this->newLine();

        // Ensure log directory exists
        $logDir = storage_path('logs');
        if (! $this->filesystem->isDirectory($logDir)) {
            $this->filesystem->makeDirectory($logDir, 0755, true);
        }

        $totalFiles = 0;
        $queuedJobs = 0;

        foreach ($targets as $to) {
            $this->info("Processing {$from} -> {$to}...");

            $files = $this->getLanguageFiles($paths, $from);
            $fileCount = count($files);
            $totalFiles += $fileCount;

            if ($fileCount === 0) {
                $this->warn("No language files found for {$from} in the specified paths.");

                continue;
            }

            $this->info("Found {$fileCount} files to process.");

            if ($useQueue) {
                $queuedJobs += $this->queueFiles($files, $from, $to, $provider);
                $this->info("Queued {$queuedJobs} translation jobs for {$from} -> {$to}");
            } else {
                $this->processFilesDirectly($files, $from, $to, $provider, $force);
            }

            $this->newLine();
        }

        if ($useQueue) {
            $this->info("Total: {$totalFiles} files, {$queuedJobs} jobs queued");
            $this->info("Run 'php artisan queue:work --queue=ai-translations' to process the queue.");
        } else {
            $this->info("Sync completed for {$totalFiles} files.");
        }

        return self::SUCCESS;
    }

    /**
     * Get the paths to sync.
     * Senkronize edilecek yolları döndürür.
     */
    protected function getSyncPaths(): array
    {
        $configuredPaths = $this->option('paths');

        if ($configuredPaths) {
            return array_map('trim', explode(',', $configuredPaths));
        }

        return config('ai-translator.paths', [
            base_path('lang'),
            base_path('resources/lang'),
        ]);
    }

    /**
     * Get language files from the specified paths.
     * Belirtilen yollardan dil dosyalarını alır.
     */
    protected function getLanguageFiles(array $paths, string $from): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (! $this->filesystem->isDirectory($path)) {
                continue;
            }

            $sourceDir = rtrim($path, '/').'/'.$from;

            if (! $this->filesystem->isDirectory($sourceDir)) {
                continue;
            }

            $finder = Finder::create()
                ->files()
                ->in($sourceDir)
                ->name('*.php')
                ->name('*.json')
                ->sortByName();

            foreach ($finder as $file) {
                $relativePath = $this->getRelativePath($file->getRealPath(), $path);
                $files[] = $relativePath;
            }
        }

        return $files;
    }

    /**
     * Queue files for translation.
     * Dosyaları çeviri için kuyruğa alır.
     */
    protected function queueFiles(array $files, string $from, string $to, string $provider): int
    {
        $queued = 0;

        foreach ($files as $file) {
            try {
                ProcessTranslationJob::dispatch($file, $from, $to, $provider);
                $queued++;

                $this->log('File queued for translation', [
                    'file' => $file,
                    'from' => $from,
                    'to' => $to,
                    'provider' => $provider,
                ]);
            } catch (\Exception $e) {
                $this->error("Failed to queue file {$file}: {$e->getMessage()}");
            }
        }

        return $queued;
    }

    /**
     * Process files directly without queue.
     * Dosyaları kuyruk olmadan doğrudan işler.
     */
    protected function processFilesDirectly(array $files, string $from, string $to, string $provider, bool $force): void
    {
        $progressBar = $this->output->createProgressBar(count($files));
        $progressBar->setFormat('Processing: %current%/%max% [%bar%] %percent:3s%% %message%');
        $progressBar->setMessage('Starting...');
        $progressBar->start();

        $processed = 0;
        $translated = 0;

        foreach ($files as $file) {
            $progressBar->setMessage("Processing {$file}...");

            try {
                $result = $this->manager->translatePath(
                    $file,
                    $from,
                    $to,
                    null, // progress callback
                    $force,
                    $provider
                );

                $translated += $result['translated'];
                $processed++;

                $this->log('File processed directly', [
                    'file' => $file,
                    'from' => $from,
                    'to' => $to,
                    'provider' => $provider,
                    'missing' => $result['missing'],
                    'translated' => $result['translated'],
                ]);
            } catch (\Exception $e) {
                $this->error("Failed to process file {$file}: {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        $progressBar->setMessage("Completed: {$processed} files, {$translated} translations");
        $progressBar->finish();
        $this->newLine();
    }

    /**
     * Get relative path from absolute path.
     * Mutlak yoldan göreli yol alır.
     */
    protected function getRelativePath(string $absolutePath, string $basePath): string
    {
        return ltrim(Str::after($absolutePath, rtrim($basePath, '/').'/'), '/');
    }

    /**
     * Log a message to the sync log file.
     * Senkronizasyon günlük dosyasına mesaj yazar.
     */
    protected function log(string $message, array $context = []): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] Sync: {$message}";

        if (! empty($context)) {
            $logMessage .= ' — '.json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        $logMessage .= PHP_EOL;

        $this->filesystem->append(storage_path('logs/ai-translator-sync.log'), $logMessage);

        // Also log to Laravel's logger
        AiTranslatorLogger::info($message, $context);
    }
}
