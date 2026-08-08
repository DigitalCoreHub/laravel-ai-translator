<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Data;

/**
 * Tek bir dil dosyası için çeviri sonucu.
 */
final readonly class FileOutcome
{
    /**
     * @param  array<int, TranslatedKey>  $translated
     * @param  array<int, FailedKey>  $failed
     */
    public function __construct(
        /** Dil köküne göre hedef dosya yolu, ör. "tr/auth.php" */
        public string $file,
        /** İşe başlarken eksik olan anahtar sayısı */
        public int $missing,
        public array $translated = [],
        public array $failed = [],
        /** Dosyaya gerçekten yazıldı mı (--dry ise hep false) */
        public bool $written = false,
        public float $durationMs = 0.0,
    ) {}

    public function translatedCount(): int
    {
        return count($this->translated);
    }

    public function failedCount(): int
    {
        return count($this->failed);
    }

    public function cacheHits(): int
    {
        return count(array_filter($this->translated, static fn (TranslatedKey $k) => $k->fromCache));
    }

    /**
     * Bu dosyada en çok hangi sağlayıcı iş gördü?
     */
    public function primaryProvider(): ?string
    {
        $counts = [];

        foreach ($this->translated as $key) {
            $counts[$key->provider] = ($counts[$key->provider] ?? 0) + 1;
        }

        if ($counts === []) {
            return null;
        }

        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'missing' => $this->missing,
            'translated' => $this->translatedCount(),
            'failed' => $this->failedCount(),
            'cache_hits' => $this->cacheHits(),
            'primary_provider' => $this->primaryProvider(),
            'written' => $this->written,
            'duration_ms' => round($this->durationMs, 2),
        ];
    }
}
