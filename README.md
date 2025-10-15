# Laravel AI Translator / Laravel AI Çevirmen

Laravel AI Translator is a Laravel 12 compatible package that scans your application's language files, detects missing keys, and automatically generates translations by calling OpenAI's API.

Laravel AI Translator, Laravel 12 ile uyumlu bir pakettir; uygulamanızın dil dosyalarını tarar, eksik anahtarları tespit eder ve OpenAI API'sini kullanarak bu çevirileri otomatik olarak tamamlar.

## Installation / Kurulum

```bash
composer require digitalcorehub/laravel-ai-translator
```

Publish the configuration file to tweak the provider or model:

```bash
php artisan vendor:publish --tag=config --provider="DigitalCoreHub\\LaravelAiTranslator\\AiTranslatorServiceProvider"
```

Update your `.env` file with the OpenAI credentials:

```
OPENAI_API_KEY=your-api-key
OPENAI_MODEL=gpt-4o-mini
```

---

Kurulumdan sonra yapılandırma dosyasını yayımlayarak sağlayıcı veya modeli özelleştirebilirsiniz:

```bash
php artisan vendor:publish --tag=config --provider="DigitalCoreHub\\LaravelAiTranslator\\AiTranslatorServiceProvider"
```

`.env` dosyanızı OpenAI kimlik bilgileriyle güncelleyin:

```
OPENAI_API_KEY=your-api-key
OPENAI_MODEL=gpt-4o-mini
```

## Usage / Kullanım

Run the artisan command to translate missing keys from one locale to another:

```bash
php artisan ai:translate en tr
```

The command will list every processed file and display how many keys were translated.

Eksik anahtarları bir dilden diğerine çevirmek için artisan komutunu çalıştırın. Komut, işlenen her dosyayı ve kaç anahtarın çevrildiğini ekrana yazdıracaktır.

## Testing / Testler

Run the package test suite with Pest:

```bash
vendor/bin/pest
```

Paketi Pest ile test etmek için yukarıdaki komutu kullanabilirsiniz.

> This package uses **Dependabot** for automatic dependency updates  
> and **Laravel Pint** for code style consistency.  
> Maintained with ❤️ by [Digital Core Hub](https://github.com/digitalcorehub)
