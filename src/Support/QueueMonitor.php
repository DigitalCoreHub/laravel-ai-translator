<?php

namespace DigitalCoreHub\LaravelAiTranslator\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;

class QueueMonitor
{
    public function __construct(
        protected Filesystem $filesystem
    ) {}

    public function path(): string
    {
        return storage_path('logs/ai-translator-queue.json');
    }

    public function state(): array
    {
        $path = $this->path();

        if (! $this->filesystem->exists($path)) {
            return ['jobs' => [], 'totals' => ['pending' => 0, 'completed' => 0, 'failed' => 0]];
        }

        $decoded = json_decode($this->filesystem->get($path), true);

        if (! is_array($decoded)) {
            return ['jobs' => [], 'totals' => ['pending' => 0, 'completed' => 0, 'failed' => 0]];
        }

        $decoded['jobs'] = array_values(array_filter(
            Arr::get($decoded, 'jobs', []),
            static fn ($job) => is_array($job)
        ));

        $decoded['totals'] = array_merge(
            ['pending' => 0, 'completed' => 0, 'failed' => 0],
            Arr::get($decoded, 'totals', [])
        );

        return $decoded;
    }

    public function markQueued(string $jobId, array $payload): void
    {
        $state = $this->state();

        $job = array_merge([
            'id' => $jobId,
            'status' => 'queued',
            'queued_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ], $payload);

        $state['jobs'] = collect($state['jobs'])
            ->reject(fn ($item) => ($item['id'] ?? null) === $jobId)
            ->push($job)
            ->values()
            ->all();

        $state['totals']['pending'] = collect($state['jobs'])
            ->where('status', 'queued')
            ->count();

        $this->write($state);
    }

    public function markRunning(string $jobId, array $payload = []): void
    {
        $this->updateJob($jobId, array_merge($payload, [
            'status' => 'running',
            'started_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]));
    }

    public function markProgress(string $jobId, array $payload): void
    {
        $this->updateJob($jobId, array_merge($payload, [
            'updated_at' => now()->toIso8601String(),
        ]));
    }

    public function markFinished(string $jobId, array $payload = []): void
    {
        $this->updateJob($jobId, array_merge($payload, [
            'status' => 'completed',
            'completed_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]));
    }

    public function markFailed(string $jobId, string $message): void
    {
        $this->updateJob($jobId, [
            'status' => 'failed',
            'error' => $message,
            'failed_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    protected function updateJob(string $jobId, array $changes): void
    {
        $state = $this->state();

        $jobs = collect($state['jobs']);
        $found = false;

        $state['jobs'] = $jobs
            ->map(function ($job) use ($jobId, $changes, &$found) {
                if (($job['id'] ?? null) !== $jobId) {
                    return $job;
                }

                $found = true;

                return array_merge($job, $changes);
            })
            ->values()
            ->all();

        if (! $found) {
            $state['jobs'][] = array_merge([
                'id' => $jobId,
                'queued_at' => now()->toIso8601String(),
            ], $changes);
        }

        $state['totals']['pending'] = collect($state['jobs'])
            ->whereIn('status', ['queued', 'running'])
            ->count();

        $state['totals']['completed'] = collect($state['jobs'])
            ->where('status', 'completed')
            ->count();

        $state['totals']['failed'] = collect($state['jobs'])
            ->where('status', 'failed')
            ->count();

        $this->write($state);
    }

    protected function write(array $state): void
    {
        $path = $this->path();

        if (! $this->filesystem->isDirectory(dirname($path))) {
            $this->filesystem->makeDirectory(dirname($path), 0755, true, true);
        }

        $this->filesystem->put(
            $path,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL
        );
    }
}
