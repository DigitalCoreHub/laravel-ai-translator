<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Cache;

use Illuminate\Contracts\Cache\Repository;

/**
 * Çeviri sonuçlarının önbelleği.
 *
 * v0.x'te clear() doğrudan $repository->clear() çağırıyordu; yani "çeviri önbelleğini
 * temizle" demek uygulamanın BÜTÜN cache'ini uçurmak demekti. Artık öyle bir şey yok:
 * kendi anahtarlarımızın önünde bir sürüm numarası taşıyoruz, temizlemek o numarayı
 * bir artırmaktan ibaret. Eski kayıtlar erişilemez hale gelir, TTL'leri dolunca düşer,
 * uygulamanın geri kalanına hiç dokunulmaz.
 */
class TranslationCache
{
    protected const PREFIX = 'ai-translator';

    protected const VERSION_KEY = self::PREFIX.':version';

    public function __construct(
        protected Repository $repository,
        protected bool $enabled = true,
        protected ?int $ttl = null,
    ) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function get(string $provider, string $from, string $to, string $text): ?string
    {
        if (! $this->enabled) {
            return null;
        }

        $value = $this->repository->get($this->key($provider, $from, $to, $text));

        return is_string($value) ? $value : null;
    }

    /**
     * Bir grup metni tek turda önbellekten çeker.
     *
     * @param  array<string, string>  $texts  anahtar => metin
     * @return array<string, string> anahtar => çeviri (sadece bulunanlar)
     */
    public function getMany(string $provider, string $from, string $to, array $texts): array
    {
        if (! $this->enabled || $texts === []) {
            return [];
        }

        $map = [];

        foreach ($texts as $key => $text) {
            $map[$this->key($provider, $from, $to, $text)] = $key;
        }

        $hits = [];

        // PSR-16 getMultiple: bulunmayan anahtarlar için varsayılan (null) döner.
        foreach ($this->repository->getMultiple(array_keys($map)) as $cacheKey => $value) {
            if (is_string($value) && isset($map[$cacheKey])) {
                $hits[$map[$cacheKey]] = $value;
            }
        }

        return $hits;
    }

    public function put(string $provider, string $from, string $to, string $text, string $translation): void
    {
        if (! $this->enabled) {
            return;
        }

        $key = $this->key($provider, $from, $to, $text);

        $this->ttl === null
            ? $this->repository->forever($key, $translation)
            : $this->repository->put($key, $translation, $this->ttl);
    }

    public function forget(string $provider, string $from, string $to, string $text): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->repository->forget($this->key($provider, $from, $to, $text));
    }

    /**
     * Sürüm numarasını artırarak bütün çeviri önbelleğini geçersiz kılar.
     * Uygulamanın diğer cache kayıtlarına dokunmaz.
     */
    public function flush(): void
    {
        $this->repository->forever(self::VERSION_KEY, $this->version() + 1);
    }

    public function version(): int
    {
        $version = $this->repository->get(self::VERSION_KEY, 1);

        return is_numeric($version) ? (int) $version : 1;
    }

    protected function key(string $provider, string $from, string $to, string $text): string
    {
        return sprintf(
            '%s:v%d:%s:%s:%s:%s',
            self::PREFIX,
            $this->version(),
            $provider,
            $from,
            $to,
            hash('xxh128', $text),
        );
    }
}
