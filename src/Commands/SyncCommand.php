<?php

namespace DigitalCoreHub\LaravelAiTranslator\Commands;

use DigitalCoreHub\LaravelAiTranslator\Jobs\ProcessTranslationJob;
use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use DigitalCoreHub\LaravelAiTranslator\Support\AiTranslatorLogger;
use DigitalCoreHub\LaravelAiTranslator\Support\QueueMonitor;
use DigitalCoreHub\LaravelAiTranslator\Support\ReportStore;
use Illuminate\Console\Command;

class SyncCommand extends Command
{
    protected $signature = 'ai:sync
        {--from= : Source locale}
        {--to=* : Target locales}
        {--provider= : Provider override}
        {--queue : Dispatch the work to the queue}
        {--force : Force re-translation of existing keys}
    ';

    protected $description = 'Synchronize missing translations across all locales.';

    public function handle(TranslationManager $manager, ReportStore $reports, QueueMonitor $monitor): int
    {
        $from = $this->option('from') ?: config('app.locale');
        $targets = $this->resolveTargets($manager, $from);
        $provider = $this->option('provider') ?: config('ai-translator.provider', 'openai');
        $force = (bool) $this->option('force');
        $queue = (bool) $this->option('queue');

        if ($targets === []) {
            $this->components->warn('No target locales found for synchronization.');

            return self::FAILURE;
        }

        foreach ($targets as $target) {
            if ($queue) {
                $files = collect($manager->gatherEntries($from, $target))
                    ->pluck('file')
                    ->unique()
                    ->values();

                $this->components->info(sprintf(
                    'Dispatching %d jobs for %s → %s.',
                    $files->count(),
                    strtoupper($from),
                    strtoupper($target)
                ));

                foreach ($files as $file) {
                    $job = new ProcessTranslationJob($from, $target, $file, $provider, $force);

                    $monitor->markQueued($job->trackingId, [
                        'file' => $file,
                        'from' => $from,
                        'to' => $target,
                        'provider' => $provider,
                    ]);

                    AiTranslatorLogger::sync(sprintf(
                        'Sync command queued %s (%s → %s).',
                        $file,
                        $from,
                        $target
                    ));

                    dispatch($job);
                }

                continue;
            }

            $result = $manager->translate(
                $from,
                $target,
                progress: null,
                dryRun: false,
                force: $force,
                options: ['provider' => $provider]
            );

            $reports->appendTranslationRun(
                $from,
                $target,
                $provider,
                $result['report'],
                ['executed_at' => now()->toIso8601String()]
            );

            AiTranslatorLogger::sync(sprintf(
                'Sync completed %s → %s missing=%d translated=%d force=%s',
                strtoupper($from),
                strtoupper($target),
                $result['totals']['missing'],
                $result['totals']['translated'],
                $force ? 'true' : 'false'
            ));

            $this->components->info(sprintf(
                'Completed sync for %s → %s. Translated %d keys.',
                strtoupper($from),
                strtoupper($target),
                $result['totals']['translated']
            ));
        }

        return self::SUCCESS;
    }

    protected function resolveTargets(TranslationManager $manager, string $from): array
    {
        $targets = $this->option('to');

        if ($targets === [] || $targets === null) {
            $targets = array_diff($manager->availableLocales(), [$from]);
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($locale) => strtolower((string) $locale),
            $targets ?? []
        ))));
    }
}
