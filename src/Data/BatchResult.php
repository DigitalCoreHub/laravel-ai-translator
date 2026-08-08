<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Data;

use Throwable;

/**
 * ProviderChain'in tek bir grup için döndürdüğü sonuç.
 */
final readonly class BatchResult
{
    /**
     * @param  array<string, string>  $translations  anahtar => çeviri
     * @param  array<string, string>  $providers  anahtar => çeviriyi yapan sağlayıcı
     * @param  array<int, string>  $untranslated  hiçbir sağlayıcının döndüremediği anahtarlar
     * @param  array<string, Throwable>  $failures  sağlayıcı => hata
     */
    public function __construct(
        public array $translations = [],
        public array $providers = [],
        public array $untranslated = [],
        public array $failures = [],
    ) {}

    public function providerFor(string $key): string
    {
        return $this->providers[$key] ?? 'unknown';
    }

    /**
     * Çevrilemeyen anahtarlar için insan okuyabilir bir sebep üretir.
     */
    public function reason(): string
    {
        if ($this->failures === []) {
            return 'Sağlayıcı bu anahtar için çeviri döndürmedi.';
        }

        return collect($this->failures)
            ->map(static fn (Throwable $e, string $provider) => $provider.': '.$e->getMessage())
            ->implode(' | ');
    }
}
