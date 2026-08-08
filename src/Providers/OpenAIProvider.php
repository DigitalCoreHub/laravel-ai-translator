<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Providers;

class OpenAIProvider extends AbstractChatProvider
{
    public function name(): string
    {
        return 'openai';
    }

    protected function endpoint(): string
    {
        return rtrim((string) $this->config('base_url', 'https://api.openai.com/v1'), '/').'/chat/completions';
    }

    /**
     * OpenAI'ın structured output'u: modelin şemaya uyması garanti altında.
     * additionalProperties açık, çünkü anahtarlar dosyadan dosyaya değişiyor;
     * onun yerine "her değer string olsun" kuralını dayatıyoruz.
     *
     * @return array<string, mixed>
     */
    protected function responseFormat(): array
    {
        return [
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'translations',
                    'strict' => false,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'translations' => [
                                'type' => 'object',
                                'description' => 'Map of input id to translated string.',
                                'additionalProperties' => ['type' => 'string'],
                            ],
                        ],
                        'required' => ['translations'],
                    ],
                ],
            ],
        ];
    }
}
