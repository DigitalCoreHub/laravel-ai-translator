<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Events;

use DigitalCoreHub\LaravelAiTranslator\Data\TranslationRun;

class TranslationRunCompleted
{
    public function __construct(public readonly TranslationRun $run) {}
}
