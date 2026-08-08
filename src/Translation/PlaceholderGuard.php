<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Translation;

/**
 * Çeviri sırasında dokunulmaması gereken parçaları korur.
 *
 * Mantık: metindeki :name, {{ $x }}, <a href="…">, %s gibi parçaları çeviriye göndermeden
 * önce ⟦0⟧ gibi nötr belirteçlerle değiştiriyoruz, cevabı alınca geri koyuyoruz.
 *
 * Belirteç olarak __AI_HTML_0__ gibi alt çizgili şeyler kullanmıyoruz; modeller onları
 * "çevrilecek metin" sanıp bozabiliyor. Köşeli çift ayraçlar hem nadir hem de modeller
 * tarafından dokunulmadan geçiriliyor.
 *
 * v0.x'te belirteç kaybolduğunda sessizce ÇEVRİLMEMİŞ kaynak metin geri dönüyordu ve
 * İngilizce metin Türkçe dosyaya yazılıyordu. Artık restore() null döner, çağıran taraf
 * bunu "başarısız anahtar" olarak raporlar.
 */
class PlaceholderGuard
{
    /**
     * Sıra önemli: önce en geniş desenler (HTML etiketi, blade bloğu), sonra dar olanlar.
     * Aksi halde <a href=":url"> içindeki :url önce yakalanır ve etiket bölünür.
     *
     * @var array<int, string>
     */
    protected const PATTERNS = [
        '/<\/?[A-Za-z][^<>]*>/',          // <a href="…">, </strong>
        '/\{\{.*?\}\}/s',                 // {{ $total }}
        '/\{!!.*?!!\}/s',                 // {!! $html !!}
        '/@[A-Za-z]+(?:\([^()]*\))?/',    // @lang('x'), @endif
        '/:[A-Za-z][A-Za-z0-9_]*/',       // :name, :count
        '/\{[A-Za-z0-9_]+\}/',            // {name} (ICU / bazı paketler)
        '/%(?:\d+\$)?[bcdeEfFgGosuxX]/',  // %s, %1$s, %d
    ];

    /**
     * Korunacak parçaları belirteçlerle değiştirir.
     *
     * @return array{0: string, 1: array<string, string>} [maskelenmiş metin, belirteç => orijinal]
     */
    public function mask(string $text): array
    {
        $placeholders = [];
        $index = 0;

        foreach (self::PATTERNS as $pattern) {
            $text = (string) preg_replace_callback(
                $pattern,
                function (array $matches) use (&$placeholders, &$index): string {
                    $token = $this->token($index++);
                    $placeholders[$token] = $matches[0];

                    return $token;
                },
                $text
            );
        }

        return [$text, $placeholders];
    }

    /**
     * Belirteçleri orijinalleriyle değiştirir.
     *
     * @param  array<string, string>  $placeholders
     * @return string|null belirteçlerden biri kaybolduysa null
     */
    public function restore(string $translated, array $placeholders): ?string
    {
        if ($placeholders === []) {
            return $translated;
        }

        // Modeller belirtecin etrafına boşluk ekleyebiliyor ya da ⟦ 0 ⟧ yazabiliyor;
        // bunu tolere ediyoruz, çünkü içerik yine de doğru.
        $translated = (string) preg_replace('/⟦\s*(\d+)\s*⟧/u', '⟦$1⟧', $translated);

        foreach (array_keys($placeholders) as $token) {
            if (! str_contains($translated, $token)) {
                return null;
            }
        }

        return strtr($translated, $placeholders);
    }

    /**
     * Metinde korunacak bir şey var mı? (Sadece hızlı kontrol için.)
     */
    public function hasPlaceholders(string $text): bool
    {
        [, $placeholders] = $this->mask($text);

        return $placeholders !== [];
    }

    protected function token(int $index): string
    {
        return '⟦'.$index.'⟧';
    }
}
