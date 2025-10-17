<?php

namespace DigitalCoreHub\LaravelAiTranslator\Providers;

use DigitalCoreHub\LaravelAiTranslator\Contracts\TranslationProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeepSeekProvider implements TranslationProvider
{
    public function __construct(
        protected array $config
    ) {}

    public function translate(string $text, string $from, string $to): string
    {
        $apiKey = $this->config['api_key'] ?? env('DEEPSEEK_API_KEY');
        $model  = $this->config['model'] ?? env('DEEPSEEK_MODEL', 'deepseek-chat');

        $response = Http::withToken($apiKey)
            ->post('https://api.deepseek.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "Translate from {$from} to {$to}. Return only translated text.",
                    ],
                    [
                        'role' => 'user',
                        'content' => $text,
                    ],
                ],
                'temperature' => 0.2,
            ]);

        if ($response->failed()) {
            throw new RuntimeException("DeepSeek API request failed: {$response->status()} - {$response->body()}");
        }

        return trim($response->json('choices.0.message.content', $text));
    }
}