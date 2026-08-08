<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Translation\Files;

use DigitalCoreHub\LaravelAiTranslator\Exceptions\InvalidLocalePathException;

/**
 * lang/tr/auth.php biçimindeki, dizi döndüren PHP dil dosyaları.
 *
 * DİKKAT — burası v0.x'in en zararlı hatasının olduğu yer. Eski kod şunu yapıyordu:
 *
 *     str_replace(['array (', ')'], ['[', ']'], var_export($data, true))
 *
 * Bu str_replace metnin *içindeki* parantezleri de vuruyordu:
 * 'Kaydet (zorunlu)' → 'Kaydet (zorunlu]'. Sessiz veri bozulması, üstelik geri dönüşü yok.
 * O yüzden var_export'a hiç güvenmiyoruz, diziyi kendimiz seri hale getiriyoruz.
 */
class PhpLocaleFile extends LocaleFile
{
    private const INDENT = '    ';

    public function read(string $path): array
    {
        if (! $this->filesystem->exists($path)) {
            return [];
        }

        // require'ı kapalı bir fonksiyon içinde çalıştırıyoruz ki dil dosyası
        // yanlışlıkla bizim değişkenlerimizi görmesin / ezmesin.
        $data = (static fn (string $file) => require $file)($path);

        if (! is_array($data)) {
            throw InvalidLocalePathException::notAnArray($path);
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    public function write(string $path, array $translations): void
    {
        $this->putAtomically($path, $this->render($translations));
    }

    public function writeEmpty(string $path): void
    {
        $this->putAtomically($path, "<?php\n\nreturn [];\n");
    }

    /**
     * @param  array<array-key, mixed>  $translations
     */
    protected function render(array $translations): string
    {
        return "<?php\n\nreturn ".$this->export($translations, 0).";\n";
    }

    /**
     * Diziyi PHP kaynak koduna çevirir. var_export yok, string oyunu yok.
     *
     * @param  array<array-key, mixed>  $value
     */
    protected function export(array $value, int $depth): string
    {
        if ($value === []) {
            return '[]';
        }

        $indent = str_repeat(self::INDENT, $depth + 1);
        $closingIndent = str_repeat(self::INDENT, $depth);

        // Ardışık 0..n anahtarlı listelerde anahtarları yazmıyoruz; dosya daha okunur oluyor.
        $isList = array_is_list($value);

        $lines = [];

        foreach ($value as $key => $item) {
            $rendered = is_array($item)
                ? $this->export($item, $depth + 1)
                : $this->scalar($item);

            $lines[] = $isList
                ? $indent.$rendered.','
                : $indent.$this->key($key).' => '.$rendered.',';
        }

        return "[\n".implode("\n", $lines)."\n".$closingIndent.']';
    }

    protected function key(int|string $key): string
    {
        return is_int($key) ? (string) $key : $this->quote($key);
    }

    protected function scalar(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) => (string) $value,
            is_float($value) => $this->float($value),
            default => $this->quote((string) $value),
        };
    }

    /**
     * Float'ı geri okunduğunda aynı değeri veren biçimde yazar (0.1 + 0.2 gibi dertler için).
     */
    protected function float(float $value): string
    {
        if (is_nan($value) || is_infinite($value)) {
            return $this->quote((string) $value);
        }

        $rendered = var_export($value, true);

        // var_export tam sayı değerli float'ları "1.0" olarak verir, bazı sürümlerde "1".
        return str_contains($rendered, '.') || str_contains($rendered, 'E')
            ? $rendered
            : $rendered.'.0';
    }

    /**
     * Tek tırnaklı PHP string'i üretir.
     *
     * Tek tırnak içinde sadece iki karakterin kaçışlanması gerekir: ters bölü ve tek tırnak.
     * Bunun dışındaki her şey — parantez, çift tırnak, satır sonu, emoji, :placeholder —
     * olduğu gibi yazılır ve olduğu gibi geri okunur.
     */
    protected function quote(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }
}
