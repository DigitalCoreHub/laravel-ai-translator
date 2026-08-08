<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Providers;

use DigitalCoreHub\LaravelAiTranslator\Exceptions\ProviderException;

/**
 * DeepL. Zaten native batch: tek istekte text[] dizisi gönderilir, sırası korunarak döner.
 */
class DeepLProvider extends AbstractProvider
{
    public function name(): string
    {
        return 'deepl';
    }

    public function translateBatch(array $texts, string $from, string $to): array
    {
        if ($texts === []) {
            return [];
        }

        $keys = array_keys($texts);

        $payload = [
            'text' => array_values($texts),
            'target_lang' => $this->normalizeTarget($to),
        ];

        if ($from !== '') {
            $payload['source_lang'] = strtoupper(explode('-', explode('_', $from)[0])[0]);
        }

        $response = $this->ensureSuccessful(
            $this->request()
                ->withHeaders(['Authorization' => 'DeepL-Auth-Key '.$this->requireApiKey()])
                ->asJson()
                ->post($this->endpoint(), $payload)
        );

        $translations = $response->json('translations');

        if (! is_array($translations) || count($translations) !== count($keys)) {
            throw ProviderException::malformedResponse(
                $this->name(),
                sprintf('%d çeviri beklenirken %d geldi.', count($keys), is_array($translations) ? count($translations) : 0)
            );
        }

        $result = [];

        foreach (array_values($translations) as $index => $translation) {
            $text = is_array($translation) ? ($translation['text'] ?? null) : null;

            if (is_string($text) && $text !== '') {
                $result[$keys[$index]] = $text;
            }
        }

        return $result;
    }

    /**
     * Ücretsiz anahtarlar ":fx" ile biter ve farklı bir alan adına gider.
     * v0.x her zaman api-free'ye gidiyordu, yani ücretli anahtarlar hiç çalışmıyordu.
     */
    protected function endpoint(): string
    {
        $configured = $this->config('base_url');

        if (is_string($configured) && trim($configured) !== '') {
            return rtrim($configured, '/').'/v2/translate';
        }

        return str_ends_with($this->requireApiKey(), ':fx')
            ? 'https://api-free.deepl.com/v2/translate'
            : 'https://api.deepl.com/v2/translate';
    }

    /**
     * DeepL hedef dili büyük harf ister ve bazı diller için bölge kodu zorunlu
     * (EN yerine EN-GB/EN-US, PT yerine PT-BR/PT-PT).
     */
    protected function normalizeTarget(string $to): string
    {
        $normalized = strtoupper(str_replace('_', '-', $to));

        return match ($normalized) {
            'EN' => 'EN-GB',
            'PT' => 'PT-PT',
            default => $normalized,
        };
    }
}
