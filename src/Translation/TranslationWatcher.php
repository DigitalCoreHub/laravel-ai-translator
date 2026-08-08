<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Translation;

use DigitalCoreHub\LaravelAiTranslator\Data\LocaleFilePair;
use DigitalCoreHub\LaravelAiTranslator\Jobs\TranslateFileJob;

/**
 * Kaynak dil dosyalarının değişimini izler ve değişenleri kuyruğa atar.
 *
 * Komuttan ayrı bir sınıf: hem test edilebilir olsun hem de isteyen kendi
 * scheduler'ından tick() çağırıp sonsuz döngü kurmadan aynı işi yapabilsin.
 */
class TranslationWatcher
{
    /** @var array<string, int>  göreli yol => son görülen mtime */
    protected array $seen = [];

    /** @var (callable(string, int): void)|null */
    protected $onChange = null;

    public function __construct(
        protected Translator $translator,
        protected string $from,
        /** @var array<int, string> */
        protected array $targets,
        protected ?string $provider = null,
        protected ?string $only = null,
        protected ?string $connection = null,
        protected string $queue = 'ai-translations',
    ) {}

    /**
     * @param  (callable(string, int): void)|null  $callback  (göreli yol, üretilen iş sayısı)
     */
    public function onChange(?callable $callback): static
    {
        $this->onChange = $callback;

        return $this;
    }

    /**
     * Mevcut durumu "görülmüş" olarak işaretler; komut açılır açılmaz
     * bütün dosyaları kuyruğa doldurmasın diye.
     */
    public function prime(): static
    {
        foreach ($this->sources() as $relative => $path) {
            $this->seen[$relative] = $this->modifiedAt($path);
        }

        return $this;
    }

    /**
     * Tek tarama turu. Değişen her dosya için her hedef dile bir iş üretir.
     *
     * @return int kuyruğa alınan iş sayısı
     */
    public function tick(): int
    {
        $dispatched = 0;

        foreach ($this->sources() as $relative => $path) {
            $modified = $this->modifiedAt($path);

            if (($this->seen[$relative] ?? 0) >= $modified) {
                continue;
            }

            $this->seen[$relative] = $modified;

            $jobs = 0;

            foreach ($this->targets as $to) {
                TranslateFileJob::dispatch(
                    file: $relative,
                    from: $this->from,
                    to: $to,
                    provider: $this->provider,
                )->onConnection($this->connection)->onQueue($this->queue);

                $jobs++;
            }

            $dispatched += $jobs;

            if ($this->onChange !== null) {
                ($this->onChange)($relative, $jobs);
            }
        }

        return $dispatched;
    }

    /**
     * İzlenen kaynak dosyalar.
     *
     * Hedef dil olarak listedeki ilkini kullanıyoruz; kaynak dosya kümesi
     * hedeften bağımsız olduğu için hangisi olduğu fark etmiyor.
     *
     * @return array<string, string> göreli yol => mutlak yol
     */
    protected function sources(): array
    {
        $paths = [];

        $scoped = $this->translator
            ->from($this->from)
            ->to($this->targets[0] ?? $this->from)
            ->only($this->only);

        foreach ($scoped->pairs() as $pair) {
            /** @var LocaleFilePair $pair */
            $paths[$pair->sourceRelative] = $pair->sourcePath;
        }

        return $paths;
    }

    protected function modifiedAt(string $path): int
    {
        clearstatcache(true, $path);

        return is_file($path) ? (int) filemtime($path) : 0;
    }
}
