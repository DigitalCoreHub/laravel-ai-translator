# 🧠 Laravel AI Translator / Laravel AI Çevirmen

Laravel AI Translator is a **Laravel 12** compatible package that scans your language files, detects missing keys, and automatically generates translations using multiple AI providers — **OpenAI**, **DeepL**, **Google Translate**, and **DeepSeek**. With v0.4 you now get a Livewire-powered web panel in addition to the CLI toolkit.

Laravel AI Translator, **Laravel 12** ile uyumlu bir pakettir; uygulamanızın dil dosyalarını tarar, eksik çeviri anahtarlarını tespit eder ve **OpenAI**, **DeepL**, **Google Translate** veya **DeepSeek** API’lerini kullanarak bu eksikleri otomatik olarak tamamlar. v0.4 sürümüyle CLI aracının yanı sıra Livewire tabanlı bir web paneli de sunar.

---

## 🚀 Features / Özellikler

- Detects and fills **missing translations** automatically / Eksik çevirileri otomatik olarak tamamlar
- Supports both **PHP** and **JSON** language files / Hem **PHP** hem de **JSON** dil dosyalarını destekler
- **Multiple providers:** OpenAI, DeepL, Google, DeepSeek / **Çoklu provider** desteği
- **Provider fallback:** automatic fail-over / **Fallback** mekanizması
- **Translation cache** (memory for repeated translations) / **Çeviri önbelleği**
- **Automatic file creation** if missing / Eksik dosyaları otomatik oluşturur
- **Dry-run**, **Force-rewrite**, and **Review** CLI flags / CLI için **Dry-run**, **Force**, **Review** modları
- **Detailed JSON report** + CLI progress & summary table / **JSON raporu**, CLI ilerleme ve özet tablosu
- **Livewire 3 + Volt dashboard** for web-based management / Web tabanlı yönetim paneli (Livewire 3 + Volt)
- **Settings page** with provider secrets overview & test buttons / Sağlayıcı ayarlarını görüntüleme ve **Test Connection** butonları
- **Logs & statistics** page reading `ai-translator-report.json` / Log ve istatistik ekranı
- **Manual edit** workflow with logging / Manuel düzenleme ve loglama
- **Optional REST API** (`POST /api/translate`) / Opsiyonel REST API uç noktası

---

## 📦 Installation / Kurulum

```bash
composer require digitalcorehub/laravel-ai-translator
```

Publish the configuration file / Yapılandırma dosyasını yayınlayın:

```bash
php artisan vendor:publish --tag=config --provider="DigitalCoreHub\\LaravelAiTranslator\\AiTranslatorServiceProvider"
```

---

## ⚙️ Environment Setup / Ortam Değişkenleri (`.env`)

### 🌐 General AI Translator Settings / Genel Ayarlar

```env
# Default translation provider (openai, deepl, google, deepseek)
AI_TRANSLATOR_PROVIDER=openai

# Enable caching for repeated translations
AI_TRANSLATOR_CACHE_ENABLED=true
AI_TRANSLATOR_CACHE_DRIVER=file

# Optional: custom translation paths (comma separated)
AI_TRANSLATOR_PATHS="lang,resources/lang"
```

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
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        ],
        'deepl' => [
            'api_key' => env('DEEPL_API_KEY'),
        ],
        'google' => [
            'api_key' => env('GOOGLE_API_KEY'),
        ],
        'deepseek' => [
            'api_key' => env('DEEPSEEK_API_KEY'),
            'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
            'base_url' => env('DEEPSEEK_API_BASE', 'https://api.deepseek.com/v1'),
        ],
    ],

    'cache_enabled' => (bool) env('AI_TRANSLATOR_CACHE_ENABLED', true),
    'cache_driver' => env('AI_TRANSLATOR_CACHE_DRIVER'),

    'paths' => (static function () {
        $paths = env('AI_TRANSLATOR_PATHS');

        if (is_string($paths) && trim($paths) !== '') {
            $segments = array_filter(array_map('trim', explode(',', $paths)));
            $resolved = array_map(static fn ($segment) => \Illuminate\Support\Str::startsWith($segment, '/') ? $segment : base_path($segment), $segments);

            return array_values(array_unique(array_filter($resolved)));
        }

        return [base_path('lang'), base_path('resources/lang')];
    })(),

    'auto_create_missing_files' => true,
    'middleware' => ['web'],
    'api_middleware' => ['api'],
];
```

---

## 🧠 Usage / Kullanım

### CLI

```bash
php artisan ai:translate en tr
```
→ Translates all missing keys from English to Turkish. / İngilizce dil dosyalarındaki eksikleri Türkçe’ye çevirir.

Additional CLI examples / Ek CLI örnekleri:

```bash
php artisan ai:translate en tr fr de      # Multiple target locales / Çoklu hedef dil
php artisan ai:translate en tr --provider=deepl
php artisan ai:translate en tr --review   # Preview without writing / Yazmadan önizle
php artisan ai:translate en tr --force    # Force rewrite / Zorla yeniden yaz
php artisan ai:translate en tr --cache-clear
```

### Web Panel / Web Paneli

- Visit /ai-translator → Livewire dashboard for scanning & translating missing keys.
- Settings page: inspect provider env values, run **Test Connection** buttons.
- Logs page: reads `storage/logs/ai-translator-report.json` and surfaces history.
- Manual edit view: tweak translations and every change is logged to `ai-translator.log`.

Panel rotası: `/ai-translator`

### API (Optional) / Opsiyonel API

```
POST /api/translate
Content-Type: application/json
{
  "from": "en",
  "to": "tr",
  "text": "Hello world",
  "provider": "openai" // optional / opsiyonel
}
```
Response includes translation, provider name, cache status, and duration.

---

## 🖼️ Screens / GIFs (optional) / Ekran Görüntüleri (opsiyonel)

_Add screenshots or GIFs of the dashboard, settings, and logs pages here._

_Panel, ayarlar ve log ekranlarının görsellerini buraya ekleyin._

---

## 📊 Logs & Reports / Log ve Raporlar

All logs and statistics are saved in:

```
storage/logs/ai-translator.log
storage/logs/ai-translator-report.json
```

CLI and web panel share the same TranslationManager so reports stay consistent. / CLI ile web panel aynı TranslationManager’ı kullandığı için raporlar tutarlı kalır.

---

## 🧪 Testing / Testler

Run the package test suite with **Pest**:

```bash
vendor/bin/pest
```

Tests cover / Testler şunları kapsar:

- Multiple providers (OpenAI, DeepL, Google, DeepSeek)
- Provider fallback mechanism / Fallback mekanizması
- JSON + PHP file translation / JSON ve PHP dosyaları
- Cache usage / Önbellek yönetimi
- Review & force CLI modes / CLI modları
- Report file generation / Rapor üretimi
- Livewire dashboard scanning & translation / Livewire panel tarama ve çeviri
- Provider connection testing / Provider bağlantı testi
- Logs table rendering / Log tablosu

---

## 🗓️ v0.4 Highlights / v0.4 Öne Çıkanlar

| Feature / Özellik | Description / Açıklama |
| --- | --- |
| 🧑‍💻 Livewire Dashboard | Scan missing keys, trigger AI translation, manual edit links |
| ⚙️ Settings Page | View provider configs, run **Test Connection** for OpenAI/DeepL/Google/DeepSeek |
| 📈 Logs & Statistics | Reads `ai-translator-report.json`, shows history with provider + duration |
| ✅ Connection Checks | Provider connectivity test buttons with success/failure feedback |
| 🌐 REST API | `POST /api/translate` endpoint using the shared TranslationManager |
| 🧾 Logging | Web actions append to `ai-translator.log` and extend JSON reports |

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
