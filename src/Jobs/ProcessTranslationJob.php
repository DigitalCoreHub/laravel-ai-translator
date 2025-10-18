<?php

namespace DigitalCoreHub\LaravelAiTranslator\Jobs;

use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use DigitalCoreHub\LaravelAiTranslator\Support\AiTranslatorLogger;
use DigitalCoreHub\LaravelAiTranslator\Support\QueueMonitor;
use DigitalCoreHub\LaravelAiTranslator\Support\ReportStore;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProcessTranslationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public string $trackingId;

    public function __construct(
        public string $from,
        public string $to,
        public string $relativePath,
        public ?string $provider = null,
        public bool $force = false,
        ?string $trackingId = null
    ) {
        $this->onConnection(config('ai-translator.queue_connection', config('queue.default')));
        $this->onQueue(config('ai-translator.queue_name', 'ai-translations'));
        $this->trackingId = $trackingId ?? (string) Str::uuid();
    }

    public function middleware(): array
    {
        return [];
    }

    public function handle(TranslationManager $manager, ReportStore $reports, QueueMonitor $monitor): void
    {
        $jobId = $this->job?->getJobId() ?? $this->trackingId;

        if (! $this->acquireSlot()) {
            AiTranslatorLogger::queue(sprintf('Job #%s delayed due to concurrency guard.', $jobId));
            $this->release(5);

            return;
        }

        try {
            $monitor->markRunning((string) $jobId, [
                'file' => $this->relativePath,
                'from' => $this->from,
                'to' => $this->to,
                'provider' => $this->determineProvider($manager),
            ]);

            $start = microtime(true);

            AiTranslatorLogger::queue(sprintf(
                'Job #%s started for %s (%s → %s)',
                $jobId,
                $this->relativePath,
                $this->from,
                $this->to
            ));

            $processed = 0;

            $result = $manager->translatePath(
                $this->relativePath,
                $this->from,
                $this->to,
                progress: function (string $file, string $key) use ($monitor, $jobId, &$processed) {
                    $processed++;
                    $monitor->markProgress((string) $jobId, [
                        'last_key' => $key,
                        'translated' => $processed,
                    ]);
                },
                force: $this->force,
                provider: $this->provider
            );

            $duration = (microtime(true) - $start) * 1000;

            $provider = $this->providerFromStats($manager, $result['stats']);

            $monitor->markFinished((string) $jobId, [
                'file' => $this->relativePath,
                'from' => $this->from,
                'to' => $this->to,
                'provider' => $provider,
                'missing' => $result['missing'],
                'translated' => $result['translated'],
                'progress_total' => max($result['missing'], $result['translated']),
                'duration_ms' => round($duration, 2),
            ]);

            $reports->appendTranslationRun(
                $this->from,
                $this->to,
                $provider,
                [$result['report']],
                ['executed_at' => now()->toIso8601String()]
            );

            AiTranslatorLogger::queue(sprintf(
                'Job #%s completed successfully. translated=%d missing=%d duration_ms=%.2f',
                $jobId,
                $result['translated'],
                $result['missing'],
                $duration
            ));
        } finally {
            $this->releaseSlot();
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $jobId = $this->job?->getJobId() ?? $this->trackingId;
        app(QueueMonitor::class)->markFailed((string) $jobId, $exception?->getMessage() ?? 'Job failed');

        AiTranslatorLogger::queue(sprintf(
            'Job #%s failed: %s',
            $jobId,
            $exception?->getMessage() ?? 'unknown error'
        ));
    }

    protected function determineProvider(TranslationManager $manager): string
    {
        return $this->provider ?? $manager->availableProviders()[0] ?? 'openai';
    }

    protected function providerFromStats(TranslationManager $manager, array $stats): string
    {
        if ($this->provider) {
            return $this->provider;
        }

        $providers = $stats['providers'] ?? [];

        if (is_array($providers) && $providers !== []) {
            return (string) array_key_first($providers);
        }

        return $this->determineProvider($manager);
    }

    protected function acquireSlot(): bool
    {
        $limit = max(1, (int) config('ai-translator.queue_max_concurrent', 5));
        $lock = Cache::lock('ai-translator:slot-lock', 5);
        $acquired = false;

        $lock->block(3, function () use ($limit, &$acquired) {
            $running = (int) Cache::get('ai-translator:running-count', 0);

            if ($running >= $limit) {
                return;
            }

            Cache::put('ai-translator:running-count', $running + 1, 60);
            $acquired = true;
        });

        return $acquired;
    }

    protected function releaseSlot(): void
    {
        Cache::lock('ai-translator:slot-lock', 5)->block(3, function (): void {
            $running = (int) Cache::get('ai-translator:running-count', 0);
            $running = max(0, $running - 1);
            Cache::put('ai-translator:running-count', $running, 60);
        });
    }
}
