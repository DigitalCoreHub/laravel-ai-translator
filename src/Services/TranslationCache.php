<?php

namespace DigitalCoreHub\LaravelAiTranslator\Services;

use Illuminate\Contracts\Cache\Repository;

/**
 * Manage caching of translation responses.
 * Çeviri cevaplarının önbelleğe alınmasını yönetir.
 */
class TranslationCache
{
    public function __construct(
        protected Repository $repository,
        protected bool $enabled = true
    ) {}

    public function get(string $provider, string $from, string $to, string $text): mixed
    {
        if (! $this->enabled) {
            return null;
        }

        $key = $this->key($provider, $from, $to, $text);

        return $this->repository->get($key);
    }

    public function put(string $provider, string $from, string $to, string $text, mixed $value): void
    {
        if (! $this->enabled) {
            return;
        }

        $key = $this->key($provider, $from, $to, $text);

        $this->repository->forever($key, $value);
    }

    public function clear(): void
    {
        if (! $this->enabled) {
            return;
        }

        if (method_exists($this->repository, 'clear')) {
            $this->repository->clear();

            return;
        }

        $this->repository->flush();
    }

    public function forget(string $provider, string $from, string $to, string $text): void
    {
        if (! $this->enabled) {
            return;
        }

        $key = $this->key($provider, $from, $to, $text);

        if (method_exists($this->repository, 'forget')) {
            $this->repository->forget($key);

            return;
        }

        if (method_exists($this->repository, 'delete')) {
            $this->repository->delete($key);
        }
    }

    protected function key(string $provider, string $from, string $to, string $text): string
    {
        return sha1(implode('|', [$provider, $from, $to, $text]));
    }
}
