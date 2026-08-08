<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Contracts;

/**
 * Bütün çeviri sağlayıcılarının uyduğu sözleşme.
 *
 * Tekil değil toplu çeviri üzerine kurulu: v0.x'te her anahtar için ayrı istek atılıyordu,
 * 1000 anahtarlık bir dosya 1000 API çağrısı demekti. Artık sağlayıcı ne kadarını tek
 * seferde kaldırabiliyorsa o kadarını tek istekte gönderiyoruz.
 */
interface TranslationProvider
{
    /**
     * Sağlayıcının config ve raporlarda geçen kısa adı (openai, deepl, ...).
     */
    public function name(): string;

    /**
     * Bir grup metni tek seferde çevirir.
     *
     * Dönen dizi girişle aynı anahtarları taşımalı. Eksik anahtar bırakmak serbesttir;
     * çağıran taraf (ProviderChain) eksikleri bir sonraki sağlayıcıya devreder.
     *
     * @param  array<string, string>  $texts  anahtar => kaynak metin
     * @return array<string, string> anahtar => çeviri
     */
    public function translateBatch(array $texts, string $from, string $to): array;

    /**
     * Tek istekte gönderilebilecek azami anahtar sayısı.
     */
    public function maxBatchSize(): int;

    /**
     * Sağlayıcı yapılandırılmış ve kullanılabilir durumda mı (API anahtarı var mı vb.).
     * Zincir, kullanılamayan sağlayıcıyı hiç denemeden atlar.
     */
    public function isConfigured(): bool;
}
