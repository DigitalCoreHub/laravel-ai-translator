<?php

namespace DigitalCoreHub\LaravelAiTranslator\Commands;

use DigitalCoreHub\LaravelAiTranslator\Jobs\ProcessTranslationJob;
use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use DigitalCoreHub\LaravelAiTranslator\Services\TranslationWatcher;
use DigitalCoreHub\LaravelAiTranslator\Support\AiTranslatorLogger;
use DigitalCoreHub\LaravelAiTranslator\Support\QueueMonitor;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class WatchCommand extends Command
{
    protected $signature = 'ai:watch
        {--from= : Source locale that will be treated as authority}
        {--to=* : Target locales to translate}
        {--provider= : Preferred translation provider}
        {--sleep=2 : Sleep time between scans in seconds}
        {--force : Force re-translation of existing keys}
        {--once : Run a single scan and exit}
    ';

    protected $description = 'Watch language directories and queue background translation jobs when changes are detected.';

    public function handle(TranslationWatcher $watcher, TranslationManager $manager, QueueMonitor $monitor): int
    {
        if (! config('ai-translator.watch_enabled', true)) {
            $this->components->warn('Watch mode is disabled. Enable AI_TRANSLATOR_WATCH_ENABLED to activate.');

            return self::SUCCESS;
        }

        $paths = config('ai-translator.watch_paths', [
            base_path('lang'),
            base_path('resources/lang'),
        ]);

        $from = $this->option('from') ?: config('app.locale');
        $targets = $this->resolveTargets($manager, $from);

        if ($targets === []) {
            $this->components->warn('No target locales resolved for watch mode.');

            return self::FAILURE;
        }

        $provider = $this->option('provider') ?: config('ai-translator.provider', 'openai');
        $sleep = max(1, (int) $this->option('sleep'));
        $force = (bool) $this->option('force');

        $this->components->info(sprintf(
            'Watching %d paths for %s -> [%s] using %s provider.',
            count($paths),
            strtoupper($from),
            implode(', ', array_map('strtoupper', $targets)),
            $provider
        ));

        $dispatch = function (string $path) use ($from, $targets, $provider, $force, $monitor) {
            $relative = $this->relative($path);

            if ($relative === null) {
                return;
            }

            if (! $this->belongsToLocale($relative, $from)) {
                return;
            }

            AiTranslatorLogger::watch(sprintf('Watcher: Detected change in %s — queued for translation.', $relative));

            foreach ($targets as $target) {
                if ($target === $from) {
                    continue;
                }

                $targetRelative = $this->targetRelative($relative, $from, $target);
                $job = new ProcessTranslationJob($from, $target, $targetRelative, $provider, $force);

                $monitor->markQueued($job->trackingId, [
                    'file' => $targetRelative,
                    'from' => $from,
                    'to' => $target,
                    'provider' => $provider,
                ]);

                dispatch($job);
            }
        };

        if ($this->option('once')) {
            $since = microtime(true) - 5;
            foreach ($watcher->scan($paths, $since) as $file => $meta) {
                $dispatch($file);
            }

            return self::SUCCESS;
        }

        $watcher->watch($paths, function (string $file) use ($dispatch) {
            $dispatch($file);
        }, $sleep);

        return self::SUCCESS;
    }

    protected function resolveTargets(TranslationManager $manager, string $from): array
    {
        $targets = $this->option('to');

        if ($targets === [] || $targets === null) {
            $configured = config('ai-translator.watch_targets', []);

            if (is_string($configured)) {
                $targets = array_filter(array_map('trim', explode(',', $configured)));
            } elseif (is_array($configured)) {
                $targets = $configured;
            }
        }

        if ($targets === [] || $targets === null) {
            $targets = array_diff($manager->availableLocales(), [$from]);
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($locale) => strtolower((string) $locale),
            $targets ?? []
        ))));
    }

    protected function relative(string $path): ?string
    {
        $base = rtrim(base_path(), '/');
        $normalized = str_replace('\', '/', $path);

        if (! Str::startsWith($normalized, $base)) {
            return null;
        }

        return ltrim(Str::after($normalized, $base.'/'), '/');
    }

    protected function belongsToLocale(string $relative, string $locale): bool
    {
        if (str_contains($relative, '/'.$locale.'/')) {
            return true;
        }

        if (str_ends_with($relative, '/'.$locale.'.json')) {
            return true;
        }

        return str_ends_with($relative, $locale.'.json');
    }

    protected function targetRelative(string $relative, string $from, string $to): string
    {
        if (str_contains($relative, '/'.$from.'/')) {
            return preg_replace('/\/'.$from.'\//', '/'.$to.'/', $relative, 1) ?? $relative;
        }

        if (str_ends_with($relative, '/'.$from.'.json')) {
            return substr($relative, 0, -strlen($from.'.json')).$to.'.json';
        }

        if (str_ends_with($relative, $from.'.json')) {
            return substr($relative, 0, -strlen($from.'.json')).$to.'.json';
        }

        return $relative;
    }
}
