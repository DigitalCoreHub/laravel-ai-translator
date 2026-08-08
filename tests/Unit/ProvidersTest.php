<?php

declare(strict_types=1);

use DigitalCoreHub\LaravelAiTranslator\Exceptions\ProviderException;
use DigitalCoreHub\LaravelAiTranslator\Providers\DeepLProvider;
use DigitalCoreHub\LaravelAiTranslator\Providers\DeepSeekProvider;
use DigitalCoreHub\LaravelAiTranslator\Providers\GoogleProvider;
use DigitalCoreHub\LaravelAiTranslator\Providers\NullProvider;
use DigitalCoreHub\LaravelAiTranslator\Providers\OpenAIProvider;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;

function chatResponse(array $translations): array
{
    return [
        'choices' => [
            ['message' => ['content' => json_encode(['translations' => $translations])]],
        ],
    ];
}

function httpFactory(): HttpFactory
{
    return app(HttpFactory::class);
}

beforeEach(fn () => Http::preventStrayRequests());

describe('openai', function () {
    it('tek istekte bütün grubu gönderir ve haritayı çözer', function () {
        Http::fake(['api.openai.com/*' => Http::response(chatResponse([
            'k0' => 'Kaydet',
            'k1' => 'İptal',
        ]))]);

        $provider = new OpenAIProvider(httpFactory(), ['api_key' => 'sk-test', 'model' => 'gpt-4o-mini']);

        $result = $provider->translateBatch(['k0' => 'Save', 'k1' => 'Cancel'], 'en', 'tr');

        expect($result)->toBe(['k0' => 'Kaydet', 'k1' => 'İptal']);

        Http::assertSentCount(1);
    });

    it('json_schema ile structured output ister', function () {
        Http::fake(['api.openai.com/*' => Http::response(chatResponse(['k0' => 'Kaydet']))]);

        (new OpenAIProvider(httpFactory(), ['api_key' => 'sk-test']))
            ->translateBatch(['k0' => 'Save'], 'en', 'tr');

        Http::assertSent(fn ($request) => $request['response_format']['type'] === 'json_schema');
    });

    it('modelin uydurduğu anahtarları atar', function () {
        Http::fake(['api.openai.com/*' => Http::response(chatResponse([
            'k0' => 'Kaydet',
            'uydurma' => 'Bir şey',
        ]))]);

        $result = (new OpenAIProvider(httpFactory(), ['api_key' => 'sk-test']))
            ->translateBatch(['k0' => 'Save'], 'en', 'tr');

        expect($result)->toBe(['k0' => 'Kaydet']);
    });

    it('markdown çitiyle sarılmış cevabı da çözer', function () {
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => "```json\n{\"k0\":\"Kaydet\"}\n```"]]],
        ])]);

        $result = (new OpenAIProvider(httpFactory(), ['api_key' => 'sk-test']))
            ->translateBatch(['k0' => 'Save'], 'en', 'tr');

        expect($result)->toBe(['k0' => 'Kaydet']);
    });

    it('api anahtarı yoksa yapılandırılmamış sayılır', function () {
        expect((new OpenAIProvider(httpFactory(), []))->isConfigured())->toBeFalse()
            ->and((new OpenAIProvider(httpFactory(), ['api_key' => ' ']))->isConfigured())->toBeFalse()
            ->and((new OpenAIProvider(httpFactory(), ['api_key' => 'sk-x']))->isConfigured())->toBeTrue();
    });

    it('hata durumunda sağlayıcı adıyla istisna fırlatır', function () {
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'nope'], 401)]);

        (new OpenAIProvider(httpFactory(), ['api_key' => 'sk-test'], ['times' => 1, 'sleep' => 0]))
            ->translateBatch(['k0' => 'Save'], 'en', 'tr');
    })->throws(ProviderException::class, '[openai]');
});

describe('deepseek', function () {
    it('kendi uç noktasını kullanır ve json_object modunda çalışır', function () {
        Http::fake(['api.deepseek.com/*' => Http::response(chatResponse(['k0' => 'Kaydet']))]);

        $result = (new DeepSeekProvider(httpFactory(), ['api_key' => 'ds-test']))
            ->translateBatch(['k0' => 'Save'], 'en', 'tr');

        expect($result)->toBe(['k0' => 'Kaydet']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.deepseek.com')
            && $request['response_format']['type'] === 'json_object');
    });
});

describe('deepl', function () {
    it('tek istekte text dizisi gönderir ve sırayı korur', function () {
        Http::fake(['*deepl.com/*' => Http::response([
            'translations' => [['text' => 'Kaydet'], ['text' => 'İptal']],
        ])]);

        $result = (new DeepLProvider(httpFactory(), ['api_key' => 'key:fx']))
            ->translateBatch(['k0' => 'Save', 'k1' => 'Cancel'], 'en', 'tr');

        expect($result)->toBe(['k0' => 'Kaydet', 'k1' => 'İptal']);

        Http::assertSentCount(1);
    });

    /**
     * v0.x her zaman api-free'ye gidiyordu; ücretli anahtarlar hiç çalışmıyordu.
     */
    it('ücretsiz ve ücretli anahtarı doğru uç noktaya yollar', function (string $key, string $host) {
        Http::fake(['*' => Http::response(['translations' => [['text' => 'Kaydet']]])]);

        (new DeepLProvider(httpFactory(), ['api_key' => $key]))
            ->translateBatch(['k0' => 'Save'], 'en', 'tr');

        Http::assertSent(fn ($request) => str_contains($request->url(), $host));
    })->with([
        'ücretsiz' => ['abc:fx', 'api-free.deepl.com'],
        'ücretli' => ['abc', 'api.deepl.com'],
    ]);

    it('bölge kodu gereken dilleri normalize eder', function () {
        Http::fake(['*' => Http::response(['translations' => [['text' => 'Save']]])]);

        (new DeepLProvider(httpFactory(), ['api_key' => 'k:fx']))
            ->translateBatch(['k0' => 'Kaydet'], 'tr', 'en');

        Http::assertSent(fn ($request) => $request['target_lang'] === 'EN-GB');
    });

    it('eksik sayıda çeviri dönerse istisna fırlatır', function () {
        Http::fake(['*' => Http::response(['translations' => [['text' => 'Kaydet']]])]);

        (new DeepLProvider(httpFactory(), ['api_key' => 'k:fx']))
            ->translateBatch(['k0' => 'Save', 'k1' => 'Cancel'], 'en', 'tr');
    })->throws(ProviderException::class, '2 çeviri beklenirken 1 geldi');
});

describe('google', function () {
    it('q dizisiyle toplu istek atar', function () {
        Http::fake(['*googleapis.com/*' => Http::response([
            'data' => ['translations' => [['translatedText' => 'Kaydet'], ['translatedText' => 'İptal']]],
        ])]);

        $result = (new GoogleProvider(httpFactory(), ['api_key' => 'g-key']))
            ->translateBatch(['k0' => 'Save', 'k1' => 'Cancel'], 'en', 'tr');

        expect($result)->toBe(['k0' => 'Kaydet', 'k1' => 'İptal']);

        Http::assertSentCount(1);
    });

    it('html entity kaçışlarını geri çözer', function () {
        Http::fake(['*googleapis.com/*' => Http::response([
            'data' => ['translations' => [['translatedText' => 'Bu &#39;bir&#39; test']]],
        ])]);

        $result = (new GoogleProvider(httpFactory(), ['api_key' => 'g-key']))
            ->translateBatch(['k0' => "It's a test"], 'en', 'tr');

        expect($result['k0'])->toBe("Bu 'bir' test");
    });
});

describe('null', function () {
    it('ağa çıkmadan metni geri verir', function () {
        $result = (new NullProvider(httpFactory(), ['prefix' => '[tr] ']))
            ->translateBatch(['k0' => 'Save'], 'en', 'tr');

        expect($result)->toBe(['k0' => '[tr] Save'])
            ->and((new NullProvider(httpFactory()))->isConfigured())->toBeTrue();
    });
});
