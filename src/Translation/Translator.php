<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Translation;

use DigitalCoreHub\LaravelAiTranslator\Cache\TranslationCache;
use DigitalCoreHub\LaravelAiTranslator\Data\BatchResult;
use DigitalCoreHub\LaravelAiTranslator\Data\FailedKey;
use DigitalCoreHub\LaravelAiTranslator\Data\FileOutcome;
use DigitalCoreHub\LaravelAiTranslator\Data\LocaleFilePair;
use DigitalCoreHub\LaravelAiTranslator\Data\MissingKey;
use DigitalCoreHub\LaravelAiTranslator\Data\TranslatedKey;
use DigitalCoreHub\LaravelAiTranslator\Data\TranslationRun;
use DigitalCoreHub\LaravelAiTranslator\Events\FileTranslated;
use DigitalCoreHub\LaravelAiTranslator\Events\KeyTranslationFailed;
use DigitalCoreHub\LaravelAiTranslator\Events\TranslationRunCompleted;
use DigitalCoreHub\LaravelAiTranslator\Events\TranslationRunStarted;
use DigitalCoreHub\LaravelAiTranslator\Exceptions\AiTranslatorException;
use DigitalCoreHub\LaravelAiTranslator\Providers\ProviderChain;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Paketin kalbi. Eksikleri bulur, sağlayıcıya gönderir, sonucu dosyaya yazar.
 *
 * Akıcı (fluent) kullanımı Facade üzerinden:
 *
 *     AiTranslator::from('en')->to('tr')->force()->translate();
 */
class Translator
{
    protected string $from = 'en';

    protected string $to = 'tr';

    protected ?string $provider = null;

    protected bool $dryRun = false;

    protected bool $force = false;

    protected ?string $only = null;

    /** @var (callable(MissingKey): void)|null */
    protected $onProgress = null;

    public function __construct(
        protected LocaleScanner $scanner,
        protected LocaleFileRepository $files,
        protected ProviderChain $chain,
        protected TranslationCache $cache,
        protected PlaceholderGuard $guard,
        protected Dispatcher $events,
    ) {}

    /**
     * Ayarları taşıyan yeni bir kopya döndürür; paylaşılan singleton'ı kirletmemek için.
     */
    protected function clone(): static
    {
        return clone $this;
    }

    public function from(string $locale): static
    {
        $clone = $this->clone();
        $clone->from = $locale;

        return $clone;
    }

    public function to(string $locale): static
    {
        $clone = $this->clone();
        $clone->to = $locale;

        return $clone;
    }

    public function provider(?string $name): static
    {
        $clone = $this->clone();
        $clone->provider = $name;

        return $clone;
    }

    public function dryRun(bool $dryRun = true): static
    {
        $clone = $this->clone();
        $clone->dryRun = $dryRun;

        return $clone;
    }

    public function force(bool $force = true): static
    {
        $clone = $this->clone();
        $clone->force = $force;

        return $clone;
    }

    public function only(?string $filter): static
    {
        $clone = $this->clone();
        $clone->only = $filter;

        return $clone;
    }

    /**
     * @param  (callable(MissingKey): void)|null  $callback
     */
    public function onProgress(?callable $callback): static
    {
        $clone = $this->clone();
        $clone->onProgress = $callback;

        return $clone;
    }

    /**
     * Eksik anahtarları listeler (hiçbir şey çevirmez, para harcamaz).
     *
     * @return array<int, MissingKey>
     */
    public function missing(): array
    {
        return $this->scanner->missing($this->from, $this->to, $this->force, $this->only);
    }

    /**
     * Kaynak dildeki bütün anahtarları durumlarıyla listeler.
     *
     * @return array<int, MissingKey>
     */
    public function entries(): array
    {
        return $this->scanner->entries($this->from, $this->to, $this->only);
    }

    /**
     * @return array<int, string>
     */
    public function locales(): array
    {
        return $this->scanner->locales();
    }

    /**
     * Bu dil çifti için eşlenmiş dosya çiftleri.
     *
     * @return array<int, LocaleFilePair>
     */
    public function pairs(): array
    {
        return $this->scanner->pairs($this->from, $this->to, $this->only);
    }

    /**
     * Bu dil çifti için işlenecek kaynak dosyaların dil köküne göre yolları
     * (ör. "en/auth.php", "en.json"). Kuyruğa iş dağıtırken kullanılıyor.
     *
     * @return array<int, string>
     */
    public function sourceFiles(): array
    {
        return array_values(array_unique(array_map(
            static fn (LocaleFilePair $pair) => $pair->sourceRelative,
            $this->scanner->pairs($this->from, $this->to, $this->only)
        )));
    }

    /**
     * Çeviriyi çalıştırır.
     */
    public function translate(): TranslationRun
    {
        $startedAt = microtime(true);
        $pairs = $this->scanner->pairs($this->from, $this->to, $this->only);

        $plan = [];
        $totalMissing = 0;

        foreach ($pairs as $pair) {
            $keys = $this->scanner->missingForPair($pair, $this->force);
            $plan[] = [$pair, $keys];
            $totalMissing += count($keys);
        }

        $this->events->dispatch(new TranslationRunStarted($this->from, $this->to, $totalMissing, $this->dryRun));

        $outcomes = [];

        foreach ($plan as [$pair, $keys]) {
            $outcomes[] = $outcome = $this->translatePair($pair, $keys);

            $this->events->dispatch(new FileTranslated($this->from, $this->to, $outcome));
        }

        $run = new TranslationRun(
            from: $this->from,
            to: $this->to,
            files: $outcomes,
            dryRun: $this->dryRun,
            force: $this->force,
            durationMs: (microtime(true) - $startedAt) * 1000,
        );

        $this->events->dispatch(new TranslationRunCompleted($run));

        return $run;
    }

    /**
     * Tek bir metni çevirir (facade ve tekil kullanım için).
     */
    public function text(string $text): TranslatedKey
    {
        $key = new MissingKey(file: '', key: 'text', source: $text);

        $outcome = $this->translateKeys([$key]);

        if ($outcome['translated'] !== []) {
            return $outcome['translated'][0];
        }

        throw new AiTranslatorException(
            $outcome['failed'] === [] ? 'Metin çevrilemedi.' : $outcome['failed'][0]->reason
        );
    }

    /**
     * @param  array<int, MissingKey>  $keys
     */
    protected function translatePair(LocaleFilePair $pair, array $keys): FileOutcome
    {
        $startedAt = microtime(true);

        // Hedef dosya hiç yoksa boşunu bırakıyoruz ki "tr dizini boş" durumu görünür olsun.
        if (! $this->dryRun) {
            $this->files->ensureExists($pair->targetPath);
        }

        if ($keys === []) {
            return new FileOutcome(
                file: $pair->targetRelative,
                missing: 0,
                durationMs: (microtime(true) - $startedAt) * 1000,
            );
        }

        $result = $this->translateKeys($keys);

        $written = false;

        if (! $this->dryRun && $result['translated'] !== []) {
            $written = $this->persist($pair, $result['translated']);
        }

        return new FileOutcome(
            file: $pair->targetRelative,
            missing: count($keys),
            translated: $result['translated'],
            failed: $result['failed'],
            written: $written,
            durationMs: (microtime(true) - $startedAt) * 1000,
        );
    }

    /**
     * Anahtar listesini önbellek + sağlayıcı zincirinden geçirir.
     *
     * @param  array<int, MissingKey>  $keys
     * @return array{translated: array<int, TranslatedKey>, failed: array<int, FailedKey>}
     */
    protected function translateKeys(array $keys): array
    {
        $translated = [];
        $failed = [];

        /** @var array<string, MissingKey> $pending  maskeli metin karması => anahtar */
        $pending = [];
        /** @var array<string, string> $masked */
        $masked = [];
        /** @var array<string, array<string, string>> $placeholders */
        $placeholders = [];

        foreach ($keys as $index => $key) {
            $this->reportProgress($key);

            [$maskedText, $tokens] = $this->guard->mask($key->source);

            // Aynı metin dosyada birden çok kez geçebilir; benzersiz bir tanıtıcı veriyoruz
            // ki sağlayıcıdan dönen harita karışmasın.
            $id = 'k'.$index;

            $pending[$id] = $key;
            $masked[$id] = $maskedText;
            $placeholders[$id] = $tokens;
        }

        // 1) Önce önbellek. Zincirdeki her sağlayıcı için ayrı ayrı bakıyoruz,
        //    çünkü çeviri kalitesi sağlayıcıya göre değişir.
        foreach ($this->chain->order($this->provider) as $providerName) {
            if ($masked === []) {
                break;
            }

            foreach ($this->cache->getMany($providerName, $this->from, $this->to, $masked) as $id => $cached) {
                $restored = $this->guard->restore($cached, $placeholders[$id]);

                if ($restored === null) {
                    continue;
                }

                $key = $pending[$id];

                $translated[] = new TranslatedKey(
                    file: $key->file,
                    key: $key->key,
                    source: $key->source,
                    translation: $restored,
                    provider: $providerName,
                    fromCache: true,
                );

                unset($masked[$id], $pending[$id]);
            }
        }

        // 2) Kalanlar sağlayıcıya gider.
        $result = $masked === []
            ? new BatchResult
            : $this->chain->translate($masked, $this->from, $this->to, $this->provider);

        foreach ($masked as $id => $maskedText) {
            $key = $pending[$id];
            $raw = $result->translations[$id] ?? null;

            if ($raw === null) {
                $failed[] = $this->fail($key, $result->reason());

                continue;
            }

            $providerName = $result->providerFor($id);

            $restored = $this->guard->restore($raw, $placeholders[$id]);

            if ($restored === null) {
                // Placeholder kaybolmuş. v0.x burada sessizce kaynak metni yazıyordu;
                // biz yazmıyoruz, çünkü ":name" kaybolmuş bir çeviri çalışan koddan beter.
                $failed[] = $this->fail(
                    $key,
                    sprintf('[%s] çeviride yer tutucular kayboldu, sonuç yazılmadı.', $providerName)
                );

                continue;
            }

            $this->cache->put($providerName, $this->from, $this->to, $maskedText, $raw);

            $translated[] = new TranslatedKey(
                file: $key->file,
                key: $key->key,
                source: $key->source,
                translation: $restored,
                provider: $providerName,
            );
        }

        return ['translated' => $translated, 'failed' => $failed];
    }

    protected function fail(MissingKey $key, string $reason): FailedKey
    {
        $failure = new FailedKey(
            file: $key->file,
            key: $key->key,
            source: $key->source,
            reason: $reason,
        );

        $this->events->dispatch(new KeyTranslationFailed($this->from, $this->to, $failure));

        return $failure;
    }

    /**
     * Çevirileri hedef dosyaya işler.
     *
     * Dosyayı yazmadan hemen önce tekrar okuyoruz: paralel çalışan başka bir işin
     * (kuyruk işçisi, ikinci bir ai:translate) araya yazdıklarını ezmeyelim diye.
     *
     * @param  array<int, TranslatedKey>  $translated
     */
    protected function persist(LocaleFilePair $pair, array $translated): bool
    {
        $current = KeyPath::flatten(
            $this->files->exists($pair->targetPath) ? $this->files->read($pair->targetPath) : []
        );

        foreach ($translated as $key) {
            $current[$key->key] = $key->translation;
        }

        // Kaynak dosyadaki sıraya göre diziyoruz; diff'ler okunabilir olsun,
        // her koşuda anahtarlar yer değiştirmesin.
        $ordered = $this->orderBySource($pair, $current);

        $this->files->write($pair->targetPath, KeyPath::expand($ordered));

        return true;
    }

    /**
     * @param  array<string, mixed>  $flat
     * @return array<string, mixed>
     */
    protected function orderBySource(LocaleFilePair $pair, array $flat): array
    {
        $source = KeyPath::flatten($this->files->read($pair->sourcePath));

        $ordered = [];

        foreach (array_keys($source) as $key) {
            if (array_key_exists($key, $flat)) {
                $ordered[$key] = $flat[$key];
            }
        }

        // Kaynakta olmayan ama hedefte duran anahtarlar (elle eklenmiş olabilir) korunur.
        foreach ($flat as $key => $value) {
            if (! array_key_exists($key, $ordered)) {
                $ordered[$key] = $value;
            }
        }

        return $ordered;
    }

    protected function reportProgress(MissingKey $key): void
    {
        if ($this->onProgress !== null) {
            ($this->onProgress)($key);
        }
    }
}
