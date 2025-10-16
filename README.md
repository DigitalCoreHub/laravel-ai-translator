# 🧠 Laravel AI Translator / Laravel AI Çevirmen

Laravel AI Translator is a **Laravel 12** compatible package that scans your language files, detects missing keys, and automatically generates translations using multiple AI providers — **OpenAI**, **DeepL**, **Google Translate**, and **DeepSeek**.

Laravel AI Translator, **Laravel 12** ile uyumlu bir pakettir; uygulamanızın dil dosyalarını tarar, eksik çeviri anahtarlarını tespit eder ve **OpenAI**, **DeepL**, **Google Translate** veya **DeepSeek** API’lerini kullanarak bu eksikleri otomatik olarak tamamlar.

---

## 🚀 Features / Özellikler

- Detects and fills **missing translations** automatically
- Supports both **PHP** and **JSON** language files
- **Multiple providers:** OpenAI, DeepL, Google, DeepSeek
- **Provider fallback:** If one fails, it switches automatically
- **Translation cache** (memory for repeated translations)
- **Automatic file creation** if missing
- **Dry-run**, **Force-rewrite**, and **Review** CLI flags
- **Detailed JSON report** after each translation
- **Progress bar + summary table** in CLI
- **Short array syntax** (`return []`) maintained for PHP files

---

## 📦 Installation / Kurulum

```bash
composer require digitalcorehub/laravel-ai-translator
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=config --provider="DigitalCoreHub\LaravelAiTranslator\AiTranslatorServiceProvider"
```

Then update your `.env` file with the necessary API keys 👇

---

## ⚙️ Environment Setup / Ortam Değişkenleri (`.env`)

### 🌐 General AI Translator Settings

```env
# Default translation provider (openai, deepl, google, deepseek)
AI_TRANSLATOR_PROVIDER=openai

# Enable caching for repeated translations
AI_TRANSLATOR_CACHE_ENABLED=true
AI_TRANSLATOR_CACHE_DRIVER=file

# Optional: custom translation paths
AI_TRANSLATOR_PATHS="lang,resources/lang"
```

---

### 🤖 OpenAI Configuration

```env
OPENAI_API_KEY=sk-your-openai-key
OPENAI_MODEL=gpt-4o-mini
```

### 🧠 DeepL Configuration

```env
DEEPL_API_KEY=your-deepl-api-key
```

### 🌍 Google Translate Configuration

```env
GOOGLE_API_KEY=your-google-api-key
```

> 💡 Make sure the **Cloud Translation API** is enabled in your Google Cloud project:
> https://console.developers.google.com/apis/api/translate.googleapis.com

### 🐉 DeepSeek Configuration

```env
DEEPSEEK_API_KEY=your-deepseek-api-key
DEEPSEEK_MODEL=deepseek-chat
DEEPSEEK_API_BASE=https://api.deepseek.com/v1
```

---

## ⚙️ Configuration File / Yapılandırma Dosyası

`config/ai-translator.php`

```php
return [
    'provider' => env('AI_TRANSLATOR_PROVIDER', 'openai'),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model'   => env('OPENAI_MODEL', 'gpt-4o-mini'),
        ],

        'deepl' => [
            'api_key' => env('DEEPL_API_KEY'),
        ],

        'google' => [
            'api_key' => env('GOOGLE_API_KEY'),
        ],

        'deepseek' => [
            'api_key'  => env('DEEPSEEK_API_KEY'),
            'model'    => env('DEEPSEEK_MODEL', 'deepseek-chat'),
            'base_url' => env('DEEPSEEK_API_BASE', 'https://api.deepseek.com/v1'),
        ],
    ],

    // Caching and fallback
    'cache_enabled' => env('AI_TRANSLATOR_CACHE_ENABLED', true),
    'cache_driver'  => env('AI_TRANSLATOR_CACHE_DRIVER', 'file'),

    // Supported paths
    'paths' => array_map('trim', explode(',', env('AI_TRANSLATOR_PATHS', 'lang'))),

    'auto_create_missing_files' => true,
];
```

---

## 🧠 Usage / Kullanım

### Basic Translation

```bash
php artisan ai:translate en tr
```
→ Translates all missing keys from English to Turkish.
→ İngilizce dil dosyalarındaki eksik anahtarları Türkçe’ye çevirir.

---

### Multiple Languages / Çoklu Dil

```bash
php artisan ai:translate en tr fr de
```
→ Translates English to Turkish, French, and German sequentially.

---

### Provider Selection / Sağlayıcı Seçimi

```bash
php artisan ai:translate en tr --provider=deepl
```
→ Uses the DeepL provider instead of the default one.

---

### Review Mode / İnceleme Modu

```bash
php artisan ai:translate en tr --review
```
→ Shows all AI translations **without writing** to files.

---

### Force Rewrite / Zorla Yeniden Yazma

```bash
php artisan ai:translate en tr --force
```
→ Re-translates and overwrites existing keys.

---

### Clear Cache / Önbelleği Temizleme

```bash
php artisan ai:translate en tr --cache-clear
```
→ Clears cached translations before running.

---

## 📊 Example Output / Örnek Çıktı

```
en -> tr translation started (provider: openai)
Translating (1/47)
Translating (2/47)
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

All logs and statistics are saved in:
```
storage/logs/ai-translator.log
storage/logs/ai-translator-report.json
```

---

## 🧪 Testing / Testler

Run the package test suite with **Pest**:

```bash
vendor/bin/pest
```

Tests cover:
- Multiple providers (OpenAI, DeepL, Google, DeepSeek)
- Provider fallback mechanism
- JSON + PHP file translation
- Cache and performance testing
- Review and force rewrite modes
- Report file generation

Paketi Pest ile test etmek için yukarıdaki komutu kullanabilirsiniz.

---

## 🗓️ Version 0.3 Highlights / 0.3 Sürüm Notları

| Feature | Açıklama |
|----------|-----------|
| 🧠 Multi-provider support | OpenAI, DeepL, Google, DeepSeek desteği eklendi |
| 🔁 Provider fallback | Ana sağlayıcı başarısız olursa diğerine geçer |
| 💾 Translation cache | Tekrar eden metinler için cache sistemi |
| 🧾 JSON report | `ai-translator-report.json` dosyası oluşturur |
| 👀 Review mode | Çevirileri yazmadan terminalde gösterir |
| ⚙️ CLI flags | `--provider`, `--cache-clear`, `--review`, `--force` destekleri |
| 🪶 Short array syntax | PHP dosyaları `return []` formatında saklanır |

---

## 💬 Example `.env` Summary

```env
AI_TRANSLATOR_PROVIDER=openai
AI_TRANSLATOR_CACHE_ENABLED=true
AI_TRANSLATOR_CACHE_DRIVER=file
AI_TRANSLATOR_PATHS="lang,resources/lang"

OPENAI_API_KEY=sk-your-openai-key
OPENAI_MODEL=gpt-4o-mini

DEEPL_API_KEY=your-deepl-api-key

GOOGLE_API_KEY=your-google-api-key

DEEPSEEK_API_KEY=your-deepseek-api-key
DEEPSEEK_MODEL=deepseek-chat
DEEPSEEK_API_BASE=https://api.deepseek.com/v1
```

---

> 🧾 This package uses **Dependabot** for automatic dependency updates
> 🪶 **Laravel Pint** for consistent code style
> 🧪 **Pest** for testing
> Maintained with ❤️ by [Digital Core Hub](https://github.com/digitalcorehub)
