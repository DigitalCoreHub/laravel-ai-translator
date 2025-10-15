<?php

namespace DigitalCoreHub\LaravelAiTranslator\Tests;

use DigitalCoreHub\LaravelAiTranslator\AiTranslatorServiceProvider;
use DigitalCoreHub\LaravelAiTranslator\Tests\Fakes\FakeProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base test case for the Laravel AI Translator package.
 * Laravel AI Translator paketi için temel test sınıfı.
 */
abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [AiTranslatorServiceProvider::class];
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('ai-translator.provider', FakeProvider::class);
    }
}
