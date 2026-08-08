<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Translation;

use DigitalCoreHub\LaravelAiTranslator\Data\LocaleFilePair;
use DigitalCoreHub\LaravelAiTranslator\Exceptions\InvalidLocalePathException;
use DigitalCoreHub\LaravelAiTranslator\Translation\Files\JsonLocaleFile;
use DigitalCoreHub\LaravelAiTranslator\Translation\Files\LocaleFile;
use DigitalCoreHub\LaravelAiTranslator\Translation\Files\PhpLocaleFile;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Dil dosyalarının nerede olduğunu bilen ve okuma/yazmayı yapan tek yer.
 *
 * Yol çözümüyle ilgili her şey burada toplandı; başka hiçbir sınıf elle yol birleştirmiyor.
 */
class LocaleFileRepository
{
    /** @var array<int, string> */
    protected array $roots;

    protected PhpLocaleFile $php;

    protected JsonLocaleFile $json;

    /**
     * @param  array<int, string>  $roots  taranacak dil kökleri (mutlak yol)
     */
    public function __construct(
        protected Filesystem $filesystem,
        array $roots,
    ) {
        $this->roots = $this->normalizeRoots($roots);
        $this->php = new PhpLocaleFile($filesystem);
        $this->json = new JsonLocaleFile($filesystem);
    }

    /**
     * @return array<int, string>
     */
    public function roots(): array
    {
        return $this->roots;
    }

    /**
     * Diskte gerçekten var olan dil kökleri.
     *
     * @return array<int, string>
     */
    public function existingRoots(): array
    {
        return array_values(array_filter(
            $this->roots,
            fn (string $root) => $this->filesystem->isDirectory($root)
        ));
    }

    /**
     * Bütün köklerde bulunan dil kodları (hem `lang/tr/` dizinleri hem `lang/tr.json` dosyaları).
     *
     * @return array<int, string>
     */
    public function locales(): array
    {
        $locales = [];

        foreach ($this->existingRoots() as $root) {
            foreach ($this->filesystem->directories($root) as $directory) {
                $locales[] = basename($directory);
            }

            foreach ($this->filesystem->files($root) as $file) {
                if (strtolower($file->getExtension()) === 'json') {
                    $locales[] = $file->getBasename('.'.$file->getExtension());
                }
            }
        }

        $locales = array_values(array_unique(array_filter($locales, static fn ($l) => $l !== '')));

        sort($locales);

        return $locales;
    }

    /**
     * Kaynak dilden hedef dile eşlenmiş dosya çiftlerini toplar.
     *
     * @param  string|null  $only  verilirse sadece bu göreli yola (ör. "en/auth.php") bakılır
     * @return array<int, LocaleFilePair>
     */
    public function pairs(string $from, string $to, ?string $only = null): array
    {
        $pairs = [];

        foreach ($this->existingRoots() as $root) {
            foreach ($this->sourceFilesIn($root, $from) as $relative) {
                if ($only !== null && ! $this->matchesFilter($relative, $only)) {
                    continue;
                }

                $targetRelative = $this->mapToTargetLocale($relative, $from, $to);

                $pairs[] = new LocaleFilePair(
                    root: $root,
                    sourceRelative: $relative,
                    sourcePath: $this->absolute($root, $relative),
                    targetRelative: $targetRelative,
                    targetPath: $this->absolute($root, $targetRelative),
                );
            }
        }

        return $pairs;
    }

    /**
     * @return array<string, mixed>
     */
    public function read(string $path): array
    {
        return $this->driverFor($path)->read($path);
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    public function write(string $path, array $translations): void
    {
        $this->driverFor($path)->write($path, $translations);
    }

    /**
     * Hedef dosya hiç yoksa boş ama geçerli bir dosya bırakır.
     * Böylece `php artisan lang:` komutları ve IDE'ler dosyayı görebilir.
     */
    public function ensureExists(string $path): void
    {
        if ($this->filesystem->exists($path)) {
            return;
        }

        $this->driverFor($path)->writeEmpty($path);
    }

    public function exists(string $path): bool
    {
        return $this->filesystem->exists($path);
    }

    public function driverFor(string $path): LocaleFile
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'json' => $this->json,
            'php' => $this->php,
            default => throw InvalidLocalePathException::unsupportedExtension($path),
        };
    }

    /**
     * Göreli yolu kökle birleştirir ve köke hapseder.
     *
     * "../../.env" gibi bir girdi (ör. HTTP üzerinden gelen bir dosya adı) burada durur.
     */
    public function absolute(string $root, string $relative): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $root = rtrim($root, '/');
        $candidate = $root.'/'.$relative;

        // Yolu diskte olmasa da sadeleştirebilmek için segment segment yürüyoruz.
        if (! str_starts_with($this->canonicalize($candidate), $this->canonicalize($root).'/')) {
            throw InvalidLocalePathException::outsideRoot($relative);
        }

        return $candidate;
    }

    /**
     * Bir kökteki belirli dile ait dosyaları, köke göre göreli olarak listeler.
     *
     * @return array<int, string>
     */
    protected function sourceFilesIn(string $root, string $locale): array
    {
        $files = [];
        $directory = rtrim($root, '/').'/'.$locale;

        if ($this->filesystem->isDirectory($directory)) {
            $finder = Finder::create()
                ->files()
                ->in($directory)
                ->name('*.php')
                ->sortByName();

            foreach ($finder as $file) {
                $files[] = $locale.'/'.str_replace('\\', '/', $file->getRelativePathname());
            }
        }

        $jsonFile = rtrim($root, '/').'/'.$locale.'.json';

        if ($this->filesystem->exists($jsonFile)) {
            $files[] = $locale.'.json';
        }

        return $files;
    }

    /**
     * "en/auth.php" → "tr/auth.php", "en.json" → "tr.json".
     *
     * Sadece ilk segmenti değiştiriyoruz; "en/admin/en.php" gibi bir yolda içerideki
     * "en" dokunulmadan kalsın diye.
     */
    protected function mapToTargetLocale(string $relative, string $from, string $to): string
    {
        if ($relative === $from.'.json') {
            return $to.'.json';
        }

        if (str_starts_with($relative, $from.'/')) {
            return $to.'/'.substr($relative, strlen($from) + 1);
        }

        return $relative;
    }

    /**
     * --only filtresi: tam yol, dosya adı ya da dizin öneki olarak eşleşebilir.
     */
    protected function matchesFilter(string $relative, string $filter): bool
    {
        $filter = trim(str_replace('\\', '/', $filter), '/');

        return $relative === $filter
            || str_starts_with($relative, $filter.'/')
            || basename($relative) === $filter
            || basename($relative, '.php') === $filter
            || fnmatch($filter, $relative);
    }

    /**
     * @param  array<int, string>  $roots
     * @return array<int, string>
     */
    protected function normalizeRoots(array $roots): array
    {
        $normalized = [];

        foreach ($roots as $root) {
            $root = rtrim(str_replace('\\', '/', $root), '/');

            if ($root !== '' && ! in_array($root, $normalized, true)) {
                $normalized[] = $root;
            }
        }

        return $normalized;
    }

    /**
     * "." ve ".." segmentlerini çözer. realpath'e güvenmiyoruz çünkü hedef dosya
     * henüz diskte olmayabilir (ilk kez oluşturulacak çeviri dosyaları).
     */
    protected function canonicalize(string $path): string
    {
        $isAbsolute = str_starts_with($path, '/');
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return ($isAbsolute ? '/' : '').implode('/', $segments);
    }
}
