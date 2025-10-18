# 🧠 Laravel AI Translator / Laravel AI Çevirmen

Laravel AI Translator is a **Laravel 12** compatible package that scans your language files, detects missing keys, and automatically generates translations using multiple AI providers — **OpenAI**, **DeepL**, **Google Translate**, and **DeepSeek**.
With **v0.5**, the Livewire-powered panel is integrated with Laravel's authentication system, e-mail allowlists, and granular access logs on top of the CLI toolkit.

Laravel AI Translator, **Laravel 12** ile uyumlu bir pakettir; uygulamanızın dil dosyalarını tarar, eksik çeviri anahtarlarını tespit eder ve **OpenAI**, **DeepL**, **Google Translate** veya **DeepSeek** API’lerini kullanarak bu eksikleri otomatik olarak tamamlar.
**v0.5** sürümü ile Livewire tabanlı web paneli Laravel kullanıcı doğrulaması, e-posta yetkilendirme listesi ve ayrıntılı erişim logları ile güçlendirildi.

---

## 🚀 Features / Özellikler

- Detects and fills **missing translations** automatically / Eksik çevirileri otomatik olarak tamamlar  
- Supports both **PHP** and **JSON** language files / Hem **PHP** hem de **JSON** dosyaları  
- **Multiple providers:** OpenAI, DeepL, Google, DeepSeek  
- **Provider fallback**: automatic fail-over mechanism
- **Translation cache** to prevent redundant API calls
- **Automatic file creation** for missing locale files
- 🔐 **Secure panel access** with Laravel auth integration & e-mail allowlist / Güvenli panel erişimi (Laravel auth + e-posta yetkilendirme)
- **CLI modes:** `--dry`, `--force`, `--review`
- **Detailed JSON report** + CLI progress table
- **Livewire 3 + Volt Dashboard** for visual management
- **Settings page** with provider test buttons  
- **Logs & statistics** page reading `ai-translator-report.json`  
- **Manual edit** & save workflow  
- Optional **REST API** (`POST /api/translate`)  

---

## 📦 Installation / Kurulum

```bash
composer require digitalcorehub/laravel-ai-translator
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=config --provider="DigitalCoreHub\\LaravelAiTranslator\\AiTranslatorServiceProvider"
```

---

## ⚙️ Environment Setup / Ortam Değişkenleri (`.env`)

### 🌐 General Settings

```env
AI_TRANSLATOR_PROVIDER=openai
AI_TRANSLATOR_CACHE_ENABLED=true
AI_TRANSLATOR_CACHE_DRIVER=file
AI_TRANSLATOR_PATHS="lang,resources/lang"
AI_TRANSLATOR_AUTH_ENABLED=true
AI_TRANSLATOR_AUTHORIZED_EMAILS=admin@digitalcorehub.com,batuhan@digitalcorehub.com
AI_TRANSLATOR_API_AUTH=true
```

### 🤖 OpenAI

```env
OPENAI_API_KEY=sk-your-openai-key
OPENAI_MODEL=gpt-4o-mini
```

### 🧠 DeepL

```env
DEEPL_API_KEY=your-deepl-api-key
```

### 🌍 Google Translate

```env
GOOGLE_API_KEY=your-google-api-key
```
> 💡 Enable **Cloud Translation API** here:  
> https://console.developers.google.com/apis/api/translate.googleapis.com

### 🐉 DeepSeek

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
            'api_key' => env('DEEPSEEK_API_KEY'),
            'model'   => env('DEEPSEEK_MODEL', 'deepseek-chat'),
            'base_url'=> env('DEEPSEEK_API_BASE', 'https://api.deepseek.com/v1'),
        ],
    ],

    'auth_enabled' => (bool) env('AI_TRANSLATOR_AUTH_ENABLED', true),

    'authorized_emails' => (static function () {
        $configured = env('AI_TRANSLATOR_AUTHORIZED_EMAILS');

        if (is_string($configured) && trim($configured) !== '') {
            return collect(explode(',', $configured))
                ->map(static fn (string $email) => trim($email))
                ->filter()
                ->values()
                ->all();
        }

        return [
            'admin@digitalcorehub.com',
            'batuhan@digitalcorehub.com',
        ];
    })(),

    'cache_enabled' => (bool) env('AI_TRANSLATOR_CACHE_ENABLED', true),
    'cache_driver' => env('AI_TRANSLATOR_CACHE_DRIVER'),
    'paths' => [base_path('lang'), base_path('resources/lang')],
    'auto_create_missing_files' => true,
    'middleware' => ['web', 'auth', \DigitalCoreHub\LaravelAiTranslator\Http\Middleware\EnsureAiTranslatorAccess::class],
    'api_middleware' => ['api'],
    'api_auth' => (bool) env('AI_TRANSLATOR_API_AUTH', true),
];
```

---

## 🧠 Usage / Kullanım

### CLI
```bash
php artisan ai:translate en tr
```
→ Translates all missing keys from English to Turkish.

More examples:
```bash
php artisan ai:translate en tr fr de      # Multi-language
php artisan ai:translate en tr --provider=deepl
php artisan ai:translate en tr --review   # Preview only
php artisan ai:translate en tr --force    # Force rewrite
php artisan ai:translate en tr --cache-clear
```

### Web Panel
- Sign in through your application's standard `/login` route using a Laravel user account.
- Navigate to `/ai-translator`; access is restricted to the e-mail allowlist when configured.
- Scan & translate missing keys
- Edit manually or re-run translations
- View logs, provider connections, and settings
- Use the header menu to see who is signed in and to log out via Laravel's default form

### API (optional)
```http
POST /api/translate
Content-Type: application/json

{
  "from": "en",
  "to": "tr",
  "text": "Hello world",
  "provider": "openai"
}
```

---

## 📊 Logs & Reports

Saved under:
```
storage/logs/ai-translator.log
storage/logs/ai-translator-report.json
```

CLI and Web use the same TranslationManager for consistent results.

---

## 🧪 Testing / Testler

Run:
```bash
vendor/bin/pest
```

Covers:
- Multi-provider support  
- Fallback system  
- JSON/PHP translation  
- Cache operations  
- CLI review/force  
- Livewire dashboard  
- Provider connection tests  
- Logs rendering  

---

## 🗓️ v0.5 Highlights

| Feature | Description |
|----------|--------------|
| 🔐 **Secure Panel Access** | Login form, session control, and e-mail authorization |
| 🧾 **Auth Logging** | Dedicated `ai-translator.log` entries for login/logout |
| 🧭 **Guarded Routes** | Middleware-protected dashboard, edit, settings, logs |
| 🌐 **Protected API** | Optional `auth:sanctum` requirement for `/api/translate` |
| 🧑‍💻 **Livewire Dashboard** | Scan & translate missing keys |
| ⚙️ **Settings Page** | Manage provider configs, test API connections |
| 📈 **Logs & Statistics** | Show provider, duration, file history |

---

## 💬 Example `.env`

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

> 🧾 Uses **Dependabot** for dependency updates  
> 🪶 **Laravel Pint** for code style consistency  
> 🧪 **Pest** for test coverage  
> Maintained with ❤️ by [Digital Core Hub](https://github.com/digitalcorehub)
