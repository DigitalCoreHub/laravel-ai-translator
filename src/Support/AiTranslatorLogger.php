<?php

namespace DigitalCoreHub\LaravelAiTranslator\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class AiTranslatorLogger
{
    /**
     * Write an info log entry to the package specific log file.
     */
    public static function info(string $message): void
    {
        $path = storage_path('logs/ai-translator.log');

        if (! File::isDirectory(dirname($path))) {
            File::makeDirectory(dirname($path), 0755, true, true);
        }

        $message = ltrim($message);

        if (! str_starts_with($message, 'INFO:')) {
            $message = 'INFO: '.$message;
        }

        Log::build([
            'driver' => 'single',
            'path' => $path,
            'level' => 'info',
        ])->info($message);
    }
}
