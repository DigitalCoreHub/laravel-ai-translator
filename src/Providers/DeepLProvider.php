<?php

namespace DigitalCoreHub\LaravelAiTranslator\Providers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Translate text through the DeepL API.
 * DeepL API'si üzerinden metin çevirisi gerçekleştirir.
 */
class DeepLProvider extends AbstractProvider
{
    public function name(): string
    {
        return 'deepl';
    }

    public function translate(string $text, ?string $from = null, ?string $to = null): string
    {
        $apiKey = (string) $this->config('api_key');

        if ($apiKey === '') {
            throw new RuntimeException('DeepL API key is missing.');
        }

        $payload = [
            'text' => [$text],
            'target_lang' => strtoupper((string) $to),
        ];

        if ($from !== null) {
            $payload['source_lang'] = strtoupper($from);
        }

        $response = Http::withHeaders([
            'Authorization' => 'DeepL-Auth-Key '.$apiKey,
        ])->asJson()->post('https://api-free.deepl.com/v2/translate', $payload);

        if ($response->failed()) {
            throw new RuntimeException('DeepL translation request failed.');
        }

        $translation = $response->json('translations.0.text');

        if (! is_string($translation)) {
            throw new RuntimeException('DeepL response did not contain a translation.');
        }

        return $translation;
    }
}
