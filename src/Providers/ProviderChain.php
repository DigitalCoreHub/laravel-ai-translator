<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Providers;

use DigitalCoreHub\LaravelAiTranslator\Data\BatchResult;
use DigitalCoreHub\LaravelAiTranslator\Exceptions\AllProvidersFailedException;
use Throwable;

/**
 * Sağlayıcıları sırayla deneyen katman.
 *
 * Fallback anahtar bazında çalışıyor: ilk sağlayıcı 50 anahtarın 47'sini döndürürse
 * bir sonrakine sadece kalan 3'ü soruyoruz. v0.x'te bir sağlayıcı patladığında bütün
 * grup baştan gidiyordu; bu hem yavaş hem de gereksiz masraftı.
 */
class ProviderChain
{
    /**
     * @param  array<int, string>  $order
     */
    public function __construct(
        protected ProviderRegistry $registry,
        protected array $order = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function order(?string $override = null): array
    {
        $order = $this->order;

        if ($override !== null && $override !== '') {
            array_unshift($order, $override);
        }

        $order = array_values(array_unique(array_filter(
            $order,
            fn (string $name) => $this->registry->has($name)
        )));

        // Hiçbiri tanınmıyorsa config'te tanımlı ne varsa onu deneriz;
        // sessizce hiçbir şey yapmamaktansa bir şey denemek yeğdir.
        return $order === [] ? $this->registry->names() : $order;
    }

    /**
     * Bir grup metni zincir boyunca çevirir.
     *
     * @param  array<string, string>  $texts  anahtar => maskelenmiş metin
     */
    public function translate(array $texts, string $from, string $to, ?string $override = null): BatchResult
    {
        if ($texts === []) {
            return new BatchResult;
        }

        $pending = $texts;
        $translations = [];
        $providers = [];
        $failures = [];

        foreach ($this->order($override) as $name) {
            if ($pending === []) {
                break;
            }

            $provider = $this->registry->get($name);

            if (! $provider->isConfigured()) {
                continue;
            }

            foreach (array_chunk($pending, max(1, $provider->maxBatchSize()), true) as $chunk) {
                try {
                    $result = $provider->translateBatch($chunk, $from, $to);
                } catch (Throwable $exception) {
                    $failures[$name] = $exception;

                    // Bu sağlayıcı bu grup için düştü; kalan parçalarını denemeye devam
                    // etmek yerine sıradaki sağlayıcıya geçiyoruz. Genelde sorun tek bir
                    // parçada değil, sağlayıcının kendisinde olur (anahtar, kota, kesinti).
                    continue 2;
                }

                foreach ($result as $key => $translation) {
                    if (! array_key_exists($key, $pending)) {
                        continue;
                    }

                    $translations[$key] = $translation;
                    $providers[$key] = $name;
                    unset($pending[$key]);
                }
            }
        }

        // Hiçbir şey çevrilemediyse ve elimizde hata varsa bunu yutmuyoruz:
        // "0 anahtar çevrildi" diye sessizce bitmek en kötü senaryo.
        if ($translations === [] && $failures !== []) {
            throw new AllProvidersFailedException($failures);
        }

        return new BatchResult(
            translations: $translations,
            providers: $providers,
            untranslated: array_keys($pending),
            failures: $failures,
        );
    }
}
