<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Translation;

use DigitalCoreHub\LaravelAiTranslator\Data\LocaleFilePair;
use DigitalCoreHub\LaravelAiTranslator\Data\MissingKey;

/**
 * Hangi anahtarın çevrilmesi gerektiğine karar veren sınıf.
 *
 * "Eksik" derken üç durumu birden kastediyoruz:
 *   1. Anahtar hedef dosyada hiç yok,
 *   2. Var ama değeri boş string,
 *   3. --force verilmişse hepsi.
 */
class LocaleScanner
{
    public function __construct(protected LocaleFileRepository $files) {}

    /**
     * Verilen dil çifti için çevrilmesi gereken anahtarları toplar.
     *
     * @return array<int, MissingKey>
     */
    public function missing(string $from, string $to, bool $force = false, ?string $only = null): array
    {
        $missing = [];

        foreach ($this->files->pairs($from, $to, $only) as $pair) {
            $missing = array_merge($missing, $this->missingForPair($pair, $force));
        }

        return $missing;
    }

    /**
     * Tek bir dosya çifti için eksikleri çıkarır.
     *
     * @return array<int, MissingKey>
     */
    public function missingForPair(LocaleFilePair $pair, bool $force = false): array
    {
        $source = KeyPath::flatten($this->files->read($pair->sourcePath));
        $target = KeyPath::flatten(
            $this->files->exists($pair->targetPath) ? $this->files->read($pair->targetPath) : []
        );

        $missing = [];

        foreach ($source as $key => $value) {
            // Sadece metinleri çeviriyoruz. Sayı, bool, boş dizi gibi değerler
            // (ör. 'retry_after' => 60) olduğu gibi kopyalanır, modele gitmez.
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $current = array_key_exists($key, $target) && is_string($target[$key])
                ? $target[$key]
                : null;

            $needsWork = $force
                || ! array_key_exists($key, $target)
                || ($current !== null && trim($current) === '');

            if (! $needsWork) {
                continue;
            }

            $missing[] = new MissingKey(
                file: $pair->targetRelative,
                key: $key,
                source: $value,
                current: $current,
            );
        }

        return $missing;
    }

    /**
     * Bir dil çiftindeki bütün anahtarların durumunu döndürür (status komutu için).
     *
     * @return array<int, MissingKey>
     */
    public function entries(string $from, string $to, ?string $only = null): array
    {
        $entries = [];

        foreach ($this->files->pairs($from, $to, $only) as $pair) {
            $source = KeyPath::flatten($this->files->read($pair->sourcePath));
            $target = KeyPath::flatten(
                $this->files->exists($pair->targetPath) ? $this->files->read($pair->targetPath) : []
            );

            foreach ($source as $key => $value) {
                if (! is_string($value)) {
                    continue;
                }

                $entries[] = new MissingKey(
                    file: $pair->targetRelative,
                    key: $key,
                    source: $value,
                    current: array_key_exists($key, $target) && is_string($target[$key])
                        ? $target[$key]
                        : null,
                );
            }
        }

        return $entries;
    }

    /**
     * @return array<int, string>
     */
    public function locales(): array
    {
        return $this->files->locales();
    }

    /**
     * @return array<int, LocaleFilePair>
     */
    public function pairs(string $from, string $to, ?string $only = null): array
    {
        return $this->files->pairs($from, $to, $only);
    }
}
