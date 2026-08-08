<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Tests;

use DigitalCoreHub\LaravelAiTranslator\AiTranslatorServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected string $langPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->langPath = $this->app->basePath('lang');

        (new Filesystem)->ensureDirectoryExists($this->langPath);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->langPath);

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [AiTranslatorServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai-translator.provider', 'null');
        $app['config']->set('ai-translator.fallback', []);
        $app['config']->set('ai-translator.paths', [$app->basePath('lang')]);
        $app['config']->set('ai-translator.cache.enabled', false);
        $app['config']->set('cache.default', 'array');
    }

    /**
     * Test dil dosyası yazar. İçerik dizi ise PHP, string ise ham yazılır.
     *
     * @param  array<array-key, mixed>|string  $contents
     */
    protected function writeLangFile(string $relative, array|string $contents): string
    {
        $path = $this->langPath.'/'.ltrim($relative, '/');

        (new Filesystem)->ensureDirectoryExists(dirname($path));

        if (is_string($contents)) {
            file_put_contents($path, $contents);

            return $path;
        }

        if (str_ends_with($relative, '.json')) {
            file_put_contents($path, json_encode($contents, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $path;
        }

        file_put_contents($path, '<?php return '.var_export($contents, true).';');

        return $path;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function readLangFile(string $relative): array
    {
        $path = $this->langPath.'/'.ltrim($relative, '/');

        if (str_ends_with($relative, '.json')) {
            return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        }

        return (static fn (string $file) => require $file)($path);
    }
}
