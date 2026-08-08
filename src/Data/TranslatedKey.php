<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Data;

/**
 * Başarıyla çevrilmiş tek bir anahtar; --review ve rapor çıktılarının hammaddesi.
 */
final readonly class TranslatedKey
{
    public function __construct(
        public string $file,
        public string $key,
        public string $source,
        public string $translation,
        public string $provider,
        public bool $fromCache = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'key' => $this->key,
            'source' => $this->source,
            'translation' => $this->translation,
            'provider' => $this->provider,
            'cached' => $this->fromCache,
        ];
    }
}
