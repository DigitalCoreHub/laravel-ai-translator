<?php

namespace DigitalCoreHub\LaravelAiTranslator;

use DigitalCoreHub\LaravelAiTranslator\Commands\SyncCommand;
use DigitalCoreHub\LaravelAiTranslator\Commands\TranslateCommand;
use DigitalCoreHub\LaravelAiTranslator\Commands\WatchCommand;
use DigitalCoreHub\LaravelAiTranslator\Contracts\TranslationProvider;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Dashboard;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\EditTranslation;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Logs;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\QueueStatus;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Settings;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Sync as SyncComponent;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\WatchLogs;
use DigitalCoreHub\LaravelAiTranslator\Providers\DeepLProvider;
use DigitalCoreHub\LaravelAiTranslator\Providers\DeepSeekProvider;
use DigitalCoreHub\LaravelAiTranslator\Providers\GoogleProvider;
use DigitalCoreHub\LaravelAiTranslator\Providers\OpenAIProvider;
use DigitalCoreHub\LaravelAiTranslator\Services\TranslationCache;
use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use DigitalCoreHub\LaravelAiTranslator\Support\AiTranslatorLogger;
use DigitalCoreHub\LaravelAiTranslator\Support\QueueMonitor;
use DigitalCoreHub\LaravelAiTranslator\Support\ReportStore;
use Illuminate\Auth\Events\Login as AuthLoginEvent;
use Illuminate\Auth\Events\Logout as AuthLogoutEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

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

        $this->app->singleton(ReportStore::class, function ($app) {
            return new ReportStore($app['files']);
        });

        $this->app->singleton(QueueMonitor::class, function ($app) {
            return new QueueMonitor($app['files']);
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

        $this->registerLivewireComponents();

        Event::listen(AuthLoginEvent::class, static function (AuthLoginEvent $event): void {
            if (! config('ai-translator.auth_enabled', true)) {
                return;
            }

            $email = $event->user->email ?? 'unknown';

            AiTranslatorLogger::info(sprintf('User %s logged in.', $email));
        });

        Event::listen(AuthLogoutEvent::class, static function (AuthLogoutEvent $event): void {
            if (! config('ai-translator.auth_enabled', true)) {
                return;
            }

            $email = $event->user?->email ?? 'unknown';

            AiTranslatorLogger::info(sprintf('User %s logged out.', $email));
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncCommand::class,
                TranslateCommand::class,
                WatchCommand::class,
            ]);
        }
    }

    protected function registerLivewireComponents(): void
    {
        if (! class_exists(\Livewire\Livewire::class)) {
            return;
        }

        foreach ([
            Dashboard::class,
            EditTranslation::class,
            QueueStatus::class,
            Settings::class,
            Logs::class,
            SyncComponent::class,
            WatchLogs::class,
        ] as $component) {
            \Livewire\Livewire::component(
                $this->livewireComponentAlias($component),
                $component
            );
        }
    }

    protected function livewireComponentAlias(string $component): string
    {
        $alias = str_replace('\\', '.', $component);
        $alias = preg_replace('/(?<!^|\.)(?=[A-Z])/', '-', $alias) ?? $alias;

        return strtolower($alias);
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
