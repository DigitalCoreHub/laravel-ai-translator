<?php

namespace DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator;

use DigitalCoreHub\LaravelAiTranslator\Jobs\ProcessTranslationJob;
use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use DigitalCoreHub\LaravelAiTranslator\Support\AiTranslatorLogger;
use DigitalCoreHub\LaravelAiTranslator\Support\QueueMonitor;
use DigitalCoreHub\LaravelAiTranslator\Support\ReportStore;
use Livewire\Volt\Component;

class Sync extends Component
{
    public string $from = '';

    public array $locales = [];

    public array $providers = [];

    public string $provider = '';

    public array $targets = [];

    public bool $useQueue = true;

    public bool $force = false;

    public string $statusMessage = '';

    public function mount(): void
    {
        $manager = $this->manager();
        $this->locales = $manager->availableLocales();
        $this->providers = $manager->availableProviders();
        $this->from = config('app.locale');

        if ($this->locales !== [] && ! in_array($this->from, $this->locales, true)) {
            $this->from = $this->locales[0];
        }

        $this->provider = config('ai-translator.provider', $this->providers[0] ?? 'openai');

        if ($this->provider === '' && $this->providers !== []) {
            $this->provider = $this->providers[0];
        }
    }

    public function render(): mixed
    {
        return view('ai-translator::livewire.translator.sync')
            ->layout('ai-translator::vendor.ai-translator.layouts.app');
    }

    public function toggleQueue(): void
    {
        $this->useQueue = ! $this->useQueue;
    }

    public function sync(): void
    {
        $manager = $this->manager();
        $targets = $this->resolveTargets($manager);

        if ($targets === []) {
            $this->statusMessage = 'Hedef dil seçilmedi veya bulunamadı.';

            return;
        }

        if ($this->useQueue) {
            $monitor = app(QueueMonitor::class);
            $dispatched = 0;

            foreach ($targets as $target) {
                $files = collect($manager->gatherEntries($this->from, $target))
                    ->pluck('file')
                    ->unique()
                    ->values();

                foreach ($files as $file) {
                    $job = new ProcessTranslationJob($this->from, $target, $file, $this->provider, $this->force);
                    $monitor->markQueued($job->trackingId, [
                        'file' => $file,
                        'from' => $this->from,
                        'to' => $target,
                        'provider' => $this->provider,
                    ]);

                    AiTranslatorLogger::sync(sprintf(
                        'Sync UI queued %s (%s → %s).',
                        $file,
                        $this->from,
                        $target
                    ));

                    dispatch($job);
                    $dispatched++;
                }
            }

            $this->statusMessage = sprintf('%d job kuyruğa eklendi.', $dispatched);

            return;
        }

        $reports = app(ReportStore::class);
        $translated = 0;

        foreach ($targets as $target) {
            $result = $manager->translate(
                $this->from,
                $target,
                progress: null,
                dryRun: false,
                force: $this->force,
                options: ['provider' => $this->provider]
            );

            $reports->appendTranslationRun(
                $this->from,
                $target,
                $this->provider,
                $result['report'],
                ['executed_at' => now()->toIso8601String(), 'context' => 'sync-ui']
            );

            AiTranslatorLogger::sync(sprintf(
                'Sync UI completed %s → %s missing=%d translated=%d force=%s',
                strtoupper($this->from),
                strtoupper($target),
                $result['totals']['missing'],
                $result['totals']['translated'],
                $this->force ? 'true' : 'false'
            ));

            $translated += $result['totals']['translated'];
        }

        $this->statusMessage = sprintf('%d anahtar başarıyla senkronize edildi.', $translated);
    }

    protected function manager(): TranslationManager
    {
        return app(TranslationManager::class);
    }

    protected function resolveTargets(TranslationManager $manager): array
    {
        $targets = $this->targets;

        if ($targets === []) {
            $targets = array_diff($manager->availableLocales(), [$this->from]);
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($locale) => strtolower((string) $locale),
            $targets
        ))));
    }
}
