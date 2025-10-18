<?php

namespace DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator;

use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use Livewire\Volt\Component;
use Throwable;

class Dashboard extends Component
{
    public string $from = 'en';

    public string $to = 'tr';

    public string $provider = '';

    public array $providers = [];

    public array $locales = [];

    public array $entries = [];

    public string $statusMessage = '';

    public string $progressLabel = '';

    public bool $translating = false;

    public int $progressTotal = 0;

    public int $progressDone = 0;

    public function mount(): void
    {
        $manager = $this->manager();

        $this->providers = $manager->availableProviders();
        $this->provider = config('ai-translator.provider', 'openai');

        if ($this->provider === '' && $this->providers !== []) {
            $this->provider = $this->providers[0];
        }

        $this->locales = $manager->availableLocales();

        if ($this->locales !== []) {
            if (! in_array($this->from, $this->locales, true)) {
                $this->from = $this->locales[0];
            }

            if (! in_array($this->to, $this->locales, true)) {
                $this->to = $this->locales[1] ?? $this->from;
            }
        } else {
            $this->locales = array_values(array_unique([$this->from, $this->to]));
        }

        $this->refreshEntries();
    }

    public function render(): mixed
    {
        return view('ai-translator::livewire.translator.dashboard')
            ->layout('ai-translator::vendor.ai-translator.layouts.app');
    }

    public function refreshEntries(): void
    {
        $entries = $this->manager()->gatherEntries($this->from, $this->to);

        $this->entries = $entries;
    }

    public function scan(): void
    {
        $this->refreshEntries();
        $this->statusMessage = 'Eksik anahtar taraması tamamlandı.';
    }

    public function translateMissing(): void
    {
        $this->runTranslation(force: false);
    }

    public function retranslateAll(): void
    {
        $this->runTranslation(force: true);
    }

    public function edit(string $file, string $key)
    {
        return redirect()->route('ai-translator.edit', [
            'path' => $file,
            'key' => $key,
            'from' => $this->from,
            'to' => $this->to,
        ]);
    }

    public function translateKey(string $file, string $key): void
    {
        $this->translateSingle($file, $key, force: false);
    }

    public function retranslateKey(string $file, string $key): void
    {
        $this->translateSingle($file, $key, force: true);
    }

    protected function runTranslation(bool $force = false): void
    {
        if ($this->from === $this->to) {
            $this->statusMessage = 'Kaynak ve hedef dilleri farklı seçmelisiniz.';

            return;
        }

        $manager = $this->manager();

        $this->progressDone = 0;
        $this->progressTotal = $manager->countMissing($this->from, $this->to, $force);
        $this->translating = true;
        $this->progressLabel = $this->progressTotal > 0
            ? sprintf('Çevriliyor (0/%d)…', $this->progressTotal)
            : 'Çevrilecek anahtar bulunamadı.';

        if ($this->progressTotal === 0) {
            $this->translating = false;

            return;
        }

        try {
            $result = $manager->translate(
                from: $this->from,
                to: $this->to,
                progress: function () {
                    $this->progressDone++;
                    $this->progressLabel = sprintf('Çevriliyor (%d/%d)…', $this->progressDone, $this->progressTotal);
                },
                dryRun: false,
                force: $force,
                options: ['provider' => $this->provider]
            );
        } catch (Throwable $exception) {
            $this->translating = false;
            $this->statusMessage = $exception->getMessage();

            return;
        }

        $this->logResult($result, $force);

        $this->translating = false;
        $this->statusMessage = sprintf(
            'Çeviri tamamlandı. Missing: %d | Translated: %d',
            $result['totals']['missing'],
            $result['totals']['translated']
        );

        $this->progressLabel = sprintf('Tamamlandı (%d/%d)', $this->progressDone, $this->progressTotal);

        $this->refreshEntries();
    }

    protected function logResult(array $result, bool $force): void
    {
        $filesystem = app('files');
        $logPath = storage_path('logs/ai-translator.log');
        $reportPath = storage_path('logs/ai-translator-report.json');

        if (! $filesystem->isDirectory(dirname($logPath))) {
            $filesystem->makeDirectory(dirname($logPath), 0755, true);
        }

        $line = sprintf(
            '[%s] context=web from=%s to=%s provider=%s missing=%d translated=%d force=%s',
            now()->toDateTimeString(),
            $this->from,
            $this->to,
            $this->provider,
            $result['totals']['missing'],
            $result['totals']['translated'],
            $force ? 'true' : 'false'
        );

        $filesystem->append($logPath, $line.PHP_EOL);

        $reports = [];

        if ($filesystem->exists($reportPath)) {
            $existing = json_decode($filesystem->get($reportPath), true);

            if (is_array($existing)) {
                $reports = $existing;
            }
        }

        $reports[] = [
            'from' => $this->from,
            'to' => $this->to,
            'provider' => $this->provider,
            'executed_at' => now()->toIso8601String(),
            'files' => $result['report'],
        ];

        $filesystem->put(
            $reportPath,
            json_encode($reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL
        );
    }

    protected function translateSingle(string $file, string $key, bool $force): void
    {
        $manager = $this->manager();
        $entry = $manager->findEntry($file, $key, $this->from, $this->to);

        if (! $entry) {
            $this->statusMessage = 'Anahtar bulunamadı.';

            return;
        }

        $result = $manager->translateText(
            text: (string) $entry['source'],
            from: $this->from,
            to: $this->to,
            provider: $this->provider,
            force: $force
        );

        $manager->updateTranslation($file, $key, $result['translation']);

        $this->logSingleResult($file, $key, $result, $force);

        $this->statusMessage = sprintf('%s -> %s anahtarı güncellendi.', $file, $key);
        $this->refreshEntries();
    }

    protected function logSingleResult(string $file, string $key, array $result, bool $force): void
    {
        $filesystem = app('files');
        $logPath = storage_path('logs/ai-translator.log');

        if (! $filesystem->isDirectory(dirname($logPath))) {
            $filesystem->makeDirectory(dirname($logPath), 0755, true);
        }

        $line = sprintf(
            '[%s] context=web action=single path=%s key=%s provider=%s cache=%s force=%s',
            now()->toDateTimeString(),
            $file,
            $key,
            $result['provider'] ?? $this->provider,
            ($result['cache_hit'] ?? false) ? 'hit' : 'miss',
            $force ? 'true' : 'false'
        );

        $filesystem->append($logPath, $line.PHP_EOL);
    }

    protected function manager(): TranslationManager
    {
        return app(TranslationManager::class);
    }
}
