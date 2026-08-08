<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Providers;

use DigitalCoreHub\LaravelAiTranslator\Contracts\TranslationProvider;
use DigitalCoreHub\LaravelAiTranslator\Exceptions\ProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;

/**
 * Sağlayıcıların ortak iskeleti: config erişimi, HTTP istemcisi, retry politikası.
 *
 * Bütün sağlayıcılar Illuminate\Http üzerinden konuşuyor — SDK yok. Böylece tek bir
 * timeout/retry politikamız var ve testlerde Http::fake() ile hepsi aynı şekilde
 * taklit edilebiliyor.
 */
abstract class AbstractProvider implements TranslationProvider
{
    /**
     * @param  array<string, mixed>  $config
     * @param  array{times: int, sleep: int}  $retry
     */
    public function __construct(
        protected HttpFactory $http,
        protected array $config = [],
        protected array $retry = ['times' => 3, 'sleep' => 500],
    ) {}

    public function maxBatchSize(): int
    {
        return max(1, (int) $this->config('max_batch', 25));
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    protected function apiKey(): ?string
    {
        $key = $this->config('api_key');

        return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }

    protected function requireApiKey(): string
    {
        return $this->apiKey() ?? throw ProviderException::missingApiKey($this->name());
    }

    protected function timeout(): int
    {
        return max(1, (int) $this->config('timeout', 60));
    }

    /**
     * Ortak retry/timeout ayarlarıyla hazırlanmış istek.
     *
     * Sadece geçici hatalarda tekrar deniyoruz: 429 ve 5xx. 401/403 gibi
     * "anahtarın yanlış" hatalarında tekrar denemek para ve zaman kaybı.
     */
    protected function request(): PendingRequest
    {
        return $this->http
            ->timeout($this->timeout())
            ->connectTimeout(min(10, $this->timeout()))
            ->retry(
                max(1, (int) ($this->retry['times'] ?? 3)),
                max(0, (int) ($this->retry['sleep'] ?? 500)),
                function (\Throwable $exception, PendingRequest $request): bool {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    if (! $exception instanceof RequestException) {
                        return false;
                    }

                    $status = $exception->response->status();

                    if ($status === 429) {
                        // Sunucu ne kadar bekleyeceğimizi söylediyse ona uyuyoruz.
                        $after = (int) $exception->response->header('Retry-After');

                        if ($after > 0) {
                            $request->withOptions(['delay' => min($after, 60) * 1000]);
                        }

                        return true;
                    }

                    return $status >= 500;
                },
                throw: false,
            );
    }

    /**
     * Cevabı doğrular; hata varsa sağlayıcı adıyla birlikte anlamlı bir istisna fırlatır.
     */
    protected function ensureSuccessful(Response $response): Response
    {
        if ($response->failed()) {
            throw ProviderException::httpFailed($this->name(), $response->status(), $response->body());
        }

        return $response;
    }
}
