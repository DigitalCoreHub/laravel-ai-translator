<?php

use DigitalCoreHub\LaravelAiTranslator\Providers\DeepLProvider;
use Illuminate\Support\Facades\Http;

it('translates text via the DeepL API', function () {
    Http::fake([
        'https://api-free.deepl.com/v2/translate' => Http::response([
            'translations' => [
                ['text' => 'Merhaba'],
            ],
        ], 200),
    ]);

    $provider = new DeepLProvider(config: ['api_key' => 'secret']);

    $result = $provider->translate('Hello', 'en', 'tr');

    expect($result)->toBe('Merhaba');

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->url() === 'https://api-free.deepl.com/v2/translate'
            && $data['text'] === ['Hello']
            && $data['target_lang'] === 'TR'
            && $data['source_lang'] === 'EN';
    });
});
