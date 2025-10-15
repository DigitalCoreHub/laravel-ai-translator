<?php

namespace Codex\LaravelAiTranslator\Tests\Fakes;

use Codex\LaravelAiTranslator\Contracts\TranslationProvider;

/**
 * Simple fake translation provider for tests.
 * Testler için basit sahte çeviri sağlayıcısı.
 */
class FakeProvider implements TranslationProvider
{
    public array $calls = [];

    /**
     * Record translation calls and simulate an echo translation.
     * Çeviri çağrılarını kaydeder ve taklit bir çeviri döndürür.
     */
    public function translate(string $text, string $from = null, string $to = null): string
    {
        $this->calls[] = compact('text', 'from', 'to');

        return sprintf('[%s->%s] %s', $from ?? 'auto', $to ?? 'auto', $text);
    }
}
