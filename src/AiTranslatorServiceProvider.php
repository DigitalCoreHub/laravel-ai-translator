<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator;

use DigitalCoreHub\LaravelAiTranslator\Cache\TranslationCache;
use DigitalCoreHub\LaravelAiTranslator\Commands\CacheClearCommand;
use DigitalCoreHub\LaravelAiTranslator\Commands\StatusCommand;
use DigitalCoreHub\LaravelAiTranslator\Commands\TranslateCommand;
use DigitalCoreHub\LaravelAiTranslator\Commands\WatchCommand;
use DigitalCoreHub\LaravelAiTranslator\Providers\ProviderChain;
use DigitalCoreHub\LaravelAiTranslator\Providers\ProviderRegistry;
use DigitalCoreHub\LaravelAiTranslator\Support\ReportStore;
use DigitalCoreHub\LaravelAiTranslator\Translation\LocaleFileRepository;
use DigitalCoreHub\LaravelAiTranslator\Translation\LocaleScanner;
use DigitalCoreHub\LaravelAiTranslator\Translation\PlaceholderGuard;
use DigitalCoreHub\LaravelAiTranslator\Translation\Translator;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

class AiTranslatorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-translator.php', 'ai-translator');

        $this->app->singleton(ProviderRegistry::class, fn ($app) => new ProviderRegistry(
            $app,
            (array) $app['config']->get('ai-translator.providers', [])
        ));

        $this->app->singleton(ProviderChain::class, function ($app) {
            /** @var ConfigRepository $config */
            $config = $app['config'];

            return new ProviderChain(
                registry: $app->make(ProviderRegistry::class),
                order: $this->providerOrder($config),
            );
        });

        $this->app->singleton(TranslationCache::class, function ($app) {
            /** @var ConfigRepository $config */
            $config = $app['config'];
            $store = $config->get('ai-translator.cache.store');

            return new TranslationCache(
                repository: $app['cache']->store(is_string($store) && $store !== '' ? $store : null),
                enabled: (bool) $config->get('ai-translator.cache.enabled', true),
                ttl: $this->cacheTtl($config),
            );
        });

        $this->app->singleton(LocaleFileRepository::class, fn ($app) => new LocaleFileRepository(
            filesystem: $app->make(Filesystem::class),
            roots: $this->localeRoots($app['config']),
        ));

        $this->app->singleton(LocaleScanner::class, fn ($app) => new LocaleScanner(
            files: $app->make(LocaleFileRepository::class),
        ));

        $this->app->singleton(ReportStore::class, function ($app) {
            /** @var ConfigRepository $config */
            $config = $app['config'];

            return new ReportStore(
                filesystem: $app->make(Filesystem::class),
                path: (string) $config->get('ai-translator.report.path', storage_path('logs/ai-translator-report.json')),
                keep: (int) $config->get('ai-translator.report.keep', 50),
                enabled: (bool) $config->get('ai-translator.report.enabled', true),
            );
        });

        $this->app->singleton(Translator::class, fn ($app) => new Translator(
            scanner: $app->make(LocaleScanner::class),
            files: $app->make(LocaleFileRepository::class),
            chain: $app->make(ProviderChain::class),
            cache: $app->make(TranslationCache::class),
            guard: new PlaceholderGuard,
            events: $app['events'],
        ));

        $this->app->alias(Translator::class, 'ai-translator');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ai-translator.php' => config_path('ai-translator.php'),
            ], 'ai-translator-config');

            $this->commands([
                TranslateCommand::class,
                StatusCommand::class,
                CacheClearCommand::class,
                WatchCommand::class,
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function providerOrder(ConfigRepository $config): array
    {
        $primary = (string) $config->get('ai-translator.provider', 'openai');
        $fallback = $config->get('ai-translator.fallback', []);

        return array_values(array_unique(array_filter(
            array_merge([$primary], is_array($fallback) ? $fallback : []),
            static fn ($name) => is_string($name) && $name !== '',
        )));
    }

    /**
     * Dil köklerini çözer. Config virgüllü string ya da dizi olabilir; göreli
     * yollar base_path()'e göre mutlaklaştırılır.
     *
     * @return array<int, string>
     */
    protected function localeRoots(ConfigRepository $config): array
    {
        $paths = $config->get('ai-translator.paths', 'lang');
        $segments = is_array($paths)
            ? $paths
            : explode(',', (string) $paths);

        $roots = [];

        foreach ($segments as $segment) {
            $segment = trim((string) $segment);

            if ($segment === '') {
                continue;
            }

            $roots[] = str_starts_with($segment, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $segment) === 1
                ? $segment
                : $this->app->basePath($segment);
        }

        return $roots === [] ? [$this->app->basePath('lang')] : $roots;
    }

    protected function cacheTtl(ConfigRepository $config): ?int
    {
        $ttl = $config->get('ai-translator.cache.ttl');

        return $ttl === null ? null : max(0, (int) $ttl);
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            Translator::class,
            'ai-translator',
            ProviderChain::class,
            ProviderRegistry::class,
            TranslationCache::class,
            LocaleFileRepository::class,
            LocaleScanner::class,
            ReportStore::class,
        ];
    }
}
