<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Data;

/**
 * Bir kaynak dil dosyası ve ona karşılık gelen hedef dosya.
 *
 * v0.x'te "en/auth.php" gibi göreli bir yol ortalıkta dolaşıyor, kimi yerde proje köküne
 * kimi yerde dil köküne göre çözülüyordu; sonuçta ai:sync base_path('en/auth.php') gibi
 * var olmayan yollara yazmaya çalışıyordu. Artık göreli yol tek başına dolaşmıyor:
 * kaynağı, hedefi ve hangi dil köküne ait olduğu hep birlikte taşınıyor.
 */
final readonly class LocaleFilePair
{
    public function __construct(
        /** Bu çiftin ait olduğu dil kökü, ör. /app/lang */
        public string $root,
        /** Dil köküne göre kaynak yol, ör. "en/auth.php" veya "en.json" */
        public string $sourceRelative,
        public string $sourcePath,
        /** Dil köküne göre hedef yol, ör. "tr/auth.php" veya "tr.json" */
        public string $targetRelative,
        public string $targetPath,
    ) {}

    public function isJson(): bool
    {
        return str_ends_with(strtolower($this->targetRelative), '.json');
    }
}
