<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Exceptions;

/**
 * Göreli bir yol dil kökünün dışına çıkmaya kalkarsa fırlar (path traversal koruması).
 */
class InvalidLocalePathException extends AiTranslatorException
{
    public static function outsideRoot(string $path): self
    {
        return new self(sprintf('[%s] dil dizininin dışına çıkıyor.', $path));
    }

    public static function notAnArray(string $path): self
    {
        return new self(sprintf('[%s] bir dizi döndürmüyor; geçerli bir dil dosyası değil.', $path));
    }

    public static function invalidJson(string $path, string $reason): self
    {
        return new self(sprintf('[%s] okunamadı: %s', $path, $reason));
    }

    public static function unsupportedExtension(string $path): self
    {
        return new self(sprintf('[%s] desteklenmiyor; sadece .php ve .json dil dosyaları işlenir.', $path));
    }
}
