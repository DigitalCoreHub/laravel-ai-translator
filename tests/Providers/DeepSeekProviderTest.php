<?php

use DigitalCoreHub\LaravelAiTranslator\Providers\DeepSeekProvider;
use Illuminate\Support\Facades\Http;

it('sends chat completion requests to deepseek api', function () {
    Http::fake([
        'https://api.custom.test/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Merhaba (DeepSeek)']],
            ],
        ], 200),
    ]);

    $provider = new DeepSeekProvider([
        'api_key' => 'secret-key',
        'model' => 'deepseek-chat',
        'base_url' => 'https://api.custom.test/v1',
    ]);

    $result = $provider->translate('Hello <strong>World</strong> :name', 'en', 'tr');

    expect($result)->toBe('Merhaba (DeepSeek)');

    Http::assertSent(function ($request) {
        $payload = $request->data();

        return $request->url() === 'https://api.custom.test/v1/chat/completions'
            && $payload['model'] === 'deepseek-chat'
            && $payload['messages'][0]['role'] === 'system'
            && str_contains($payload['messages'][0]['content'], 'Keep HTML tags, placeholders, and formatting intact')
            && $payload['messages'][1]['content'] === 'Hello <strong>World</strong> :name';
    });
});
