<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Providers;

/**
 * Ağa hiç çıkmayan sağlayıcı: metni (istenirse bir önekle) olduğu gibi geri verir.
 *
 * İki işe yarıyor: testlerde gerçek API'ye ihtiyaç duymamak, ve kurulumdan hemen sonra
 * "boru hattı çalışıyor mu" sorusunu bir kuruş harcamadan cevaplayabilmek.
 */
class NullProvider extends AbstractProvider
{
    public function name(): string
    {
        return 'null';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function maxBatchSize(): int
    {
        return PHP_INT_MAX;
    }

    public function translateBatch(array $texts, string $from, string $to): array
    {
        $prefix = (string) $this->config('prefix', '');

        return array_map(static fn (string $text) => $prefix.$text, $texts);
    }
}
