<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Exceptions;

use Illuminate\Support\Str;

/**
 * Tek bir sağlayıcı patladığında fırlar. Zincirin bir üstündeki sağlayıcıya geçmek için sinyal.
 */
class ProviderException extends AiTranslatorException
{
    public function __construct(
        public readonly string $provider,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(sprintf('[%s] %s', $provider, $message), 0, $previous);
    }

    public static function missingApiKey(string $provider): self
    {
        return new self($provider, 'API anahtarı tanımlı değil.');
    }

    public static function httpFailed(string $provider, int $status, string $body): self
    {
        return new self($provider, sprintf(
            'HTTP %d döndü: %s',
            $status,
            Str::limit(trim($body), 300)
        ));
    }

    public static function malformedResponse(string $provider, string $detail): self
    {
        return new self($provider, 'Cevap beklenen biçimde değil: '.$detail);
    }
}
