<?php

namespace DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator;

use DigitalCoreHub\LaravelAiTranslator\Jobs\ProcessTranslationJob;
use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use Illuminate\Support\Str;
use Livewire\Component;
use Symfony\Component\Finder\Finder;

/**
 * Sync component for bulk translation operations.
 * Toplu çeviri işlemleri için senkronizasyon bileşeni.
 */
class Sync extends Component
{
    public string $from = 'en';

    public string $to = 'tr';

    public string $provider = 'openai';

    public bool $useQueue = true;

    public bool $force = false;

    public array $availableLocales = [];

    public array $availableProviders = [];

    public array $files = [];

    public bool $isProcessing = false;

    public string $status = '';

    public int $progress = 0;

    public int $total = 0;

    protected $listeners = ['refreshSync'];

    public function mount(): void
    {
        $this->loadAvailableOptions();
        $this->scanFiles();
    }

    public function render(): mixed
    {
        return view('livewire.translator.sync');
    }

    /**
     * Load available locales and providers.
     * Mevcut yereller ve sağlayıcıları yükler.
     */
    public function loadAvailableOptions(): void
    {
        $manager = app(TranslationManager::class);

        $this->availableLocales = $manager->availableLocales();
        $this->availableProviders = $manager->availableProviders();

        // Set default provider from config
        $this->provider = config('ai-translator.provider', 'openai');
    }

    /**
     * Scan for language files.
     * Dil dosyalarını tarar.
     */
    public function scanFiles(): void
    {
        $this->files = [];
        $paths = config('ai-translator.paths', [
            base_path('lang'),
            base_path('resources/lang'),
        ]);

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $sourceDir = rtrim($path, '/').'/'.$this->from;

            if (! is_dir($sourceDir)) {
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
                $this->files[] = [
                    'path' => $relativePath,
                    'name' => basename($relativePath),
                    'size' => $file->getSize(),
                    'modified' => $file->getMTime(),
                ];
            }
        }

        $this->total = count($this->files);
    }

    /**
     * Start the sync process.
     * Senkronizasyon işlemini başlatır.
     */
    public function startSync(): void
    {
        $this->validate([
            'from' => 'required|string',
            'to' => 'required|string',
            'provider' => 'required|string',
        ]);

        if (empty($this->files)) {
            $this->addError('files', 'No language files found to sync.');

            return;
        }

        $this->isProcessing = true;
        $this->progress = 0;
        $this->status = 'Starting sync...';

        if ($this->useQueue) {
            $this->startQueueSync();
        } else {
            $this->startDirectSync();
        }
    }

    /**
     * Start queue-based sync.
     * Kuyruk tabanlı senkronizasyonu başlatır.
     */
    protected function startQueueSync(): void
    {
        $queued = 0;

        foreach ($this->files as $file) {
            try {
                ProcessTranslationJob::dispatch(
                    $file['path'],
                    $this->from,
                    $this->to,
                    $this->provider
                );
                $queued++;
            } catch (\Exception $e) {
                $this->addError('queue', "Failed to queue file {$file['path']}: {$e->getMessage()}");
            }
        }

        $this->status = "Queued {$queued} translation jobs. Run 'php artisan queue:work --queue=ai-translations' to process them.";
        $this->isProcessing = false;

        $this->dispatch('queueStatusUpdated');
    }

    /**
     * Start direct sync (without queue).
     * Doğrudan senkronizasyonu başlatır (kuyruk olmadan).
     */
    protected function startDirectSync(): void
    {
        $manager = app(TranslationManager::class);
        $processed = 0;
        $translated = 0;

        foreach ($this->files as $file) {
            try {
                $this->status = "Processing {$file['name']}...";
                $this->progress = round((($processed + 1) / $this->total) * 100);

                $result = $manager->translatePath(
                    $file['path'],
                    $this->from,
                    $this->to,
                    null, // progress callback
                    $this->force,
                    $this->provider
                );

                $translated += $result['translated'];
                $processed++;

            } catch (\Exception $e) {
                $this->addError('sync', "Failed to process file {$file['path']}: {$e->getMessage()}");
            }
        }

        $this->status = "Sync completed! Processed {$processed} files, translated {$translated} keys.";
        $this->isProcessing = false;
        $this->progress = 100;
    }

    /**
     * Cancel the sync process.
     * Senkronizasyon işlemini iptal eder.
     */
    public function cancelSync(): void
    {
        $this->isProcessing = false;
        $this->status = 'Sync cancelled.';
        $this->progress = 0;
    }

    /**
     * Refresh the sync component.
     * Senkronizasyon bileşenini yeniler.
     */
    public function refreshSync(): void
    {
        $this->scanFiles();
        $this->reset(['isProcessing', 'status', 'progress']);
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
     * Get the progress percentage.
     * İlerleme yüzdesini döndürür.
     */
    public function getProgressPercentageProperty(): float
    {
        return round($this->progress, 1);
    }

    /**
     * Check if sync can be started.
     * Senkronizasyonun başlatılıp başlatılamayacağını kontrol eder.
     */
    public function getCanStartSyncProperty(): bool
    {
        return ! $this->isProcessing &&
               ! empty($this->files) &&
               $this->from !== $this->to;
    }
}
