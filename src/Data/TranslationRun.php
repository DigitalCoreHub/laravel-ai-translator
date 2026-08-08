<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Bir çeviri koşusunun tamamı. Facade'in ve komutların döndürdüğü nesne.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class TranslationRun implements Arrayable
{
    /**
     * @param  array<int, FileOutcome>  $files
     */
    public function __construct(
        public string $from,
        public string $to,
        public array $files = [],
        public bool $dryRun = false,
        public bool $force = false,
        public float $durationMs = 0.0,
    ) {}

    public function missingCount(): int
    {
        return array_sum(array_map(static fn (FileOutcome $f) => $f->missing, $this->files));
    }

    public function translatedCount(): int
    {
        return array_sum(array_map(static fn (FileOutcome $f) => $f->translatedCount(), $this->files));
    }

    public function failedCount(): int
    {
        return array_sum(array_map(static fn (FileOutcome $f) => $f->failedCount(), $this->files));
    }

    public function cacheHits(): int
    {
        return array_sum(array_map(static fn (FileOutcome $f) => $f->cacheHits(), $this->files));
    }

    public function hasFailures(): bool
    {
        return $this->failedCount() > 0;
    }

    /**
     * Bütün dosyalardaki çevrilmiş anahtarları düz bir listede verir (--review için).
     *
     * @return array<int, TranslatedKey>
     */
    public function allTranslated(): array
    {
        return array_merge(...array_map(static fn (FileOutcome $f) => $f->translated, $this->files)) ?: [];
    }

    /**
     * @return array<int, FailedKey>
     */
    public function allFailed(): array
    {
        return array_merge(...array_map(static fn (FileOutcome $f) => $f->failed, $this->files)) ?: [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'dry_run' => $this->dryRun,
            'force' => $this->force,
            'totals' => [
                'missing' => $this->missingCount(),
                'translated' => $this->translatedCount(),
                'failed' => $this->failedCount(),
                'cache_hits' => $this->cacheHits(),
            ],
            'duration_ms' => round($this->durationMs, 2),
            'files' => array_map(static fn (FileOutcome $f) => $f->toArray(), $this->files),
        ];
    }
}
