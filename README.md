# Laravel AI Translator / Laravel AI Çevirmen

## Overview / Genel Bakış
Laravel AI Translator is a Laravel 12 compatible package that inspects your `resources/lang` directory, detects missing keys, and fills the gaps by requesting translations from OpenAI's official PHP SDK. The package respects both PHP array and JSON translation files, ensuring feature parity with Laravel's localisation system.

Laravel AI Çevirmen, `resources/lang` dizininizi tarayan, eksik anahtarları tespit eden ve bu boşlukları OpenAI'nin resmi PHP SDK'sını kullanarak dolduran Laravel 12 uyumlu bir pakettir. Paket, hem PHP dizi dosyalarını hem de JSON çeviri dosyalarını destekleyerek Laravel'in yerelleştirme sistemiyle tam uyumluluk sağlar.

## Features / Özellikler
- **Automatic gap filling / Otomatik boşluk doldurma:** Missing keys are translated between any two locales through the `ai:translate` Artisan command. / Eksik anahtarlar `ai:translate` Artisan komutu ile iki dil arasında otomatik olarak çevrilir.
- **HTML-safe translations / HTML güvenli çeviriler:** Placeholders, HTML etiketleri ve Blade değişkenleri korunur. / Yer tutucular, HTML etiketleri ve Blade değişkenleri korunur.
- **Configurable provider / Yapılandırılabilir sağlayıcı:** Choose the OpenAI model and API key via the publishable config file. / Yayınlanabilir yapılandırma dosyası ile OpenAI modeli ve API anahtarını seçebilirsiniz.
- **Test-friendly design / Test dostu tasarım:** The translation provider is swappable, enabling reliable tests with fakes. / Sağlayıcı sınıfı değiştirilebilir, bu sayede sahte sınıflarla güvenilir testler yapılabilir.

## Requirements / Gereksinimler
- PHP 8.3 or newer / PHP 8.3 veya üzeri
- Laravel 12 project / Laravel 12 projesi
- OpenAI API credentials / OpenAI API kimlik bilgileri

## Installation / Kurulum
```bash
composer require digitalcorehub/laravel-ai-translator
```

Publish the configuration to customise the provider or model:
```bash
php artisan vendor:publish --tag=config --provider="DigitalCoreHub\\LaravelAiTranslator\\AiTranslatorServiceProvider"
```

Sağlayıcı veya modeli özelleştirmek için yapılandırma dosyasını yayınlayın:
```bash
php artisan vendor:publish --tag=config --provider="DigitalCoreHub\\LaravelAiTranslator\\AiTranslatorServiceProvider"
```

## Configuration / Yapılandırma
Add the following keys to your `.env` file:
```dotenv
OPENAI_API_KEY=your-api-key
OPENAI_MODEL=gpt-4o-mini
```

`.env` dosyanıza aşağıdaki anahtarları ekleyin:
```dotenv
OPENAI_API_KEY=api-anahtariniz
OPENAI_MODEL=gpt-4o-mini
```

The `config/ai-translator.php` file lets you switch providers or override model defaults. / `config/ai-translator.php` dosyası sağlayıcıyı değiştirmenize veya model varsayılanlarını güncellemenize olanak tanır.

## Usage / Kullanım
Run the Artisan command to backfill missing translations from English to Turkish:
```bash
php artisan ai:translate en tr
```

Change the locale arguments to match your source and destination folders under `resources/lang`. / `resources/lang` altındaki kaynak ve hedef klasörlere göre komut parametrelerini güncelleyin.

Komutu çalıştırdığınızda işlenen dosyalar ve kaç anahtarın çevrildiği özet olarak görüntülenir. / The command prints a summary of processed files and translated keys.

## Testing / Testler
Execute the Pest-powered test suite:
```bash
composer test
```

or run Pest directly:
```bash
vendor/bin/pest
```

Pest tabanlı testleri çalıştırmak için:
```bash
composer test
```

yahut doğrudan Pest komutunu kullanın:
```bash
vendor/bin/pest
```

## Contributing / Katkıda Bulunma
Pull requests are welcome. Please ensure translations remain bilingual and pass Pint formatting before submitting. / Çekme isteklerine açığız. Lütfen çevirilerin iki dilli kaldığından ve Pint format denetiminden geçtiğinden emin olun.

## Automation / Otomasyon
Dependabot pull requests enforce naming conventions, run Pint and `composer test`, and auto-merge only when minor or patch updates succeed. / Dependabot çekme istekleri isimlendirme kurallarına uyar, Pint ve `composer test` çalıştırır ve yalnızca küçük veya yama güncellemeleri başarılı olduğunda otomatik birleştirme yapar.

## License / Lisans
This package is open-sourced software licensed under the [MIT license](LICENSE). / Bu paket [MIT lisansı](LICENSE) ile lisanslanmıştır.

> This package uses **Dependabot** for automatic dependency updates  
> and **Laravel Pint** for code style consistency.  
> Maintained with ❤️ by [Digital Core Hub](https://github.com/digitalcorehub)
