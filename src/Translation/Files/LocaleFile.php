<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Translation\Files;

use DigitalCoreHub\LaravelAiTranslator\Exceptions\InvalidLocalePathException;
use Illuminate\Filesystem\Filesystem;

/**
 * Bir dil dosyasını okuyup yazan sürücülerin ortak atası.
 *
 * Yazma her zaman atomik: önce yanına geçici dosya yazılır, sonra rename edilir.
 * Sebebi basit — çeviri sırasında Ctrl+C yiyen bir komut yarım dosya bırakmasın,
 * o dosya da uygulamayı fatal error'a düşürmesin.
 */
abstract class LocaleFile
{
    public function __construct(protected Filesystem $filesystem) {}

    /**
     * Dosyayı okur ve iç içe geçmiş diziyi döndürür. Dosya yoksa boş dizi.
     *
     * @return array<string, mixed>
     */
    abstract public function read(string $path): array;

    /**
     * @param  array<string, mixed>  $translations
     */
    abstract public function write(string $path, array $translations): void;

    /**
     * Hiç anahtar içermeyen geçerli bir dosya oluşturur.
     */
    abstract public function writeEmpty(string $path): void;

    /**
     * Geçici dosya + rename ile atomik yazma.
     */
    protected function putAtomically(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (! $this->filesystem->isDirectory($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true, true);
        }

        $temporary = $directory.'/.'.basename($path).'.'.bin2hex(random_bytes(6)).'.tmp';

        if ($this->filesystem->put($temporary, $contents) === false) {
            throw new InvalidLocalePathException(sprintf('[%s] yazılamadı.', $path));
        }

        // rename aynı dizin içinde POSIX'te atomiktir; Windows'ta da PHP bunu halleder.
        if (! @rename($temporary, $path)) {
            $this->filesystem->delete($temporary);

            throw new InvalidLocalePathException(sprintf('[%s] yerine taşınamadı.', $path));
        }
    }
}
