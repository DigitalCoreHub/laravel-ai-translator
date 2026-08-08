<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Data;

/**
 * Hedef dilde karşılığı olmayan (ya da --force ile yeniden çevrilecek) tek bir anahtar.
 */
final readonly class MissingKey
{
    public function __construct(
        /** Dil köküne göre hedef dosya yolu, ör. "tr/auth.php" */
        public string $file,
        /** Nokta gösterimli anahtar, ör. "throttle.title" */
        public string $key,
        /** Kaynak dildeki metin */
        public string $source,
        /** Hedef dilde şu an ne varsa (yoksa null) */
        public ?string $current = null,
    ) {}

    /**
     * Anahtar hedef dosyada hiç yok mu, yoksa var ama boş mu?
     */
    public function isEmpty(): bool
    {
        return $this->current !== null && trim($this->current) === '';
    }

    public function status(): string
    {
        return match (true) {
            $this->current === null => 'missing',
            trim($this->current) === '' => 'empty',
            default => 'translated',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'key' => $this->key,
            'source' => $this->source,
            'current' => $this->current,
            'status' => $this->status(),
        ];
    }
}
