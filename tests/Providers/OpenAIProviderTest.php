<?php

use DigitalCoreHub\LaravelAiTranslator\Providers\OpenAIProvider;
use Mockery as m;
use OpenAI\Factory;

it('sends a chat completion request to OpenAI', function () {
    $response = (object) [
        'choices' => [
            (object) ['message' => (object) ['content' => 'Çevrilmiş metin']],
        ],
    ];

    $chat = m::mock();
    $chat->shouldReceive('create')->once()->with(m::on(function (array $payload) {
        return $payload['model'] === 'gpt-4o-mini'
            && $payload['messages'][1]['content'] !== '';
    }))->andReturn($response);

    $client = m::mock();
    $client->shouldReceive('chat')->andReturn($chat);

    $factory = m::mock(Factory::class);
    $factory->shouldReceive('withApiKey')->once()->with('secret')->andReturnSelf();
    $factory->shouldReceive('make')->once()->andReturn($client);

    $provider = new OpenAIProvider($factory, ['api_key' => 'secret', 'model' => 'gpt-4o-mini']);

    $result = $provider->translate('Hello', 'en', 'tr');

    expect($result)->toBe('Çevrilmiş metin');
});
