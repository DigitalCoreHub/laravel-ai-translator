<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Varsayılan sağlayıcı
    |--------------------------------------------------------------------------
    |
    | İlk denenecek sağlayıcı. Aşağıdaki "providers" listesinde tanımlı olmalı.
    | Test/geliştirme için "null" sağlayıcısı ağa çıkmadan çalışır.
    |
    */

    'provider' => env('AI_TRANSLATOR_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Yedek sağlayıcı sırası
    |--------------------------------------------------------------------------
    |
    | Ana sağlayıcı patlarsa sırayla bunlar denenir. API anahtarı tanımlı olmayan
    | sağlayıcılar hiç denenmeden atlanır, o yüzden listeyi dolu bırakmak zararsız.
    |
    */

    'fallback' => array_values(array_filter(explode(',', (string) env('AI_TRANSLATOR_FALLBACK', 'deepl,google')))),

    /*
    |--------------------------------------------------------------------------
    | Sağlayıcılar
    |--------------------------------------------------------------------------
    |
    | timeout saniye cinsinden, max_batch tek istekte gönderilecek azami anahtar sayısı.
    | Kendi sağlayıcınızı yazacaksanız "class" anahtarıyla sınıf adını verin;
    | TranslationProvider arayüzünü uygulaması yeterli.
    |
    */

    'providers' => [

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            // Varsayılanı bilerek muhafazakâr tuttuk: gpt-4o-mini ucuz, her hesapta açık ve
            // json_schema destekliyor. Daha yeni bir modeliniz varsa env'den geçin.
            'model' => env('AI_TRANSLATOR_OPENAI_MODEL', 'gpt-4o-mini'),
            'base_url' => env('AI_TRANSLATOR_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'timeout' => (int) env('AI_TRANSLATOR_OPENAI_TIMEOUT', 120),
            'max_batch' => (int) env('AI_TRANSLATOR_OPENAI_BATCH', 50),
        ],

        'deepseek' => [
            'api_key' => env('DEEPSEEK_API_KEY'),
            'model' => env('AI_TRANSLATOR_DEEPSEEK_MODEL', 'deepseek-chat'),
            'base_url' => env('AI_TRANSLATOR_DEEPSEEK_BASE_URL', 'https://api.deepseek.com/v1'),
            'timeout' => (int) env('AI_TRANSLATOR_DEEPSEEK_TIMEOUT', 120),
            'max_batch' => (int) env('AI_TRANSLATOR_DEEPSEEK_BATCH', 30),
        ],

        'deepl' => [
            'api_key' => env('DEEPL_API_KEY'),
            // Boş bırakılırsa anahtarın ":fx" ile bitip bitmediğine bakılarak
            // free/pro uç noktası otomatik seçilir.
            'base_url' => env('AI_TRANSLATOR_DEEPL_BASE_URL'),
            'timeout' => (int) env('AI_TRANSLATOR_DEEPL_TIMEOUT', 60),
            'max_batch' => (int) env('AI_TRANSLATOR_DEEPL_BATCH', 50),
        ],

        'google' => [
            'api_key' => env('GOOGLE_TRANSLATE_API_KEY', env('GOOGLE_API_KEY')),
            'base_url' => env('AI_TRANSLATOR_GOOGLE_BASE_URL', 'https://translation.googleapis.com/language/translate/v2'),
            'timeout' => (int) env('AI_TRANSLATOR_GOOGLE_TIMEOUT', 60),
            'max_batch' => (int) env('AI_TRANSLATOR_GOOGLE_BATCH', 100),
        ],

        'null' => [
            // Ağa çıkmaz, metni olduğu gibi geri verir. Test ve prova için.
            'prefix' => env('AI_TRANSLATOR_NULL_PREFIX', ''),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Dil dosyası yolları
    |--------------------------------------------------------------------------
    |
    | Virgülle ayrılmış liste verilebilir. Göreli yollar base_path()'e göre çözülür.
    |
    */

    'paths' => env('AI_TRANSLATOR_PATHS', 'lang,resources/lang'),

    /*
    |--------------------------------------------------------------------------
    | Kaynak dil
    |--------------------------------------------------------------------------
    |
    | Komutlara dil verilmediğinde kullanılacak varsayılan kaynak.
    |
    */

    'source_locale' => env('AI_TRANSLATOR_SOURCE_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Önbellek
    |--------------------------------------------------------------------------
    |
    | Aynı metni iki kere çevirip iki kere para ödememek için. "store" boşsa
    | uygulamanın varsayılan cache store'u kullanılır. "ttl" saniye cinsinden,
    | null verilirse süresiz saklanır.
    |
    */

    'cache' => [
        'enabled' => (bool) env('AI_TRANSLATOR_CACHE_ENABLED', true),
        'store' => env('AI_TRANSLATOR_CACHE_STORE'),
        'ttl' => env('AI_TRANSLATOR_CACHE_TTL') === null
            ? 60 * 60 * 24 * 30
            : (int) env('AI_TRANSLATOR_CACHE_TTL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Eşzamanlılık
    |--------------------------------------------------------------------------
    |
    | Aynı anda kaç batch isteği havada olsun. Sağlayıcınızın rate limit'ine göre
    | ayarlayın; 429 yiyorsanız düşürün.
    |
    */

    'concurrency' => (int) env('AI_TRANSLATOR_CONCURRENCY', 5),

    /*
    |--------------------------------------------------------------------------
    | Yeniden deneme
    |--------------------------------------------------------------------------
    |
    | Geçici hatalarda (429, 5xx, timeout) kaç kere ve kaç ms bekleyerek denensin.
    | Bekleme her denemede ikiye katlanır; sunucu Retry-After gönderirse ona uyulur.
    |
    */

    'retry' => [
        'times' => (int) env('AI_TRANSLATOR_RETRY_TIMES', 3),
        'sleep' => (int) env('AI_TRANSLATOR_RETRY_SLEEP', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | İstek gövdesi biçimi
    |--------------------------------------------------------------------------
    |
    | LLM sağlayıcılarına gönderilen anahtar listesinin biçimi: "json" veya "toon".
    | TOON tablo biçimi aynı veriyi ~%40 daha az token ile ifade eder ama
    | digitalcorehub/laravel-toon paketinin kurulu olmasını gerektirir. Kurulu
    | değilse sessizce json'a düşer. Model cevabı her hâlükârda JSON şemasıyla alınır.
    |
    */

    'prompt_format' => env('AI_TRANSLATOR_PROMPT_FORMAT', 'json'),

    /*
    |--------------------------------------------------------------------------
    | Çeviri talimatı
    |--------------------------------------------------------------------------
    |
    | LLM sağlayıcılarına gönderilen ek yönerge. Marka adları, hitap şekli (siz/sen),
    | terim sözlüğü gibi projeye özel kuralları buraya yazın.
    |
    */

    'instructions' => env('AI_TRANSLATOR_INSTRUCTIONS'),

    /*
    |--------------------------------------------------------------------------
    | Kuyruk
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'connection' => env('AI_TRANSLATOR_QUEUE_CONNECTION'),
        'name' => env('AI_TRANSLATOR_QUEUE', 'ai-translations'),
        'timeout' => (int) env('AI_TRANSLATOR_QUEUE_TIMEOUT', 600),
        'tries' => (int) env('AI_TRANSLATOR_QUEUE_TRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | İzleme (ai:watch)
    |--------------------------------------------------------------------------
    |
    | "locales" boşsa dil kökünde bulunan bütün diller hedef alınır.
    | "interval" saniye cinsinden tarama aralığı.
    |
    */

    'watch' => [
        'locales' => array_values(array_filter(explode(',', (string) env('AI_TRANSLATOR_WATCH_LOCALES', '')))),
        'interval' => (int) env('AI_TRANSLATOR_WATCH_INTERVAL', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rapor
    |--------------------------------------------------------------------------
    |
    | Her koşunun özeti buraya JSON olarak eklenir. "keep" son kaç koşunun
    | saklanacağını belirler; dosyanın sonsuza kadar şişmesini engeller.
    |
    */

    'report' => [
        'enabled' => (bool) env('AI_TRANSLATOR_REPORT_ENABLED', true),
        'path' => env('AI_TRANSLATOR_REPORT_PATH', storage_path('logs/ai-translator-report.json')),
        'keep' => (int) env('AI_TRANSLATOR_REPORT_KEEP', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log kanalı
    |--------------------------------------------------------------------------
    |
    | null ise uygulamanın varsayılan kanalı kullanılır.
    |
    */

    'log_channel' => env('AI_TRANSLATOR_LOG_CHANNEL'),

];
