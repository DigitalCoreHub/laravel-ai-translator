<?php

namespace DigitalCoreHub\LaravelAiTranslator\Services;

use DigitalCoreHub\LaravelAiTranslator\Jobs\ProcessTranslationJob;
use DigitalCoreHub\LaravelAiTranslator\Support\AiTranslatorLogger;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/**
 * Watch for changes in language files and dispatch translation jobs.
 * Dil dosyalarındaki değişiklikleri izler ve çeviri işlerini kuyruğa alır.
 */
class TranslationWatcher
{
    protected array $lastCheckedTimes = [];

    protected array $watchPaths = [];

    protected string $logFile;

    public function __construct(
        protected Filesystem $filesystem,
        protected string $basePath,
        array $watchPaths = [],
        protected string $from = 'en',
        protected string $to = 'tr',
        protected string $provider = 'openai'
    ) {
        $this->watchPaths = $watchPaths ?: [
            base_path('lang'),
            base_path('resources/lang'),
        ];
        $this->logFile = storage_path('logs/ai-translator-watch.log');
    }

    /**
     * Start watching for file changes.
     * Dosya değişikliklerini izlemeye başlar.
     */
    public function watch(): void
    {
        $this->log('Watcher started', ['paths' => $this->watchPaths]);

        while (true) {
            $this->checkForChanges();
            sleep(1); // Check every second
        }
    }

    /**
     * Check for changes in watched directories.
     * İzlenen dizinlerde değişiklik kontrol eder.
     */
    public function checkForChanges(): void
    {
        foreach ($this->watchPaths as $path) {
            if (! $this->filesystem->isDirectory($path)) {
                continue;
            }

            $this->scanDirectory($path);
        }
    }

    /**
     * Scan a directory for changes.
     * Bir dizini değişiklikler için tarar.
     */
    protected function scanDirectory(string $path): void
    {
        $finder = Finder::create()
            ->files()
            ->in($path)
            ->name('*.php')
            ->name('*.json')
            ->sortByName();

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            $relativePath = $this->getRelativePath($filePath);

            if ($this->shouldProcessFile($relativePath)) {
                $this->processFileIfChanged($filePath, $relativePath);
            }
        }
    }

    /**
     * Check if a file should be processed based on its path.
     * Dosya yoluna göre işlenip işlenmeyeceğini kontrol eder.
     */
    protected function shouldProcessFile(string $relativePath): bool
    {
        // Only process files in language directories
        $pathSegments = explode('/', $relativePath);

        // Check if it's a language file (contains locale directory)
        foreach ($pathSegments as $segment) {
            if (in_array($segment, ['en', 'tr', 'es', 'fr', 'de', 'it', 'pt', 'ru', 'ar', 'zh', 'ja', 'ko'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Process a file if it has been modified since last check.
     * Dosya son kontrol edildiğinden beri değiştirilmişse işler.
     */
    protected function processFileIfChanged(string $filePath, string $relativePath): void
    {
        $lastModified = filemtime($filePath);
        $fileKey = $relativePath;

        if (! isset($this->lastCheckedTimes[$fileKey]) || $lastModified > $this->lastCheckedTimes[$fileKey]) {
            $this->lastCheckedTimes[$fileKey] = $lastModified;

            // Only process if it's a source language file (en)
            if ($this->isSourceLanguageFile($relativePath)) {
                $this->dispatchTranslationJob($filePath, $relativePath);
            }
        }
    }

    /**
     * Check if the file is a source language file (English).
     * Dosyanın kaynak dil dosyası (İngilizce) olup olmadığını kontrol eder.
     */
    protected function isSourceLanguageFile(string $relativePath): bool
    {
        $pathSegments = explode('/', $relativePath);

        foreach ($pathSegments as $segment) {
            if ($segment === $this->from) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dispatch a translation job for the file.
     * Dosya için çeviri işini kuyruğa alır.
     */
    protected function dispatchTranslationJob(string $filePath, string $relativePath): void
    {
        try {
            ProcessTranslationJob::dispatch(
                $relativePath,
                $this->from,
                $this->to,
                $this->provider
            );

            $this->log('File change detected and queued for translation', [
                'file' => $relativePath,
                'from' => $this->from,
                'to' => $this->to,
                'provider' => $this->provider,
            ]);
        } catch (\Exception $e) {
            $this->log('Failed to dispatch translation job', [
                'file' => $relativePath,
                'error' => $e->getMessage(),
            ], 'error');
        }
    }

    /**
     * Get relative path from absolute path.
     * Mutlak yoldan göreli yol alır.
     */
    protected function getRelativePath(string $absolutePath): string
    {
        return ltrim(Str::after($absolutePath, rtrim($this->basePath, '/').'/'), '/');
    }

    /**
     * Log a message to the watch log file.
     * İzleme günlük dosyasına mesaj yazar.
     */
    protected function log(string $message, array $context = [], string $level = 'info'): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] Watcher: {$message}";

        if (! empty($context)) {
            $logMessage .= ' — '.json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        $logMessage .= PHP_EOL;

        $this->filesystem->append($this->logFile, $logMessage);

        // Also log to Laravel's logger
        AiTranslatorLogger::{$level}($message, $context);
    }

    /**
     * Get the last checked times for all files.
     * Tüm dosyalar için son kontrol edilme zamanlarını döndürür.
     */
    public function getLastCheckedTimes(): array
    {
        return $this->lastCheckedTimes;
    }

    /**
     * Set the last checked times for files.
     * Dosyalar için son kontrol edilme zamanlarını ayarlar.
     */
    public function setLastCheckedTimes(array $times): void
    {
        $this->lastCheckedTimes = $times;
    }

    /**
     * Get watch paths.
     * İzleme yollarını döndürür.
     */
    public function getWatchPaths(): array
    {
        return $this->watchPaths;
    }

    /**
     * Set watch paths.
     * İzleme yollarını ayarlar.
     */
    public function setWatchPaths(array $paths): void
    {
        $this->watchPaths = $paths;
    }
}
