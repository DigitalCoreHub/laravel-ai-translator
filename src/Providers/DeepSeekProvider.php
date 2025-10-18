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

    public function translate(string $text, ?string $from = null, ?string $to = null): string
    {
        $apiKey = $this->config['api_key'] ?? env('DEEPSEEK_API_KEY');
        $model = $this->config['model'] ?? env('DEEPSEEK_MODEL', 'deepseek-chat');
        $baseUrl = rtrim($this->config['base_url'] ?? env('DEEPSEEK_API_BASE', 'https://api.deepseek.com/v1'), '/');
        $endpoint = $baseUrl.'/chat/completions';

        if (! $apiKey) {
            throw new RuntimeException('DeepSeek API key is missing.');
        }

        $systemPrompt = sprintf(
            'Translate from %s to %s. Keep HTML tags, placeholders, and formatting intact. Return only translated text.',
            $from ?? 'auto',
            $to ?? 'auto'
        );

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->post($endpoint, [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
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
