<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Support;

/**
 * LLM'e gönderilen anahtar listesini kodlar.
 *
 * İki biçim var:
 *
 *  - json (varsayılan): [{"id":"auth.failed","text":"..."}]
 *  - toon: digitalcorehub/laravel-toon kuruluysa aynı veriyi tablo biçiminde yazar:
 *
 *        [2]{id,text}:
 *          auth.failed,These credentials...
 *          auth.throttle,Too many attempts...
 *
 *    Anahtar isimleri her satırda tekrar etmediği için tablo biçimi aynı veriyi
 *    kabaca %40 daha az token ile ifade ediyor. Kısa dosyalarda fark önemsiz,
 *    yüzlerce anahtarlı dosyalarda doğrudan faturaya yansıyor.
 *
 * Sadece İSTEK tarafında TOON kullanıyoruz. Cevabı modelden hâlâ JSON şemasıyla
 * istiyoruz: çeviri metinleri virgül ve tırnak dolu olur, modelin kaçış kurallarına
 * uymasına bel bağlamak yerine JSON'un garantisini tercih ediyoruz.
 *
 * laravel-toon PHP 8.3 / Laravel 12+ istediği, biz ise PHP 8.2 / Laravel 11'i de
 * desteklediğimiz için bağımlılık zorunlu değil — kurulu değilse sessizce json'a düşer.
 */
class BatchPayload
{
    public function __construct(protected string $format = 'json') {}

    /**
     * @param  array<string, string>  $texts  anahtar => kaynak metin
     */
    public function encode(array $texts): string
    {
        $rows = [];

        foreach ($texts as $id => $text) {
            $rows[] = ['id' => (string) $id, 'text' => $text];
        }

        if ($this->format === 'toon' && $this->toonAvailable()) {
            return $this->encodeToon($rows);
        }

        return json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * TOON gerçekten kullanılabilir durumda mı? (Testler ve teşhis için açık bıraktık.)
     */
    public function usesToon(): bool
    {
        return $this->format === 'toon' && $this->toonAvailable();
    }

    /**
     * @param  array<int, array{id: string, text: string}>  $rows
     */
    protected function encodeToon(array $rows): string
    {
        /** @var callable $encoder */
        $encoder = ['DigitalCoreHub\\Toon\\Facades\\Toon', 'encode'];

        return (string) $encoder($rows);
    }

    protected function toonAvailable(): bool
    {
        return class_exists('DigitalCoreHub\\Toon\\Facades\\Toon');
    }
}
