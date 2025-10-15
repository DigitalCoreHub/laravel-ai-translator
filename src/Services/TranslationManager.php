<?php

namespace DigitalCoreHub\LaravelAiTranslator\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Illuminate\Filesystem\Filesystem;
use DigitalCoreHub\LaravelAiTranslator\Contracts\TranslationProvider;

/**
 * Coordinate translation of missing locale keys across language files.
 * Eksik dil anahtarlarının çevirisini dil dosyaları arasında koordine eder.
 */
class TranslationManager
{
    protected string $languageRoot;

    public function __construct(
        protected TranslationProvider $provider,
        protected Filesystem $filesystem,
        protected string $basePath,
        protected bool $autoCreateMissingFiles = true
    ) {
        $this->languageRoot = $this->resolveLanguageRoot($basePath);
    }

    /**
     * Translate missing keys from one locale into another.
     * Bir dildeki eksik anahtarları başka bir dile çevirir.
     *
     * @return array{files: array<int, array{name: string, missing: int, translated: int}>, totals: array{missing: int, translated: int}}
     */
    public function translate(string $from, string $to, ?callable $progress = null, bool $dryRun = false, bool $force = false): array
    {
        $files = [];
        $totals = ['missing' => 0, 'translated' => 0];
        $previews = [];

        $sourcePath = $this->langDirectory($from);

        foreach ($this->gatherPhpFiles($sourcePath) as $relativeFile) {
            $sourceFile = $sourcePath.'/'.$relativeFile;
            $targetFile = $this->langDirectory($to).'/'.$relativeFile;

            [$missingCount, $translatedCount, $preview] = $this->translatePhpFile(
                $from,
                $to,
                $sourceFile,
                $targetFile,
                $progress,
                $dryRun,
                $force
            );

            $files[] = [
                'name' => $relativeFile,
                'missing' => $missingCount,
                'translated' => $translatedCount,
            ];

            $totals['missing'] += $missingCount;
            $totals['translated'] += $translatedCount;

            if ($preview !== []) {
                $previews['lang/'.$to.'/'.$relativeFile] = $preview;
            }
        }

        $sourceJson = $this->langJsonPath($from);

        if ($this->filesystem->exists($sourceJson)) {
            $targetJson = $this->langJsonPath($to);

            [$missingCount, $translatedCount, $preview] = $this->translateJsonFile(
                $from,
                $to,
                $sourceJson,
                $targetJson,
                $progress,
                $dryRun,
                $force
            );

            $files[] = [
                'name' => basename($sourceJson),
                'missing' => $missingCount,
                'translated' => $translatedCount,
            ];

            $totals['missing'] += $missingCount;
            $totals['translated'] += $translatedCount;

            if ($preview !== []) {
                $previews['lang/'.$to.'.json'] = $preview;
            }
        }

        return compact('files', 'totals', 'previews');
    }

    /**
     * Count the total number of missing translation keys between locales.
     * Yereller arasında eksik çeviri anahtarlarının toplamını hesaplar.
     */
    public function countMissing(string $from, string $to, bool $force = false): int
    {
        $total = 0;

        $sourcePath = $this->langDirectory($from);

        foreach ($this->gatherPhpFiles($sourcePath) as $relativeFile) {
            $sourceFile = $sourcePath.'/'.$relativeFile;
            $source = $this->readPhp($sourceFile);

            if ($force) {
                $total += $this->countTotalKeys($source);

                continue;
            }

            $targetFile = $this->langDirectory($to).'/'.$relativeFile;
            $target = $this->readPhp($targetFile);

            $total += $this->countMissingFromArrays($source, $target);
        }

        $sourceJson = $this->langJsonPath($from);

        if ($this->filesystem->exists($sourceJson)) {
            $source = $this->readJson($sourceJson);

            if ($force) {
                $total += $this->countTotalKeys($source);

                return $total;
            }

            $target = $this->readJson($this->langJsonPath($to));

            $total += $this->countMissingFromArrays($source, $target);
        }

        return $total;
    }

    /**
     * Translate the contents of a PHP language file.
     * Bir PHP dil dosyasının içeriğini çevirir.
     */
    protected function translatePhpFile(
        string $from,
        string $to,
        string $sourceFile,
        string $targetFile,
        ?callable $progress = null,
        bool $dryRun = false,
        bool $force = false
    ): array
    {
        $source = $this->readPhp($sourceFile);
        $target = $this->readPhp($targetFile);

        [$missing, $translated, $updated, $preview] = $this->translateArray(
            $from,
            $to,
            $source,
            $target,
            $progress,
            basename($sourceFile),
            $dryRun,
            $force
        );

        if ($updated) {
            $this->writePhp($targetFile, $target);
        } else {
            $this->initializeTargetFile($targetFile, 'php', $dryRun);
        }

        return [$missing, $translated, $preview];
    }

    /**
     * Translate the contents of a JSON language file.
     * Bir JSON dil dosyasının içeriğini çevirir.
     */
    protected function translateJsonFile(
        string $from,
        string $to,
        string $sourceFile,
        string $targetFile,
        ?callable $progress = null,
        bool $dryRun = false,
        bool $force = false
    ): array
    {
        $source = $this->readJson($sourceFile);
        $target = $this->readJson($targetFile);

        [$missing, $translated, $updated, $preview] = $this->translateArray(
            $from,
            $to,
            $source,
            $target,
            $progress,
            basename($sourceFile),
            $dryRun,
            $force
        );

        if ($updated) {
            $this->writeJson($targetFile, $target);
        } else {
            $this->initializeTargetFile($targetFile, 'json', $dryRun);
        }

        return [$missing, $translated, $preview];
    }

    /**
     * Compare flattened arrays and translate missing keys.
     * Düzleştirilmiş dizileri karşılaştırır ve eksik anahtarları çevirir.
     */
    protected function translateArray(
        string $from,
        string $to,
        array $source,
        array &$target,
        ?callable $progress,
        string $fileName,
        bool $dryRun,
        bool $force
    ): array
    {
        $flatSource = $this->flatten($source);
        $flatTarget = $this->flatten($target);

        $missingKeys = array_diff_key($flatSource, $flatTarget);

        $missing = count($missingKeys);
        $translated = 0;
        $updated = false;
        $preview = [];

        $keysToTranslate = $force ? $flatSource : $missingKeys;

        foreach ($keysToTranslate as $key => $text) {
            if ($progress) {
                $progress($fileName, $key, $text);
            }

            [$normalizedText, $placeholders] = $this->maskPlaceholders($text);

            $translatedText = $this->provider->translate($normalizedText, $from, $to);

            $translation = $this->restorePlaceholders($translatedText, $placeholders, $normalizedText);

            $preview[$key] = $translation;

            if (! $dryRun) {
                $flatTarget[$key] = $translation;
                $updated = true;
            }

            $translated++;
        }

        if ($updated) {
            $target = $this->expand($flatTarget);
        }

        return [$missing, $translated, $updated, $preview];
    }

    /**
     * Count missing keys between two translation arrays.
     * İki çeviri dizisi arasındaki eksik anahtarları sayar.
     */
    protected function countMissingFromArrays(array $source, array $target): int
    {
        $flatSource = $this->flatten($source);
        $flatTarget = $this->flatten($target);

        return count(array_diff_key($flatSource, $flatTarget));
    }

    /**
     * Count all translation keys in the given array.
     * Verilen dizideki tüm çeviri anahtarlarını sayar.
     */
    protected function countTotalKeys(array $translations): int
    {
        return count($this->flatten($translations));
    }

    /**
     * Flatten nested translation arrays into dot notation.
     * İç içe geçmiş çeviri dizilerini nokta gösterimine dönüştürür.
     */
    protected function flatten(array $translations, string $prefix = ''): array
    {
        $results = [];

        foreach ($translations as $key => $value) {
            $fullKey = $prefix === '' ? $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $results += $this->flatten($value, $fullKey);

                continue;
            }

            $results[$fullKey] = $value;
        }

        return $results;
    }

    /**
     * Expand dot notation arrays back into nested structures.
     * Nokta gösterimindeki dizileri tekrar iç içe geçmiş yapıya dönüştürür.
     */
    protected function expand(array $translations): array
    {
        $results = [];

        foreach ($translations as $key => $value) {
            Arr::set($results, $key, $value);
        }

        return $results;
    }

    /**
     * Gather PHP translation files from a directory.
     * Bir dizindeki PHP çeviri dosyalarını listeler.
     */
    protected function gatherPhpFiles(string $path): array
    {
        if (! $this->filesystem->exists($path)) {
            return [];
        }

        $finder = Finder::create()
            ->files()
            ->in($path)
            ->name('*.php')
            ->sortByName();

        $relative = [];

        foreach ($finder as $file) {
            $pathname = $file->getRealPath();
            $relative[] = ltrim(Str::after($pathname, rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
        }

        return $relative;
    }

    /**
     * Determine the base language directory to operate on.
     * Kullanılacak temel dil dizinini belirler.
     */
    protected function resolveLanguageRoot(string $basePath): string
    {
        $normalized = rtrim($basePath, '/');
        $preferred = $normalized.'/lang';
        $legacy = $normalized.'/resources/lang';

        if ($this->filesystem->isDirectory($preferred)) {
            return $preferred;
        }

        if ($this->filesystem->isDirectory($legacy)) {
            return $legacy;
        }

        return $preferred;
    }

    /**
     * Build the directory path for a locale.
     * Bir yerel için dizin yolunu oluşturur.
     */
    protected function langDirectory(string $locale): string
    {
        return rtrim($this->languageRoot, '/').'/'.$locale;
    }

    /**
     * Build the JSON file path for a locale.
     * Bir yerel için JSON dosya yolunu oluşturur.
     */
    protected function langJsonPath(string $locale): string
    {
        return rtrim($this->languageRoot, '/').'/'.$locale.'.json';
    }

    /**
     * Read a JSON translation file.
     * Bir JSON çeviri dosyasını okur.
     */
    protected function readJson(string $path): array
    {
        if (! $this->filesystem->exists($path)) {
            return [];
        }

        return json_decode($this->filesystem->get($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Read a PHP translation file.
     * Bir PHP çeviri dosyasını okur.
     */
    protected function readPhp(string $path): array
    {
        if (! $this->filesystem->exists($path)) {
            return [];
        }

        /** @var array $translations */
        $translations = $this->filesystem->getRequire($path);

        return $translations;
    }

    /**
     * Persist translations to a JSON file.
     * Çevirileri bir JSON dosyasına yazar.
     */
    protected function writeJson(string $path, array $translations): void
    {
        $this->ensureDirectory(dirname($path));

        $this->filesystem->put(
            $path,
            json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL
        );
    }

    /**
     * Persist translations to a PHP file.
     * Çevirileri bir PHP dosyasına yazar.
     */
    protected function writePhp(string $path, array $translations): void
    {
        $this->ensureDirectory(dirname($path));

        $export = str_replace(
            ["array (", ")"],
            ["[", "]"],
            var_export($translations, true)
        );

        $content = "<?php\n\nreturn {$export};\n";

        $this->filesystem->put($path, $content);
    }

    /**
     * Initialize a missing target file when auto creation is enabled.
     * Otomatik oluşturma etkin olduğunda eksik hedef dosyayı hazırlar.
     */
    protected function initializeTargetFile(string $path, string $type, bool $dryRun): void
    {
        if ($dryRun || ! $this->autoCreateMissingFiles || $this->filesystem->exists($path)) {
            return;
        }

        $this->ensureDirectory(dirname($path));

        $content = $type === 'php'
            ? "<?php\n\nreturn [];\n"
            : "{}\n";

        $this->filesystem->put($path, $content);
    }

    /**
     * Ensure the target directory exists before writing.
     * Yazmadan önce hedef dizinin var olduğundan emin olur.
     */
    protected function ensureDirectory(string $path): void
    {
        if (! $this->filesystem->isDirectory($path)) {
            $this->filesystem->makeDirectory($path, 0755, true);
        }
    }

    /**
     * Replace HTML tags and placeholders with safe tokens before translation.
     * Çeviri öncesi HTML etiketlerini ve yer tutucuları güvenli belirteçlerle değiştirir.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    protected function maskPlaceholders(string $text): array
    {
        $placeholders = [];
        $normalized = $text;

        foreach ($this->placeholderPatterns() as $pattern => $prefix) {
            $normalized = preg_replace_callback($pattern, function (array $matches) use (&$placeholders, $prefix) {
                $token = sprintf('__AI_%s_%d__', strtoupper($prefix), count($placeholders));
                $placeholders[$token] = $matches[0];

                return $token;
            }, $normalized);
        }

        return [$normalized, $placeholders];
    }

    /**
     * Restore the original placeholders after translation.
     * Çeviri sonrasında orijinal yer tutucuları geri yükler.
     */
    protected function restorePlaceholders(string $translated, array $placeholders, string $normalized): string
    {
        if ($placeholders === []) {
            return $translated;
        }

        if ($this->tokensMissing($translated, array_keys($placeholders))) {
            return strtr($normalized, $placeholders);
        }

        return strtr($translated, $placeholders);
    }

    /**
     * Determine if any placeholder tokens were lost during translation.
     * Çeviri sırasında herhangi bir yer tutucu belirteci kayboldu mu kontrol eder.
     *
     * @param  array<int, string>  $tokens
     */
    protected function tokensMissing(string $text, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (! str_contains($text, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Regex patterns that should be protected during translation.
     * Çeviri sırasında korunması gereken regex desenlerini döndürür.
     *
     * @return array<string, string>
     */
    protected function placeholderPatterns(): array
    {
        return [
            '/<[^>]+>/' => 'html',
            '/:\w+/' => 'placeholder',
            '/{{\s*[^}]+\s*}}/' => 'blade',
            '/%(?:\d+\$)?[a-zA-Z]/' => 'format',
        ];
    }
}
