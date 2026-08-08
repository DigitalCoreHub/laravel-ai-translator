<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Support;

use DigitalCoreHub\LaravelAiTranslator\Data\TranslationRun;
use Illuminate\Filesystem\Filesystem;

/**
 * Koşu özetlerini tek bir JSON dosyasına yazar.
 *
 * "keep" sınırı var: v0.x'te rapor dosyası sonsuza kadar büyüyordu, birkaç bin koşudan
 * sonra dosyayı okuyan her şey yavaşlıyordu.
 */
class ReportStore
{
    public function __construct(
        protected Filesystem $filesystem,
        protected string $path,
        protected int $keep = 50,
        protected bool $enabled = true,
    ) {}

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        if (! $this->filesystem->exists($this->path)) {
            return [];
        }

        $decoded = json_decode((string) $this->filesystem->get($this->path), true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    public function record(TranslationRun $run): void
    {
        if (! $this->enabled) {
            return;
        }

        $runs = $this->all();
        $runs[] = ['executed_at' => now()->toIso8601String()] + $run->toArray();

        if ($this->keep > 0 && count($runs) > $this->keep) {
            $runs = array_slice($runs, -$this->keep);
        }

        $this->filesystem->ensureDirectoryExists(dirname($this->path));

        $this->filesystem->put(
            $this->path,
            json_encode(array_values($runs), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL
        );
    }

    public function clear(): void
    {
        $this->filesystem->delete($this->path);
    }
}
