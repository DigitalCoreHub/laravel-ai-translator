<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Translation\Files;

use DigitalCoreHub\LaravelAiTranslator\Exceptions\InvalidLocalePathException;
use JsonException;

/**
 * lang/tr.json biçimindeki düz JSON dil dosyaları.
 */
class JsonLocaleFile extends LocaleFile
{
    private const FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    public function read(string $path): array
    {
        if (! $this->filesystem->exists($path)) {
            return [];
        }

        $contents = trim((string) $this->filesystem->get($path));

        if ($contents === '') {
            return [];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidLocalePathException::invalidJson($path, $exception->getMessage());
        }

        if (! is_array($decoded)) {
            throw InvalidLocalePathException::notAnArray($path);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    public function write(string $path, array $translations): void
    {
        $encoded = json_encode($translations, self::FLAGS | JSON_THROW_ON_ERROR);

        $this->putAtomically($path, $encoded.PHP_EOL);
    }

    public function writeEmpty(string $path): void
    {
        $this->putAtomically($path, '{}'.PHP_EOL);
    }
}
