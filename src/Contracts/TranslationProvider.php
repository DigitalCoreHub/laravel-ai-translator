<?php

namespace DigitalCoreHub\LaravelAiTranslator\Contracts;

interface TranslationProvider
{
    /**
     * Translate the given text into the desired language.
     * Verilen metni hedef dile çevirir.
     */
    public function translate(string $text, ?string $from = null, ?string $to = null): string;
}
