<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Exceptions;

use Throwable;

/**
 * Fallback zincirindeki bütün sağlayıcılar düştüğünde fırlar.
 * Her sağlayıcının kendi hatasını taşır ki "neden olmadı" sorusu tek bakışta cevaplansın.
 */
class AllProvidersFailedException extends AiTranslatorException
{
    /**
     * @param  array<string, Throwable>  $failures  sağlayıcı adı => hata
     */
    public function __construct(public readonly array $failures)
    {
        $detail = collect($failures)
            ->map(static fn (Throwable $e, string $provider) => $e instanceof ProviderException
                // ProviderException zaten "[sağlayıcı] mesaj" biçiminde; tekrar etiketlemeyelim.
                ? $e->getMessage()
                : sprintf('[%s] %s', $provider, $e->getMessage()))
            ->implode(' | ');

        parent::__construct(
            $detail === ''
                ? 'Hiçbir çeviri sağlayıcısı yapılandırılmamış.'
                : 'Bütün çeviri sağlayıcıları başarısız oldu. '.$detail
        );
    }

    /**
     * @return array<int, string>
     */
    public function providers(): array
    {
        return array_keys($this->failures);
    }
}
