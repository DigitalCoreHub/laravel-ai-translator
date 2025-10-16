<?php

namespace DigitalCoreHub\LaravelAiTranslator\Providers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Translate text using Google Cloud Translation API v2.
 * Google Cloud Translation API v2 ile metin çevirir.
 */
class GoogleProvider extends AbstractProvider
{
    public function name(): string
    {
        return 'google';
    }

    public function translate(string $text, ?string $from = null, ?string $to = null): string
    {
        $apiKey = (string) $this->config('api_key');

        if ($apiKey === '') {
            throw new RuntimeException('Google Translate API key is missing.');
        }

        $query = [
            'q' => $text,
            'target' => $to,
            'format' => 'text',
            'key' => $apiKey,
        ];

        if ($from !== null) {
            $query['source'] = $from;
        }

        $response = Http::asJson()->post('https://translation.googleapis.com/language/translate/v2', $query);

        if ($response->failed()) {
            throw new RuntimeException('Google translation request failed.');
        }

        $translation = $response->json('data.translations.0.translatedText');

        if (! is_string($translation)) {
            throw new RuntimeException('Google response did not contain a translation.');
        }

        return html_entity_decode($translation, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
