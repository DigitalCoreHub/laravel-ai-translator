<?php

namespace DigitalCoreHub\LaravelAiTranslator\Tests\Fakes;

use DigitalCoreHub\LaravelAiTranslator\Contracts\TranslationProvider;
use RuntimeException;

class FailingProvider implements TranslationProvider
{
    public int $calls = 0;

    public function translate(string $text, ?string $from = null, ?string $to = null): string
    {
        $this->calls++;

        throw new RuntimeException('Provider failure.');
    }
}
