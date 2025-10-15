<?php

namespace DigitalCoreHub\LaravelAiTranslator;

use DigitalCoreHub\LaravelAiTranslator\Commands\TranslateCommand;
use DigitalCoreHub\LaravelAiTranslator\Contracts\TranslationProvider;
use DigitalCoreHub\LaravelAiTranslator\Providers\OpenAIProvider;
use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
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
            return $app->make($this->resolveProviderClass());
        });

        $this->app->singleton(TranslationManager::class, function ($app) {
            return new TranslationManager(
                provider: $app->make(TranslationProvider::class),
                filesystem: $app['files'],
                basePath: $app->basePath(),
                autoCreateMissingFiles: $app['config']->get('ai-translator.auto_create_missing_files', true),
            );
        });

        $this->app->singleton(OpenAIProvider::class, function ($app) {
            return new OpenAIProvider(
                apiKey: $app['config']->get('ai-translator.openai.api_key'),
                model: $app['config']->get('ai-translator.openai.model')
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
            default => $provider,
        };
    }
}
