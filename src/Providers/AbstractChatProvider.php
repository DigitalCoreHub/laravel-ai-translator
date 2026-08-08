<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Providers;

use DigitalCoreHub\LaravelAiTranslator\Exceptions\ProviderException;
use DigitalCoreHub\LaravelAiTranslator\Support\BatchPayload;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * OpenAI uyumlu /chat/completions uç noktası konuşan LLM sağlayıcılarının ortak gövdesi.
 * OpenAI ve DeepSeek bunu paylaşıyor; farkları sadece uç nokta, model ve
 * structured output desteği.
 */
abstract class AbstractChatProvider extends AbstractProvider
{
    /**
     * @param  array<string, mixed>  $config
     * @param  array{times: int, sleep: int}  $retry
     */
    public function __construct(
        HttpFactory $http,
        array $config = [],
        array $retry = ['times' => 3, 'sleep' => 500],
        protected BatchPayload $payload = new BatchPayload,
        protected ?string $instructions = null,
    ) {
        parent::__construct($http, $config, $retry);
    }

    public function translateBatch(array $texts, string $from, string $to): array
    {
        if ($texts === []) {
            return [];
        }

        $response = $this->ensureSuccessful(
            $this->request()
                ->withToken($this->requireApiKey())
                ->acceptJson()
                ->asJson()
                ->post($this->endpoint(), $this->body($texts, $from, $to))
        );

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw ProviderException::malformedResponse($this->name(), 'cevapta metin yok.');
        }

        return $this->decode($content, $texts);
    }

    /**
     * Modelin döndürdüğü JSON'u anahtar => çeviri haritasına çevirir.
     *
     * Girişte olmayan anahtarları atıyoruz (model uydurmuş olabilir), eksik
     * bıraktıklarını da öylece bırakıyoruz — zinciri işleten taraf onları
     * bir sonraki sağlayıcıya ya da daha küçük bir parçaya devredecek.
     *
     * @param  array<string, string>  $texts
     * @return array<string, string>
     */
    protected function decode(string $content, array $texts): array
    {
        $decoded = json_decode($this->stripCodeFence($content), true);

        if (! is_array($decoded)) {
            throw ProviderException::malformedResponse($this->name(), 'cevap geçerli JSON değil.');
        }

        // Model bazen {"translations": {...}} sarmalıyor; ikisini de kabul ediyoruz.
        if (isset($decoded['translations']) && is_array($decoded['translations'])) {
            $decoded = $decoded['translations'];
        }

        $result = [];

        foreach ($decoded as $key => $value) {
            $key = (string) $key;

            if (array_key_exists($key, $texts) && is_string($value) && $value !== '') {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Bazı modeller structured output'a rağmen ```json ... ``` sarmalayabiliyor.
     */
    protected function stripCodeFence(string $content): string
    {
        $content = trim($content);

        if (! str_starts_with($content, '```')) {
            return $content;
        }

        $content = (string) preg_replace('/^```[a-zA-Z]*\s*/', '', $content);

        return trim((string) preg_replace('/```$/', '', trim($content)));
    }

    /**
     * @param  array<string, string>  $texts
     * @return array<string, mixed>
     */
    protected function body(array $texts, string $from, string $to): array
    {
        $body = [
            'model' => (string) $this->config('model', ''),
            'temperature' => 0,
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt($from, $to)],
                ['role' => 'user', 'content' => $this->payload->encode($texts)],
            ],
        ];

        return array_merge($body, $this->responseFormat());
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseFormat(): array
    {
        return ['response_format' => ['type' => 'json_object']];
    }

    protected function systemPrompt(string $from, string $to): string
    {
        $lines = [
            sprintf(
                'You are a professional software localizer. Translate UI strings from "%s" to "%s".',
                $from,
                $to
            ),
            'Input is a set of id/text records. Translate only the text; never translate or alter the ids.',
            'Return a JSON object mapping every input id to its translated string. Include every id exactly once.',
            'Tokens wrapped in ⟦ ⟧ (for example ⟦0⟧) are protected placeholders: reproduce them verbatim, '
                .'in a position that reads naturally in the target language. Never translate, renumber, drop or duplicate them.',
            'Preserve leading and trailing whitespace, punctuation style and letter case conventions of the target language.',
            'Return the translation only — no explanations, no markdown fences.',
        ];

        if (is_string($this->instructions) && trim($this->instructions) !== '') {
            $lines[] = 'Project-specific instructions: '.trim($this->instructions);
        }

        return implode("\n", $lines);
    }

    abstract protected function endpoint(): string;
}
