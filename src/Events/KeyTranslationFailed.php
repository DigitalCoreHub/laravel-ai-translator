<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Events;

use DigitalCoreHub\LaravelAiTranslator\Data\FailedKey;

class KeyTranslationFailed
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly FailedKey $key,
    ) {}
}
