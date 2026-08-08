<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Data;

/**
 * Çevrilemeyen anahtar.
 *
 * v0.x placeholder kaybolduğunda sessizce kaynak metni geri yazıyordu; kullanıcı
 * İngilizce metnin Türkçe dosyaya düştüğünü ancak gözüyle görerek fark ediyordu.
 * Artık böyle durumlar dosyaya yazılmıyor, buraya düşüyor ve raporda görünüyor.
 */
final readonly class FailedKey
{
    public function __construct(
        public string $file,
        public string $key,
        public string $source,
        public string $reason,
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
            'reason' => $this->reason,
        ];
    }
}
