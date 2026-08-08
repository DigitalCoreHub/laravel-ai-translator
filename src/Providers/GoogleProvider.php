<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Providers;

use DigitalCoreHub\LaravelAiTranslator\Exceptions\ProviderException;

/**
 * Google Cloud Translation v2. q[] ile toplu istek destekliyor, sıra korunur.
 */
class GoogleProvider extends AbstractProvider
{
    public function name(): string
    {
        return 'google';
    }

    public function translateBatch(array $texts, string $from, string $to): array
    {
        if ($texts === []) {
            return [];
        }

        $keys = array_keys($texts);

        $payload = [
            'q' => array_values($texts),
            'target' => $to,
            'format' => 'text',
        ];

        if ($from !== '') {
            $payload['source'] = $from;
        }

        $response = $this->ensureSuccessful(
            $this->request()
                ->asJson()
                ->post($this->endpoint().'?key='.urlencode($this->requireApiKey()), $payload)
        );

        $translations = $response->json('data.translations');

        if (! is_array($translations) || count($translations) !== count($keys)) {
            throw ProviderException::malformedResponse(
                $this->name(),
                sprintf('%d çeviri beklenirken %d geldi.', count($keys), is_array($translations) ? count($translations) : 0)
            );
        }

        $result = [];

        foreach (array_values($translations) as $index => $translation) {
            $text = is_array($translation) ? ($translation['translatedText'] ?? null) : null;

            if (! is_string($text) || $text === '') {
                continue;
            }

            // format=text göndersek bile Google &#39; gibi entity'ler döndürebiliyor.
            $result[$keys[$index]] = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $result;
    }

    protected function endpoint(): string
    {
        return rtrim(
            (string) $this->config('base_url', 'https://translation.googleapis.com/language/translate/v2'),
            '/'
        );
    }
}
