<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Tests\Fakes;

use DigitalCoreHub\LaravelAiTranslator\Contracts\TranslationProvider;
use DigitalCoreHub\LaravelAiTranslator\Exceptions\ProviderException;

/**
 * Testlerde gerçek sağlayıcı yerine geçen sahte. Ne sorulduğunu ve kaç kere
 * çağrıldığını kaydeder ki zincir davranışını doğrulayabilelim.
 */
class FakeProvider implements TranslationProvider
{
    public int $calls = 0;

    /** @var array<string, string>|null */
    public ?array $lastBatch = null;

    /**
     * @param  array<string, string>  $responses  anahtar => döndürülecek çeviri
     */
    public function __construct(
        protected string $name,
        protected array $responses = [],
        protected int $maxBatch = 50,
        protected bool $configured = true,
        protected ?string $failWith = null,
    ) {}

    public static function failing(string $name, string $message): self
    {
        return new self($name, failWith: $message);
    }

    public static function unconfigured(string $name): self
    {
        return new self($name, configured: false);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function maxBatchSize(): int
    {
        return $this->maxBatch;
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function translateBatch(array $texts, string $from, string $to): array
    {
        $this->calls++;
        $this->lastBatch = $texts;

        if ($this->failWith !== null) {
            throw new ProviderException($this->name, $this->failWith);
        }

        return array_intersect_key($this->responses, $texts);
    }
}
