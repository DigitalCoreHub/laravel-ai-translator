<?php

namespace DigitalCoreHub\LaravelAiTranslator;

use DigitalCoreHub\LaravelAiTranslator\Commands\TranslateCommand;
use DigitalCoreHub\LaravelAiTranslator\Contracts\TranslationProvider;
use DigitalCoreHub\LaravelAiTranslator\Providers\DeepLProvider;
use DigitalCoreHub\LaravelAiTranslator\Providers\DeepSeekProvider;
use DigitalCoreHub\LaravelAiTranslator\Providers\GoogleProvider;
use DigitalCoreHub\LaravelAiTranslator\Providers\OpenAIProvider;
use DigitalCoreHub\LaravelAiTranslator\Services\TranslationCache;
use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use Illuminate\Support\ServiceProvider;
use Livewire\Volt;

/**
 * Bootstrap bindings and configuration for the AI translator package.
 * AI çeviri paketinin yapılandırmalarını ve servislerini kaydeder.
 */
class AiTranslatorServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     * Servis konteynerine bağımlılıkları kaydeder.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-translator.php', 'ai-translator');

        $this->app->singleton(TranslationProvider::class, function ($app) {
            $provider = $app['config']->get('ai-translator.provider', 'openai');

            return $this->makeProvider($provider);
        });

        $this->app->singleton('ai-translator.providers', function ($app) {
            $providers = [];

            foreach (array_keys($app['config']->get('ai-translator.providers', [])) as $name) {
                $providers[$name] = $this->makeProvider($name);
            }

            return $providers;
        });

        $this->app->singleton(TranslationCache::class, function ($app) {
            $config = $app['config']->get('ai-translator');
            $driver = $config['cache_driver'] ?? $app['config']->get('cache.default');

            return new TranslationCache(
                repository: $app['cache']->store($driver),
                enabled: (bool) ($config['cache_enabled'] ?? true)
            );
        });

        $this->app->singleton(TranslationManager::class, function ($app) {
            $config = $app['config']->get('ai-translator');

            return new TranslationManager(
                providers: $app->make('ai-translator.providers'),
                cache: $app->make(TranslationCache::class),
                filesystem: $app['files'],
                basePath: $app->basePath(),
                paths: $config['paths'] ?? [base_path('lang')],
                configuredProvider: $config['provider'] ?? 'openai',
                autoCreateMissingFiles: $config['auto_create_missing_files'] ?? true,
            );
        });

        $this->app->singleton(OpenAIProvider::class, function ($app) {
            return new OpenAIProvider(
                config: $app['config']->get('ai-translator.providers.openai', [])
            );
        });

        $this->app->singleton(DeepLProvider::class, function ($app) {
            return new DeepLProvider(
                config: $app['config']->get('ai-translator.providers.deepl', [])
            );
        });

        $this->app->singleton(GoogleProvider::class, function ($app) {
            return new GoogleProvider(
                config: $app['config']->get('ai-translator.providers.google', [])
            );
        });

        $this->app->singleton(DeepSeekProvider::class, function ($app) {
            return new DeepSeekProvider(
                config: $app['config']->get('ai-translator.providers.deepseek', [])
            );
        });
    }

    /**
     * Bootstrap any application services.
     * Uygulama servislerini başlatır.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/ai-translator.php' => config_path('ai-translator.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/ai-translator'),
        ], 'views');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ai-translator');
        $this->loadRoutesFrom(__DIR__.'/../routes/ai-translator.php');

        Volt::layout('ai-translator::layouts.app');

        if ($this->app->runningInConsole()) {
            $this->commands([
                TranslateCommand::class,
            ]);
        }
    }

    /**
     * Resolve the configured translation provider class name.
     * Yapılandırılan çeviri sağlayıcısının sınıf adını çözümler.
     */
    protected function resolveProviderClass(): string
    {
        $provider = $this->app['config']->get('ai-translator.provider', 'openai');

        return match ($provider) {
            'openai' => OpenAIProvider::class,
            'deepl' => DeepLProvider::class,
            'google' => GoogleProvider::class,
            'deepseek' => DeepSeekProvider::class,
            default => $provider,
        };
    }

    protected function makeProvider(string $name)
    {
        $config = $this->app['config']->get("ai-translator.providers.{$name}", []);
        $class = $config['class'] ?? match ($name) {
            'openai' => OpenAIProvider::class,
            'deepl' => DeepLProvider::class,
            'google' => GoogleProvider::class,
            'deepseek' => DeepSeekProvider::class,
            default => $name,
        };

        if (is_subclass_of($class, \DigitalCoreHub\LaravelAiTranslator\Providers\AbstractProvider::class)) {
            return $this->app->make($class, ['config' => $config]);
        }

        return $this->app->make($class);
    }
}
