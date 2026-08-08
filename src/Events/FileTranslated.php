<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Events;

use DigitalCoreHub\LaravelAiTranslator\Data\FileOutcome;

class FileTranslated
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly FileOutcome $outcome,
    ) {}
}
