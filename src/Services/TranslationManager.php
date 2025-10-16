<?php

namespace DigitalCoreHub\LaravelAiTranslator\Services;

use DigitalCoreHub\LaravelAiTranslator\Contracts\TranslationProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Coordinate translation of missing locale keys across language files.
 * Eksik dil anahtarlarının çevirisini dil dosyaları arasında koordine eder.
 */
class TranslationManager
{
    /**
     * Resolved language directories that should be inspected.
     * İşlem yapılacak çözülmüş dil dizinleri.
     *
     * @var array<int, string>
     */
    protected array $paths;

    /**
     * Default fallback order for providers.
     * Sağlayıcılar için varsayılan geri dönüş sırası.
     */
    protected array $fallbackOrder = ['openai', 'deepl', 'google'];

    /**
     * @param  array<string, TranslationProvider>  $providers
     */
    public function __construct(
        protected array $providers,
        protected TranslationCache $cache,
        protected Filesystem $filesystem,
        protected string $basePath,
        array $paths = [],
        protected string $configuredProvider = 'openai',
        protected bool $autoCreateMissingFiles = true
    ) {
        $this->paths = $this->resolvePaths($paths === []
            ? [$this->resolveLanguageRoot($basePath)]
            : $paths
        );
    }

    /**
     * Translate missing keys from one locale into another.
     * Bir dildeki eksik anahtarları başka bir dile çevirir.
     *
     * @return array{
     *     files: array<int, array{name: string, missing: int, translated: int}>,
     *     totals: array{missing: int, translated: int},
     *     previews: array<string, array<string, string>>,
     *     reviews: array<string, array<string, array{source: string, translation: string, provider: string, cache: bool}>>,
     *     report: array<int, array<string, mixed>>
     * }
     */
    public function translate(
        string $from,
        string $to,
        ?callable $progress = null,
        bool $dryRun = false,
        bool $force = false,
        array $options = []
    ): array {
        $providerOverride = $options['provider'] ?? null;
        $reviewMode = (bool) ($options['review'] ?? false);

        if ($reviewMode) {
            $dryRun = true;
        }

        $files = [];
        $totals = ['missing' => 0, 'translated' => 0];
        $previews = [];
        $reviews = [];
        $report = [];
        $providerOrder = $this->resolveProviderOrder($providerOverride);

        foreach ($this->paths as $root) {
            $sourceDirectory = rtrim($root, '/').'/'.$from;
            $targetDirectory = rtrim($root, '/').'/'.$to;

            foreach ($this->gatherPhpFiles($sourceDirectory) as $relativeFile) {
                $sourceFile = $sourceDirectory.'/'.$relativeFile;
                $targetFile = $targetDirectory.'/'.$relativeFile;

                $source = $this->readPhp($sourceFile);
                $target = $this->filesystem->exists($targetFile)
                    ? $this->readPhp($targetFile)
                    : [];

                [$missing, $translatedCount, $updated, $preview, $fileReviews, $stats] = $this->translateArray(
                    from: $from,
                    to: $to,
                    source: $source,
                    target: $target,
                    progress: $progress,
                    fileName: basename($sourceFile),
                    dryRun: $dryRun,
                    force: $force,
                    providerOrder: $providerOrder
                );

                if ($updated && ! $dryRun) {
                    $this->writePhp($targetFile, $target);
                } else {
                    $this->initializeTargetFile($targetFile, 'php', $dryRun);
                }

                $relativePath = $this->relativePath($targetFile);

                $files[] = [
                    'name' => $relativePath,
                    'missing' => $missing,
                    'translated' => $translatedCount,
                ];

                $totals['missing'] += $missing;
                $totals['translated'] += $translatedCount;

                if ($preview !== []) {
                    $previews[$relativePath] = $preview;
                }

                if ($fileReviews !== [] && $reviewMode) {
                    $reviews[$relativePath] = $fileReviews;
                }

                $report[] = $this->buildReportEntry(
                    path: $relativePath,
                    stats: $stats,
                    translated: $translatedCount,
                    missing: $missing
                );
            }

            $sourceJson = $sourceDirectory.'.json';

            if ($this->filesystem->exists($sourceJson)) {
                $targetJson = $targetDirectory.'.json';

                $source = $this->readJson($sourceJson);
                $target = $this->filesystem->exists($targetJson)
                    ? $this->readJson($targetJson)
                    : [];

                [$missing, $translatedCount, $updated, $preview, $fileReviews, $stats] = $this->translateArray(
                    from: $from,
                    to: $to,
                    source: $source,
                    target: $target,
                    progress: $progress,
                    fileName: basename($sourceJson),
                    dryRun: $dryRun,
                    force: $force,
                    providerOrder: $providerOrder
                );

                if ($updated && ! $dryRun) {
                    $this->writeJson($targetJson, $target);
                } else {
                    $this->initializeTargetFile($targetJson, 'json', $dryRun);
                }

                $relativePath = $this->relativePath($targetJson);

                $files[] = [
                    'name' => $relativePath,
                    'missing' => $missing,
                    'translated' => $translatedCount,
                ];

                $totals['missing'] += $missing;
                $totals['translated'] += $translatedCount;

                if ($preview !== []) {
                    $previews[$relativePath] = $preview;
                }

                if ($fileReviews !== [] && $reviewMode) {
                    $reviews[$relativePath] = $fileReviews;
                }

                $report[] = $this->buildReportEntry(
                    path: $relativePath,
                    stats: $stats,
                    translated: $translatedCount,
                    missing: $missing
                );
            }
        }

        return compact('files', 'totals', 'previews', 'reviews', 'report');
    }

    /**
     * Inspect language files and return missing translation entries.
     * Dil dosyalarını inceleyerek eksik çeviri kayıtlarını döndürür.
     *
     * @return array{files: array<int, array{name: string, entries: array<int, array{key: string, source: string, target: string|null, status: string}>, missing: int, total: int, type: string}>, totals: array{files: int, missing: int}}
     */
    public function inspectMissing(string $from, string $to): array
    {
        $files = [];
        $totals = ['files' => 0, 'missing' => 0];

        foreach ($this->paths as $root) {
            $sourceDirectory = rtrim($root, '/').'/'.$from;
            $targetDirectory = rtrim($root, '/').'/'.$to;

            foreach ($this->gatherPhpFiles($sourceDirectory) as $relativeFile) {
                $sourceFile = $sourceDirectory.'/'.$relativeFile;
                $targetFile = $targetDirectory.'/'.$relativeFile;

                $payload = $this->inspectFile($sourceFile, $targetFile, 'php');

                if ($payload['entries'] === []) {
                    continue;
                }

                $files[] = $payload;
                $totals['files']++;
                $totals['missing'] += $payload['missing'];
            }

            $sourceJson = $sourceDirectory.'.json';

            if ($this->filesystem->exists($sourceJson)) {
                $targetJson = $targetDirectory.'.json';
                $payload = $this->inspectFile($sourceJson, $targetJson, 'json');

                if ($payload['entries'] !== []) {
                    $files[] = $payload;
                    $totals['files']++;
                    $totals['missing'] += $payload['missing'];
                }
            }
        }

        usort($files, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return compact('files', 'totals');
    }

    /**
     * Gather available locales by inspecting registered paths.
     * Kayıtlı dizinleri inceleyerek mevcut yerelleri toplar.
     *
     * @return array<int, string>
     */
    public function availableLocales(): array
    {
        $locales = [];

        foreach ($this->paths as $root) {
            if (! $this->filesystem->exists($root)) {
                continue;
            }

            foreach ($this->filesystem->directories($root) as $directory) {
                $locales[] = basename($directory);
            }

            foreach ($this->filesystem->files($root) as $file) {
                if ($file->getExtension() === 'json') {
                    $locales[] = $file->getBasename('.json');
                }
            }
        }

        $locales = array_values(array_unique($locales));
        sort($locales);

        return $locales;
    }

    /**
     * Translate a specific file and persist changes immediately.
     * Belirli bir dosyayı çevirir ve değişiklikleri kalıcı olarak yazar.
     *
     * @return array{path: string, missing: int, translated: int, preview: array<string, string>, stats: array<string, mixed>}
     */
    public function translateFile(string $from, string $to, string $relativePath, array $options = []): array
    {
        $providerOverride = $options['provider'] ?? null;
        $force = (bool) ($options['force'] ?? false);
        $providerOrder = $this->resolveProviderOrder($providerOverride);

        [$sourceFile, $targetFile, $type] = $this->resolveSourceAndTarget($from, $to, $relativePath);

        if ($type === 'json') {
            $source = $this->filesystem->exists($sourceFile) ? $this->readJson($sourceFile) : [];
            $target = $this->filesystem->exists($targetFile) ? $this->readJson($targetFile) : [];
        } else {
            $source = $this->filesystem->exists($sourceFile) ? $this->readPhp($sourceFile) : [];
            $target = $this->filesystem->exists($targetFile) ? $this->readPhp($targetFile) : [];
        }

        [$missing, $translated, $updated, $preview, $reviews, $stats] = $this->translateArray(
            from: $from,
            to: $to,
            source: $source,
            target: $target,
            progress: $options['progress'] ?? null,
            fileName: basename($targetFile),
            dryRun: false,
            force: $force,
            providerOrder: $providerOrder
        );

        if ($type === 'json') {
            if ($updated) {
                $this->writeJson($targetFile, $target);
            } else {
                $this->initializeTargetFile($targetFile, 'json', false);
            }
        } else {
            if ($updated) {
                $this->writePhp($targetFile, $target);
            } else {
                $this->initializeTargetFile($targetFile, 'php', false);
            }
        }

        $relative = $this->relativePath($targetFile);

        return [
            'path' => $relative,
            'missing' => $missing,
            'translated' => $translated,
            'preview' => $preview,
            'reviews' => $reviews,
            'stats' => $stats,
        ];
    }

    /**
     * Translate a single key within a file.
     * Bir dosya içindeki tek anahtarı çevirir.
     *
     * @return array{key: string, translation: string, provider: string, cache_hit: bool, duration: float}
     */
    public function translateEntry(
        string $from,
        string $to,
        string $relativePath,
        string $key,
        array $options = []
    ): array {
        $providerOrder = $this->resolveProviderOrder($options['provider'] ?? null);
        $force = (bool) ($options['force'] ?? false);

        [$sourceFile, $targetFile, $type] = $this->resolveSourceAndTarget($from, $to, $relativePath);

        $source = $type === 'json'
            ? ($this->filesystem->exists($sourceFile) ? $this->readJson($sourceFile) : [])
            : ($this->filesystem->exists($sourceFile) ? $this->readPhp($sourceFile) : []);

        $target = $type === 'json'
            ? ($this->filesystem->exists($targetFile) ? $this->readJson($targetFile) : [])
            : ($this->filesystem->exists($targetFile) ? $this->readPhp($targetFile) : []);

        $flatSource = $this->flatten($source);
        $flatTarget = $this->flatten($target);

        if (! array_key_exists($key, $flatSource)) {
            throw new RuntimeException("Source key [{$key}] not found for {$relativePath}.");
        }

        if (! $force && array_key_exists($key, $flatTarget) && $flatTarget[$key] !== null && $flatTarget[$key] !== '') {
            return [
                'key' => $key,
                'translation' => $flatTarget[$key],
                'provider' => 'existing',
                'cache_hit' => true,
                'duration' => 0.0,
            ];
        }

        $result = $this->performTranslation($flatSource[$key], $from, $to, $providerOrder);

        $flatTarget[$key] = $result['translation'];
        $expanded = $this->expand($flatTarget);

        if ($type === 'json') {
            $this->writeJson($targetFile, $expanded);
        } else {
            $this->writePhp($targetFile, $expanded);
        }

        return ['key' => $key] + $result;
    }

    /**
     * Persist a manual translation value for the given locale.
     * Belirtilen yerel için manuel çeviri değerini kaydeder.
     */
    public function updateTranslationEntry(string $locale, string $relativePath, string $key, string $value): void
    {
        [$sourceFile, $targetFile, $type] = $this->resolveSourceAndTarget($locale, $locale, $relativePath);

        $target = $type === 'json'
            ? ($this->filesystem->exists($targetFile) ? $this->readJson($targetFile) : [])
            : ($this->filesystem->exists($targetFile) ? $this->readPhp($targetFile) : []);

        $flat = $this->flatten($target);
        $flat[$key] = $value;
        $expanded = $this->expand($flat);

        if ($type === 'json') {
            $this->writeJson($targetFile, $expanded);
        } else {
            $this->writePhp($targetFile, $expanded);
        }
    }

    /**
     * Retrieve all translation entries for the selected file pair.
     * Seçilen dosya çifti için tüm çeviri kayıtlarını getirir.
     *
     * @return array<int, array{key: string, source: string, target: string|null}>
     */
    public function getFileEntries(string $from, string $to, string $relativePath): array
    {
        [$sourceFile, $targetFile, $type] = $this->resolveSourceAndTarget($from, $to, $relativePath);

        $source = $type === 'json'
            ? ($this->filesystem->exists($sourceFile) ? $this->readJson($sourceFile) : [])
            : ($this->filesystem->exists($sourceFile) ? $this->readPhp($sourceFile) : []);

        $target = $type === 'json'
            ? ($this->filesystem->exists($targetFile) ? $this->readJson($targetFile) : [])
            : ($this->filesystem->exists($targetFile) ? $this->readPhp($targetFile) : []);

        $flatSource = $this->flatten($source);
        $flatTarget = $this->flatten($target);

        $entries = [];

        foreach ($flatSource as $key => $sourceValue) {
            $entries[] = [
                'key' => $key,
                'source' => $sourceValue,
                'target' => $flatTarget[$key] ?? null,
            ];
        }

        return $entries;
    }

    /**
     * Translate an arbitrary text snippet via the configured providers.
     * Yapılandırılmış sağlayıcılar üzerinden serbest bir metni çevirir.
     *
     * @return array{translation: string, provider: string, cache_hit: bool, duration: float}
     */
    public function translateText(string $text, string $from, string $to, ?string $provider = null): array
    {
        $order = $this->resolveProviderOrder($provider);

        return $this->performTranslation($text, $from, $to, $order);
    }

    /**
     * Test connectivity to a specific provider.
     * Belirli bir sağlayıcıya bağlantıyı test eder.
     *
     * @return array{ok: bool, message: string, provider?: string}
     */
    public function testProvider(string $provider): array
    {
        if (! isset($this->providers[$provider])) {
            return ['ok' => false, 'message' => "Provider [{$provider}] is not registered."];
        }

        try {
            $result = $this->performTranslation('ping', 'en', 'en', [$provider]);

            return [
                'ok' => true,
                'message' => 'Connection OK',
                'provider' => $result['provider'],
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Inspect a language file and prepare structured entry data.
     * Bir dil dosyasını inceleyip yapılandırılmış veri döner.
     *
     * @return array{name: string, entries: array<int, array{key: string, source: string, target: string|null, status: string}>, missing: int, total: int, type: string}
     */
    protected function inspectFile(string $sourceFile, string $targetFile, string $type): array
    {
        $source = $this->filesystem->exists($sourceFile)
            ? ($type === 'json' ? $this->readJson($sourceFile) : $this->readPhp($sourceFile))
            : [];

        $target = $this->filesystem->exists($targetFile)
            ? ($type === 'json' ? $this->readJson($targetFile) : $this->readPhp($targetFile))
            : [];

        $flatSource = $this->flatten($source);
        $flatTarget = $this->flatten($target);

        $entries = [];
        $missing = 0;

        foreach ($flatSource as $key => $value) {
            $targetValue = $flatTarget[$key] ?? null;
            $status = $targetValue === null
                ? 'missing'
                : ($targetValue === '' ? 'empty' : 'translated');

            if ($status === 'missing' || $status === 'empty') {
                $missing++;
            }

            $entries[] = [
                'key' => $key,
                'source' => $value,
                'target' => $targetValue,
                'status' => $status,
            ];
        }

        return [
            'name' => $this->relativePath($targetFile),
            'entries' => $entries,
            'missing' => $missing,
            'total' => count($entries),
            'type' => $type,
        ];
    }

    /**
     * Resolve the absolute source/target file paths for the UI helpers.
     * UI yardımcıları için mutlak kaynak/hedef dosya yollarını çözümler.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    protected function resolveSourceAndTarget(string $from, string $to, string $relativePath): array
    {
        $relativePath = ltrim($relativePath, '/');
        $targetFile = rtrim($this->basePath, '/').'/'.$relativePath;
        $type = Str::endsWith($targetFile, '.json') ? 'json' : 'php';

        if ($type === 'json') {
            $needle = '/'.$to.'.json';
            $replacement = '/'.$from.'.json';
            $sourceFile = Str::replaceLast($needle, $replacement, $targetFile);

            if ($sourceFile === $targetFile && $from !== $to) {
                $sourceFile = Str::replaceLast('/'.$to.'.', '/'.$from.'.', $targetFile);
            }
        } else {
            $sourceFile = Str::replaceFirst('/'.$to.'/', '/'.$from.'/', $targetFile);
        }

        if ($from === $to) {
            $sourceFile = $targetFile;
        }

        return [$sourceFile, $targetFile, $type];
    }

    /**
     * Clear the underlying translation cache.
     * Çeviri önbelleğini temizler.
     */
    public function clearCache(): void
    {
        $this->cache->clear();
    }

    /**
     * Count the total number of missing translation keys between locales.
     * Yereller arasında eksik çeviri anahtarlarının toplamını hesaplar.
     */
    public function countMissing(string $from, string $to, bool $force = false): int
    {
        $total = 0;

        foreach ($this->paths as $root) {
            $sourceDirectory = rtrim($root, '/').'/'.$from;
            $targetDirectory = rtrim($root, '/').'/'.$to;

            foreach ($this->gatherPhpFiles($sourceDirectory) as $relativeFile) {
                $sourceFile = $sourceDirectory.'/'.$relativeFile;
                $targetFile = $targetDirectory.'/'.$relativeFile;

                $source = $this->readPhp($sourceFile);
                $target = $this->filesystem->exists($targetFile)
                    ? $this->readPhp($targetFile)
                    : [];

                $total += $force
                    ? $this->countTotalKeys($source)
                    : $this->countMissingFromArrays($source, $target);
            }

            $sourceJson = $sourceDirectory.'.json';

            if (! $this->filesystem->exists($sourceJson)) {
                continue;
            }

            $targetJson = $targetDirectory.'.json';

            $source = $this->readJson($sourceJson);
            $target = $this->filesystem->exists($targetJson)
                ? $this->readJson($targetJson)
                : [];

            $total += $force
                ? $this->countTotalKeys($source)
                : $this->countMissingFromArrays($source, $target);
        }

        return $total;
    }

    /**
     * Compare flattened arrays and translate missing keys.
     * Düzleştirilmiş dizileri karşılaştırır ve eksik anahtarları çevirir.
     *
     * @return array{int, int, bool, array<string, string>, array<string, array{source: string, translation: string, provider: string, cache: bool}>, array<string, mixed>}
     */
    protected function translateArray(
        string $from,
        string $to,
        array $source,
        array &$target,
        ?callable $progress,
        string $fileName,
        bool $dryRun,
        bool $force,
        array $providerOrder
    ): array {
        $flatSource = $this->flatten($source);
        $flatTarget = $this->flatten($target);

        $missingKeys = array_diff_key($flatSource, $flatTarget);

        $missing = count($missingKeys);
        $translated = 0;
        $updated = false;
        $preview = [];
        $reviews = [];
        $stats = [
            'providers' => [],
            'cache_hits' => 0,
            'cache_misses' => 0,
            'duration' => 0.0,
        ];

        $keysToTranslate = $force ? $flatSource : $missingKeys;

        foreach ($keysToTranslate as $key => $text) {
            if ($progress) {
                $progress($fileName, $key, $text);
            }

            $result = $this->performTranslation($text, $from, $to, $providerOrder);

            $preview[$key] = $result['translation'];

            $reviews[$key] = [
                'source' => $text,
                'translation' => $result['translation'],
                'provider' => $result['provider'],
                'cache' => $result['cache_hit'],
            ];

            $stats['providers'][$result['provider']] = ($stats['providers'][$result['provider']] ?? 0) + 1;
            $stats['duration'] += $result['duration'];

            if ($result['cache_hit']) {
                $stats['cache_hits']++;
            } else {
                $stats['cache_misses']++;
            }

            if (! $dryRun) {
                $flatTarget[$key] = $result['translation'];
                $updated = true;
            }

            $translated++;
        }

        if ($updated) {
            $target = $this->expand($flatTarget);
        }

        return [$missing, $translated, $updated, $preview, $reviews, $stats];
    }

    /**
     * Execute a translation against the provider fallback chain.
     * Sağlayıcı geri dönüş zincirine karşı bir çeviri gerçekleştirir.
     *
     * @param  array<int, string>  $providerOrder
     * @return array{translation: string, provider: string, cache_hit: bool, duration: float}
     */
    protected function performTranslation(string $text, string $from, string $to, array $providerOrder): array
    {
        [$normalized, $placeholders] = $this->maskPlaceholders($text);

        $errors = [];

        foreach ($providerOrder as $name) {
            if (! isset($this->providers[$name])) {
                continue;
            }

            $cached = $this->cache->get($name, $from, $to, $normalized);

            if (is_array($cached) && isset($cached['translation'])) {
                return [
                    'translation' => $cached['translation'],
                    'provider' => $cached['provider'] ?? $name,
                    'cache_hit' => true,
                    'duration' => 0.0,
                ];
            }

            $provider = $this->providers[$name];
            $start = microtime(true);

            try {
                $translated = $provider->translate($normalized, $from, $to);
            } catch (Throwable $exception) {
                $errors[] = $exception;

                continue;
            }

            $duration = microtime(true) - $start;
            $restored = $this->restorePlaceholders($translated, $placeholders, $normalized);

            $this->cache->put($name, $from, $to, $normalized, [
                'translation' => $restored,
                'provider' => $name,
            ]);

            return [
                'translation' => $restored,
                'provider' => $name,
                'cache_hit' => false,
                'duration' => $duration,
            ];
        }

        $messages = array_map(static fn (Throwable $error) => $error->getMessage(), $errors);
        $message = 'All translation providers failed.';

        if ($messages !== []) {
            $message .= ' '.implode(' | ', $messages);
        }

        throw new RuntimeException($message);
    }

    /**
     * Build a report entry for a translated file.
     * Çevrilen bir dosya için rapor girdisi oluşturur.
     *
     * @param  array{providers: array<string, int>, cache_hits: int, cache_misses: int, duration: float}  $stats
     * @return array<string, mixed>
     */
    protected function buildReportEntry(string $path, array $stats, int $translated, int $missing): array
    {
        $totalCache = $stats['cache_hits'] + $stats['cache_misses'];
        $hitRate = $totalCache > 0 ? $stats['cache_hits'] / $totalCache : 0.0;

        return [
            'file' => $path,
            'translated' => $translated,
            'missing' => $missing,
            'primary_provider' => $this->primaryProvider($stats['providers']),
            'providers' => $stats['providers'],
            'cache' => [
                'hits' => $stats['cache_hits'],
                'misses' => $stats['cache_misses'],
                'hit_rate' => $hitRate,
            ],
            'duration_ms' => round($stats['duration'] * 1000, 2),
        ];
    }

    /**
     * Determine the provider used most often for a file.
     * Bir dosya için en sık kullanılan sağlayıcıyı belirler.
     *
     * @param  array<string, int>  $providers
     */
    protected function primaryProvider(array $providers): string
    {
        if ($providers === []) {
            return 'unknown';
        }

        arsort($providers);

        return (string) array_key_first($providers);
    }

    /**
     * Determine provider order including configured fallbacks.
     * Yapılandırılmış geri dönüşler dahil sağlayıcı sırasını belirler.
     */
    protected function resolveProviderOrder(?string $override): array
    {
        $order = $this->fallbackOrder;

        if ($this->configuredProvider !== null) {
            array_unshift($order, $this->configuredProvider);
        }

        if ($override) {
            array_unshift($order, $override);
        }

        $order = array_values(array_unique(array_filter($order, function ($name) {
            return isset($this->providers[$name]);
        })));

        if ($order === []) {
            $order = array_keys($this->providers);
        }

        return $order;
    }

    /**
     * Resolve directories from configured paths.
     * Yapılandırılan yolları gerçek dizinlere dönüştürür.
     *
     * @return array<int, string>
     */
    protected function resolvePaths(array $paths): array
    {
        $resolved = [];

        foreach ($paths as $path) {
            foreach ($this->expandPath($path) as $candidate) {
                $normalized = rtrim($candidate, '/');

                if (! in_array($normalized, $resolved, true)) {
                    $resolved[] = $normalized;
                }
            }
        }

        return $resolved;
    }

    /**
     * Expand wildcard paths.
     * Joker karakter içeren yolları genişletir.
     *
     * @return array<int, string>
     */
    protected function expandPath(string $path): array
    {
        if (! str_contains($path, '*')) {
            return [$path];
        }

        $glob = glob($path, GLOB_ONLYDIR) ?: [];

        return array_values(array_filter($glob, static fn ($candidate) => $candidate !== false));
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
     * Compare flattened arrays for missing keys.
     * Düzleştirilmiş dizilerdeki eksik anahtarları karşılaştırır.
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
     * Read a JSON translation file.
     * Bir JSON çeviri dosyasını okur.
     */
    protected function readJson(string $path): array
    {
        if (! $this->filesystem->exists($path)) {
            return [];
        }

        $contents = $this->filesystem->get($path);

        return $contents !== ''
            ? json_decode($contents, true, 512, JSON_THROW_ON_ERROR)
            : [];
    }

    /**
     * Read a PHP translation file.
     * Bir PHP çeviri dosyasını okur.
     */
    protected function readPhp(string $path): array
    {
        return $this->filesystem->exists($path)
            ? Arr::undot(require $path)
            : [];
    }

    /**
     * Persist translations to a JSON file.
     * Çevirileri bir JSON dosyasına yazar.
     */
    protected function writeJson(string $path, array $translations): void
    {
        $this->ensureDirectory(dirname($path));

        $this->filesystem->put($path, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL);
    }

    /**
     * Persist translations to a PHP file.
     * Çevirileri bir PHP dosyasına yazar.
     */
    protected function writePhp(string $path, array $translations): void
    {
        $this->ensureDirectory(dirname($path));

        $export = str_replace(
            ['array (', ')'],
            ['[', ']'],
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
     * Replace placeholders with tokens before translation.
     * Çeviri öncesi yer tutucuları belirteçlerle değiştirir.
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
            '/<\/?[A-Za-z][^>]*>/' => 'html',
            '/:[A-Za-z0-9_\-]+/' => 'placeholder',
            '/\{\{\s*.*?\s*\}\}/s' => 'blade',
            '/%(?:\d+\$)?s/' => 'format',
        ];
    }

    /**
     * Convert an absolute path to repository relative form.
     * Mutlak yolu depo göreli biçime dönüştürür.
     */
    protected function relativePath(string $path): string
    {
        return ltrim(Str::after($path, rtrim($this->basePath, '/').'/'), '/');
    }
}
