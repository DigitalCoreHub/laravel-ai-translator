<?php

namespace DigitalCoreHub\LaravelAiTranslator\Providers;

use OpenAI\Factory;
use OpenAI\Responses\Chat\CreateResponse;
use RuntimeException;

/**
 * Generate translations through the official OpenAI PHP SDK.
 * Resmî OpenAI PHP SDK'si üzerinden çeviriler üretir.
 */
class OpenAIProvider extends AbstractProvider
{
    public function __construct(
        protected ?Factory $factory = null,
        array $config = []
    ) {
        parent::__construct($config);
    }

    public function name(): string
    {
        return 'openai';
    }

    /**
     * Translate the given text into the target language using OpenAI's Chat Completions API.
     * Verilen metni OpenAI Chat Completions API ile hedef dile çevirir.
     */
    public function translate(string $text, ?string $from = null, ?string $to = null): string
    {
        $systemPrompt = 'You are a professional translator. Preserve original HTML tags, Blade variables, tokens such as :count, '
            .'and double-underscore placeholders (e.g. __AI_HTML_0__) exactly as provided.';
        $userPrompt = $this->buildPrompt($text, $from, $to);

        /** @var CreateResponse $response */
        $response = $this->client()->chat()->create([
            'model' => (string) $this->config('model', 'gpt-4o-mini'),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        $message = $response->choices[0]->message->content ?? null;

        if ($message === null) {
            throw new RuntimeException('OpenAI response did not contain a translation.');
        }

        return trim($message);
    }

    /**
     * Build an authenticated OpenAI client instance.
     * Kimlik doğrulanmış bir OpenAI istemcisi oluşturur.
     */
    protected function client()
    {
        $factory = $this->factory instanceof Factory ? $this->factory : new Factory;

        if ($apiKey = $this->config('api_key')) {
            $factory = $factory->withApiKey($apiKey);
        }

        return $factory->make();
    }

    /**
     * Build the instruction payload sent to OpenAI.
     * OpenAI'ye gönderilecek talimat metnini oluşturur.
     */
    protected function buildPrompt(string $text, ?string $from, ?string $to): string
    {
        $parts = [];

        if ($from) {
            $parts[] = "Source language: {$from}";
        }

        if ($to) {
            $parts[] = "Target language: {$to}";
        }

        $parts[] = 'Text to translate:';
        $parts[] = $text;
        $parts[] = 'Respond with only the translated text. Keep tokens like __AI_*__, :placeholders, and Blade syntax unchanged.';

        return implode("\n\n", $parts);
    }
}
