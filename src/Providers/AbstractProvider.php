<?php

namespace DigitalCoreHub\LaravelAiTranslator\Providers;

use DigitalCoreHub\LaravelAiTranslator\Contracts\TranslationProvider;
use Illuminate\Support\Arr;

/**
 * Base class for translation providers with shared helpers.
 * Paylaşılan yardımcıları içeren çeviri sağlayıcıları için temel sınıf.
 */
abstract class AbstractProvider implements TranslationProvider
{
    public function __construct(protected array $config = [])
    {
    }

    /**
     * Unique provider identifier used within the manager.
     * Yöneticide kullanılan benzersiz sağlayıcı tanımlayıcısı.
     */
    abstract public function name(): string;

    /**
     * Fetch a configuration value for the provider.
     * Sağlayıcıya ait yapılandırma değerini getirir.
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }
}
