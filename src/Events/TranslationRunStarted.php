<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Events;

class TranslationRunStarted
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly int $missing,
        public readonly bool $dryRun,
    ) {}
}
