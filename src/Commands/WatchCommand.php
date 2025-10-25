<?php

namespace DigitalCoreHub\LaravelAiTranslator\Commands;

use DigitalCoreHub\LaravelAiTranslator\Services\TranslationWatcher;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Watch for changes in language files and automatically translate them.
 * Dil dosyalarındaki değişiklikleri izler ve otomatik olarak çevirir.
 */
class WatchCommand extends Command
{
    /**
     * The console command signature.
     * Konsol komutunun imzası.
     */
    protected $signature = 'ai:watch
        {--from=en : Source language code}
        {--to=tr : Target language code}
        {--provider= : Translation provider to use}
        {--paths= : Comma-separated list of paths to watch}';

    /**
     * The console command description.
     * Konsol komutunun açıklaması.
     */
    protected $description = 'Watch for changes in language files and automatically translate them';

    public function __construct(
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
        $from = $this->option('from');
        $to = $this->option('to');
        $provider = $this->option('provider') ?: config('ai-translator.provider', 'openai');
        $paths = $this->getWatchPaths();

        $this->info("Starting watcher for {$from} -> {$to} translation...");
        $this->info("Provider: {$provider}");
        $this->info('Watching paths: '.implode(', ', $paths));
        $this->newLine();

        // Ensure log directory exists
        $logDir = storage_path('logs');
        if (! $this->filesystem->isDirectory($logDir)) {
            $this->filesystem->makeDirectory($logDir, 0755, true);
        }

        // Create watcher instance
        $watcher = new TranslationWatcher(
            $this->filesystem,
            base_path(),
            $paths,
            $from,
            $to,
            $provider
        );

        $this->info('Watcher is running. Press Ctrl+C to stop.');
        $this->newLine();

        try {
            $watcher->watch();
        } catch (\Exception $e) {
            $this->error("Watcher failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Get the paths to watch.
     * İzlenecek yolları döndürür.
     */
    protected function getWatchPaths(): array
    {
        $configuredPaths = $this->option('paths');

        if ($configuredPaths) {
            return array_map('trim', explode(',', $configuredPaths));
        }

        return config('ai-translator.watch_paths', [
            base_path('lang'),
            base_path('resources/lang'),
        ]);
    }
}
