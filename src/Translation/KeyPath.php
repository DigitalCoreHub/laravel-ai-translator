<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Translation;

/**
 * İç içe dil dizileri ile nokta gösterimi arasında gidip gelen yardımcı.
 *
 * Laravel'in Arr::dot/Arr::undot çiftini kullanmıyoruz: Arr::undot, anahtarın kendisinde
 * nokta olan kayıtları (ör. JSON dosyalarındaki "Are you sure? Yes." gibi cümle anahtarları)
 * alt dizilere bölüp dosyayı bozuyor. Burada düzleştirmeyi biz yapıyoruz ve hangi anahtarın
 * gerçekten iç içe olduğunu unutmuyoruz.
 */
final class KeyPath
{
    /**
     * İç içe diziyi nokta gösterimine indirger. Yaprak olmayan boş diziler de korunur.
     *
     * @param  array<array-key, mixed>  $items
     * @return array<string, mixed>
     */
    public static function flatten(array $items, string $prefix = ''): array
    {
        $result = [];

        foreach ($items as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value) && $value !== []) {
                $result += self::flatten($value, $path);

                continue;
            }

            $result[$path] = $value;
        }

        return $result;
    }

    /**
     * Nokta gösterimini iç içe diziye geri açar.
     *
     * @param  array<string, mixed>  $items
     * @return array<array-key, mixed>
     */
    public static function expand(array $items): array
    {
        $result = [];

        foreach ($items as $path => $value) {
            $segments = explode('.', $path);
            $cursor = &$result;

            foreach ($segments as $segment) {
                // Yolun ortasında skaler bir değere denk gelirsek onu diziye yükseltiyoruz;
                // aksi halde "foo" ve "foo.bar" aynı dosyada birlikte bulunamazdı.
                if (! isset($cursor[$segment]) || ! is_array($cursor[$segment])) {
                    $cursor[$segment] = [];
                }

                $cursor = &$cursor[$segment];
            }

            $cursor = $value;
            unset($cursor);
        }

        return $result;
    }
}
