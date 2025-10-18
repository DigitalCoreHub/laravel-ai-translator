<?php

namespace DigitalCoreHub\LaravelAiTranslator\Tests;

use DigitalCoreHub\LaravelAiTranslator\AiTranslatorServiceProvider;
use DigitalCoreHub\LaravelAiTranslator\Tests\Fakes\FakeProvider;
use DigitalCoreHub\LaravelAiTranslator\Tests\Stubs\User;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base test case for the Laravel AI Translator package.
 * Laravel AI Translator paketi için temel test sınıfı.
 */
abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations();
    }

    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(static function () use ($router) {
            $router->get('/login', static fn () => 'Login')->name('login');
        });
    }

    protected function getPackageProviders($app)
    {
        return [AiTranslatorServiceProvider::class];
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('ai-translator.provider', 'openai');
        $app['config']->set('ai-translator.providers.openai.class', FakeProvider::class);
        $app['config']->set('ai-translator.cache_enabled', false);
        $app['config']->set('ai-translator.paths', [base_path('lang')]);
        $app['config']->set('session.driver', 'array');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('auth.defaults.guard', 'web');
        $app['config']->set('auth.guards.web', [
            'driver' => 'session',
            'provider' => 'users',
        ]);
    }
}
