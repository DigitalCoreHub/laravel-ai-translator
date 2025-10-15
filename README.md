# Laravel AI Translator / Laravel AI Çevirmen

Laravel AI Translator is a Laravel 12 compatible package that scans your application's language files, detects missing keys, and automatically generates translations using **OpenAI's API**.
Laravel AI Translator, Laravel 12 ile uyumlu bir pakettir; uygulamanızın dil dosyalarını tarar, eksik anahtarları tespit eder ve **OpenAI API**’sini kullanarak çevirileri otomatik olarak tamamlar.

---

## 🚀 Features / Özellikler

- Detects and fills **missing translations** automatically
- Supports both **PHP** and **JSON** language files
- **Multiple target languages** in one command (`en → tr, fr, de`)
- **Automatic file creation** if target files do not exist
- **Dry-run** (`--dry`) and **Force-rewrite** (`--force`) CLI flags
- **Progress bar** and summary table in CLI output
- **Logs every translation** to `storage/logs/ai-translator.log`
- Maintains **short array syntax** (`[]`) in PHP files

---

## 📦 Installation / Kurulum

```bash
composer require digitalcorehub/laravel-ai-translator
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=config --provider="DigitalCoreHub\LaravelAiTranslator\AiTranslatorServiceProvider"
```

Update your `.env` file with your OpenAI credentials:

```
OPENAI_API_KEY=your-api-key
OPENAI_MODEL=gpt-4o-mini
```

Kurulumdan sonra yapılandırma dosyasını yayımlayın ve `.env` dosyanızı OpenAI kimlik bilgileriyle güncelleyin.

---

## ⚙️ Configuration / Yapılandırma

`config/ai-translator.php`

```php
return [
    'provider' => 'openai',

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model'   => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    // Automatically create missing translation files
    'auto_create_missing_files' => true,
];
```

---

## 🧠 Usage / Kullanım

### Basic translation

```bash
php artisan ai:translate en tr
```

Translates all missing keys from `lang/en` to `lang/tr`.
`lang/en` dizinindeki eksik anahtarları `lang/tr` dosyalarına çevirir.

### Multiple languages / Çoklu dil

```bash
php artisan ai:translate en tr fr de
```

Translates English into Turkish, French and German sequentially.

### Dry-run mode

```bash
php artisan ai:translate en tr --dry
```

Shows missing keys and their AI translations without writing to files.
Eksik anahtarları ve çevirilerini sadece terminalde gösterir, dosyaya yazmaz.

### Force-rewrite

```bash
php artisan ai:translate en tr --force
```

Re-translates and overwrites existing translations.
Var olan çevirileri de günceller.

---

## 📂 Example Output / Örnek Çıktı

```
en -> tr translation started...
Translating (1/34)
Translating (2/34)
...
✔ Translation completed!

+------------------+----------+------------+
| File             | Missing  | Translated |
+------------------+----------+------------+
| auth.php         | 12       | 12         |
| validation.php   | 25       | 25         |
| pagination.php   | 4        | 4          |
| passwords.php    | 6        | 6          |
+------------------+----------+------------+
Total missing: 47 | Translated: 47
```

All progress is logged in:
```
storage/logs/ai-translator.log
```

---

## 🧪 Testing / Testler

Run the test suite with Pest:

```bash
vendor/bin/pest
```

Tests cover:

- Multi-language translation
- JSON file support
- Dry-run and force flags
- Automatic file creation

Paketi Pest ile test etmek için yukarıdaki komutu kullanabilirsiniz.

---

## 🗓️ Version 0.2 Highlights / 0.2 Sürüm Notları

| Feature | Açıklama |
|----------|-----------|
| JSON file support | JSON dil dosyaları artık otomatik çevrilir |
| Multi-language command | Tek seferde birden fazla dile çeviri yapılabilir |
| Auto file creation | Eksik hedef dosyalar otomatik oluşturulur |
| `--dry` / `--force` flags | CLI üzerinden önizleme veya yeniden çeviri seçenekleri |
| Logging | Tüm işlemler `ai-translator.log` dosyasına kaydedilir |
| Short array syntax | PHP dil dosyaları artık `return []` formatında yazılır |

---

> This package uses **Dependabot** for automatic dependency updates
> and **Laravel Pint** for code style consistency.
>
> Maintained with ❤️ by [Digital Core Hub](https://github.com/digitalcorehub)
