<?php

namespace DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator;

use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\On;
use Livewire\Component;

class Dashboard extends Component
{
    public array $locales = [];

    public string $sourceLocale = '';

    public string $targetLocale = '';

    public array $files = [];

    public ?string $selectedFile = null;

    public array $entries = [];

    public ?string $statusMessage = null;

    public ?string $statusType = null;

    public ?string $provider = null;

    public array $providers = [];

    public ?int $progressCurrent = null;

    public ?int $progressTotal = null;

    public array $lastPreview = [];

    protected TranslationManager $manager;

    public function mount(TranslationManager $manager): void
    {
        $this->manager = $manager;
        $this->locales = $manager->availableLocales();
        $this->providers = array_keys(config('ai-translator.providers', []));
        $this->provider = config('ai-translator.provider');

        if ($this->locales !== []) {
            $this->sourceLocale = $this->locales[0];
            $this->targetLocale = $this->locales[0];
        }
    }

    public function updatedSourceLocale(): void
    {
        if ($this->sourceLocale === $this->targetLocale && count($this->locales) > 1) {
            $this->targetLocale = $this->locales[1];
        }
    }

    public function scan(): void
    {
        if ($this->sourceLocale === '' || $this->targetLocale === '') {
            return;
        }

        $this->statusMessage = 'Eksik çeviriler taranıyor…';
        $this->statusType = 'info';
        $this->progressCurrent = null;
        $this->progressTotal = null;

        $result = $this->manager->inspectMissing($this->sourceLocale, $this->targetLocale);

        $this->files = $result['files'];
        $this->selectedFile = $this->files[0]['name'] ?? null;
        $this->entries = $this->selectedFile ? $this->decorateEntries($this->files[0]['entries']) : [];
        $this->lastPreview = [];
        $this->statusMessage = 'Tarama tamamlandı';
        $this->statusType = 'success';
    }

    public function selectFile(string $path): void
    {
        $file = collect($this->files)->firstWhere('name', $path);

        if (! $file) {
            return;
        }

        $this->selectedFile = $path;
        $this->entries = $this->decorateEntries($file['entries']);
        $this->lastPreview = [];
    }

    public function translateSelectedFile(): void
    {
        if (! $this->selectedFile) {
            return;
        }

        $total = count($this->entries);
        $this->progressCurrent = 0;
        $this->progressTotal = $total;
        $this->statusMessage = 'Çevriliyor (0/'.$total.')…';
        $this->statusType = 'info';

        $result = $this->manager->translateFile(
            $this->sourceLocale,
            $this->targetLocale,
            $this->selectedFile,
            [
                'provider' => $this->provider,
                'progress' => function (): void {
                    $this->progressCurrent++;
                    $this->statusMessage = 'Çevriliyor ('.$this->progressCurrent.'/'.$this->progressTotal.')…';
                },
            ]
        );

        $this->statusMessage = 'Çeviri tamamlandı';
        $this->statusType = 'success';
        $this->lastPreview = $result['preview'];
        $this->appendReport($result);
        $this->logAction('Dosya çevirisi tamamlandı', [
            'file' => $result['path'],
            'from' => $this->sourceLocale,
            'to' => $this->targetLocale,
            'translated' => $result['translated'],
        ]);

        $this->refreshSelectedFile();
    }

    public function translateEntry(string $encodedKey, bool $force = false): void
    {
        if (! $this->selectedFile) {
            return;
        }

        $key = base64_decode($encodedKey, true);

        if ($key === false) {
            return;
        }

        $result = $this->manager->translateEntry(
            $this->sourceLocale,
            $this->targetLocale,
            $this->selectedFile,
            $key,
            ['provider' => $this->provider, 'force' => $force]
        );

        if ($result['provider'] === 'existing') {
            $this->statusMessage = 'Anahtar zaten mevcut: '.$key;
            $this->statusType = 'info';

            return;
        }

        $this->statusMessage = ($force ? 'Yeniden çevrildi: ' : 'Anahtar çevrildi: ').$key;
        $this->statusType = 'success';
        $this->logAction('Anahtar çevirisi yapıldı', [
            'file' => $this->selectedFile,
            'key' => $key,
            'provider' => $result['provider'],
            'cache_hit' => $result['cache_hit'],
            'mode' => $force ? 'retranslate' : 'translate',
        ]);

        $this->refreshSelectedFile();
        $this->appendReport([
            'path' => $this->selectedFile,
            'missing' => 0,
            'translated' => 1,
            'preview' => [$key => $result['translation']],
            'reviews' => [],
            'stats' => [
                'providers' => [$result['provider'] => 1],
                'cache_hits' => $result['cache_hit'] ? 1 : 0,
                'cache_misses' => $result['cache_hit'] ? 0 : 1,
                'duration' => $result['duration'],
            ],
        ]);
    }

    public function gotoEdit(): mixed
    {
        if (! $this->selectedFile) {
            return null;
        }

        $encoded = base64_encode($this->selectedFile);

        return $this->redirectRoute('ai-translator.edit', [
            'from' => $this->sourceLocale,
            'to' => $this->targetLocale,
            'encoded' => $encoded,
        ]);
    }

    public function swapLocales(): void
    {
        [$this->sourceLocale, $this->targetLocale] = [$this->targetLocale, $this->sourceLocale];
        $this->scan();
    }

    #[On('refresh-dashboard')]
    public function refreshSelectedFile(): void
    {
        if (! $this->selectedFile) {
            return;
        }

        $entries = $this->manager->getFileEntries($this->sourceLocale, $this->targetLocale, $this->selectedFile);
        $this->entries = $this->decorateEntries($entries);

        $missing = collect($this->entries)->filter(function (array $entry) {
            return $entry['status'] === 'missing' || $entry['status'] === 'empty';
        })->count();

        $this->files = array_map(function (array $file) use ($missing) {
            if ($file['name'] === $this->selectedFile) {
                $file['entries'] = $this->entries;
                $file['missing'] = $missing;
            }

            return $file;
        }, $this->files);
    }

    protected function decorateEntries(array $entries): array
    {
        return array_map(function (array $entry) {
            $status = $entry['target'] === null
                ? 'missing'
                : ($entry['target'] === '' ? 'empty' : 'translated');

            return $entry + ['status' => $status];
        }, $entries);
    }

    protected function appendReport(array $result): void
    {
        $path = storage_path('logs/ai-translator-report.json');
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $existing = [];

        if (File::exists($path)) {
            $decoded = json_decode(File::get($path), true);
            $existing = is_array($decoded) ? $decoded : [];
        }

        $stats = $result['stats'] ?? ['providers' => [], 'cache_hits' => 0, 'cache_misses' => 0, 'duration' => 0.0];
        $totalCache = ($stats['cache_hits'] ?? 0) + ($stats['cache_misses'] ?? 0);
        $hitRate = $totalCache > 0 ? ($stats['cache_hits'] ?? 0) / $totalCache : 0.0;

        $existing[] = [
            'file' => $result['path'],
            'translated' => $result['translated'] ?? 0,
            'missing' => $result['missing'] ?? 0,
            'primary_provider' => $this->primaryProvider($stats['providers'] ?? []),
            'providers' => $stats['providers'] ?? [],
            'cache' => [
                'hits' => $stats['cache_hits'] ?? 0,
                'misses' => $stats['cache_misses'] ?? 0,
                'hit_rate' => $hitRate,
            ],
            'duration_ms' => round(($stats['duration'] ?? 0.0) * 1000, 2),
            'timestamp' => Carbon::now()->toIso8601String(),
        ];

        File::put($path, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    protected function primaryProvider(array $providers): string
    {
        if ($providers === []) {
            return 'unknown';
        }

        arsort($providers);

        return (string) array_key_first($providers);
    }

    protected function logAction(string $message, array $context = []): void
    {
        $path = storage_path('logs/ai-translator.log');
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $timestamp = Carbon::now()->format('Y-m-d H:i:s');
        $payload = $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        File::append($path, "[{$timestamp}] {$message} {$payload}".PHP_EOL);
    }

    public function render()
    {
        return view('ai-translator::livewire.translator.dashboard')
            ->layout('ai-translator::livewire.translator.layout');
    }
}
