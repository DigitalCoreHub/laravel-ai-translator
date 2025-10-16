<?php

use DigitalCoreHub\LaravelAiTranslator\Providers\GoogleProvider;
use Illuminate\Support\Facades\Http;

it('translates text via the Google Translation API', function () {
    Http::fake([
        'https://translation.googleapis.com/language/translate/v2' => Http::response([
            'data' => [
                'translations' => [
                    ['translatedText' => 'Bonjour'],
                ],
            ],
        ], 200),
    ]);

    $provider = new GoogleProvider(config: ['api_key' => 'secret']);

    $result = $provider->translate('Hello', 'en', 'fr');

    expect($result)->toBe('Bonjour');

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->url() === 'https://translation.googleapis.com/language/translate/v2'
            && $data['q'] === 'Hello'
            && $data['target'] === 'fr'
            && $data['source'] === 'en'
            && $data['key'] === 'secret';
    });
});
