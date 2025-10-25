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
        self::write('ai-translator.log', $message);
    }

    public static function watch(string $message): void
    {
        self::write('ai-translator-watch.log', $message);
    }

    public static function sync(string $message): void
    {
        self::write('ai-translator-sync.log', $message);
    }

    public static function queue(string $message): void
    {
        self::write('ai-translator-queue.log', $message);
    }

    public static function write(string $filename, string $message, string $level = 'info'): void
    {
        $path = storage_path('logs/'.$filename);

        if (! File::isDirectory(dirname($path))) {
            File::makeDirectory(dirname($path), 0755, true, true);
        }

        $message = trim($message);
        $timestamp = now()->format('H:i:s');

        if (! str_starts_with($message, '[')) {
            $message = sprintf('[%s] %s', $timestamp, $message);
        }

        Log::build([
            'driver' => 'single',
            'path' => $path,
            'level' => $level,
        ])->log($level, $message);
    }
}
