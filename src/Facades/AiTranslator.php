<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Facades;

use DigitalCoreHub\LaravelAiTranslator\Data\MissingKey;
use DigitalCoreHub\LaravelAiTranslator\Data\TranslatedKey;
use DigitalCoreHub\LaravelAiTranslator\Data\TranslationRun;
use DigitalCoreHub\LaravelAiTranslator\Translation\Translator;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Translator from(string $locale)
 * @method static Translator to(string $locale)
 * @method static Translator provider(?string $name)
 * @method static Translator dryRun(bool $dryRun = true)
 * @method static Translator force(bool $force = true)
 * @method static Translator only(?string $filter)
 * @method static Translator onProgress(?callable $callback)
 * @method static TranslationRun translate()
 * @method static TranslatedKey text(string $text)
 * @method static array<int, MissingKey> missing()
 * @method static array<int, MissingKey> entries()
 * @method static array<int, string> locales()
 *
 * @see Translator
 */
class AiTranslator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ai-translator';
    }
}
