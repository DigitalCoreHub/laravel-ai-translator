<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Exceptions;

use RuntimeException;

/**
 * Paketten çıkan her hatanın atası. Tüketici tek bir catch ile hepsini yakalayabilsin diye var.
 */
class AiTranslatorException extends RuntimeException {}
