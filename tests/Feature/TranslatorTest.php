<?php

declare(strict_types=1);

use DigitalCoreHub\LaravelAiTranslator\Contracts\TranslationProvider;
use DigitalCoreHub\LaravelAiTranslator\Events\KeyTranslationFailed;
use DigitalCoreHub\LaravelAiTranslator\Events\TranslationRunCompleted;
use DigitalCoreHub\LaravelAiTranslator\Facades\AiTranslator;
use DigitalCoreHub\LaravelAiTranslator\Providers\ProviderChain;
use DigitalCoreHub\LaravelAiTranslator\Providers\ProviderRegistry;
use DigitalCoreHub\LaravelAiTranslator\Support\ReportStore;
use DigitalCoreHub\LaravelAiTranslator\Tests\Fakes\FakeProvider;
use DigitalCoreHub\LaravelAiTranslator\Translation\Translator;
use Illuminate\Support\Facades\Event;

/**
 * Zincire elle bir sahte sağlayıcı yerleştirir.
 */
function useProvider(TranslationProvider $provider): void
{
    config()->set('ai-translator.provider', 'sahte');
    config()->set('ai-translator.fallback', []);

    app()->forgetInstance(ProviderRegistry::class);
    app()->forgetInstance(ProviderChain::class);
    app()->forgetInstance(Translator::class);

    app()->instance(ProviderRegistry::class, new class($provider) extends ProviderRegistry
    {
        public function __construct(protected TranslationProvider $provider)
        {
            parent::__construct(app(), []);
        }

        public function names(): array
        {
            return ['sahte'];
        }

        public function has(string $name): bool
        {
            return $name === 'sahte';
        }

        public function get(string $name): TranslationProvider
        {
            return $this->provider;
        }
    });
}

it('facade akıcı arayüzle çalışır', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);

    $run = AiTranslator::from('en')->to('tr')->translate();

    expect($run->translatedCount())->toBe(1)
        ->and($run->from)->toBe('en')
        ->and($run->to)->toBe('tr');
});

it('facade ayarları paylaşılan örneğe sızdırmaz', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);

    AiTranslator::from('en')->to('de')->force();

    // Yukarıdaki zincir yeni bir kopya döndürmeli; aşağıdaki koşu etkilenmemeli.
    $run = AiTranslator::from('en')->to('tr')->translate();

    expect($run->to)->toBe('tr')
        ->and($run->force)->toBeFalse();
});

it('eksikleri çevirmeden listeler', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed', 'password' => 'Wrong']);
    $this->writeLangFile('tr/auth.php', ['failed' => 'Hatalı']);

    $missing = AiTranslator::from('en')->to('tr')->missing();

    expect($missing)->toHaveCount(1)
        ->and($missing[0]->key)->toBe('password')
        ->and($missing[0]->file)->toBe('tr/auth.php')
        ->and($missing[0]->status())->toBe('missing');
});

/**
 * v0.x burada sessizce ÇEVRİLMEMİŞ İngilizce metni Türkçe dosyaya yazıyordu.
 */
it('yer tutucu kaybolan çeviriyi dosyaya yazmaz ve hata olarak raporlar', function () {
    Event::fake([KeyTranslationFailed::class]);

    // Sağlayıcı ⟦0⟧ belirtecini yutuyor.
    useProvider(new FakeProvider('sahte', ['k0' => 'Merhaba']));

    $this->writeLangFile('en/auth.php', ['greeting' => 'Hello :name']);

    $run = AiTranslator::from('en')->to('tr')->translate();

    expect($run->failedCount())->toBe(1)
        ->and($run->translatedCount())->toBe(0)
        ->and($run->allFailed()[0]->reason)->toContain('yer tutucular kayboldu')
        ->and($this->readLangFile('tr/auth.php'))->toBe([]);

    Event::assertDispatched(KeyTranslationFailed::class);
});

it('yer tutucu korunmuşsa normal şekilde yazar', function () {
    useProvider(new FakeProvider('sahte', ['k0' => 'Merhaba ⟦0⟧']));

    $this->writeLangFile('en/auth.php', ['greeting' => 'Hello :name']);

    AiTranslator::from('en')->to('tr')->translate();

    expect($this->readLangFile('tr/auth.php')['greeting'])->toBe('Merhaba :name');
});

it('çevrilemeyen anahtarı hata olarak işaretler', function () {
    useProvider(new FakeProvider('sahte', []));

    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);

    $run = AiTranslator::from('en')->to('tr')->translate();

    expect($run->failedCount())->toBe(1)
        ->and($run->hasFailures())->toBeTrue();
});

it('koşu tamamlandığında event yayınlar', function () {
    Event::fake([TranslationRunCompleted::class]);

    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);

    AiTranslator::from('en')->to('tr')->translate();

    Event::assertDispatched(
        TranslationRunCompleted::class,
        fn (TranslationRunCompleted $e) => $e->run->translatedCount() === 1
    );
});

it('önbellek açıkken ikinci koşuda sağlayıcıya gitmez', function () {
    config()->set('ai-translator.cache.enabled', true);

    $provider = new FakeProvider('sahte', ['k0' => 'Hatalı']);
    useProvider($provider);

    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);

    AiTranslator::from('en')->to('tr')->force()->translate();
    $second = AiTranslator::from('en')->to('tr')->force()->translate();

    expect($provider->calls)->toBe(1)
        ->and($second->cacheHits())->toBe(1);
});

it('koşuyu rapora yazar', function () {
    $reportPath = $this->langPath.'/../rapor.json';
    config()->set('ai-translator.report.path', $reportPath);
    app()->forgetInstance(ReportStore::class);

    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);

    $this->artisan('ai:translate en tr')->assertSuccessful();

    $report = app(ReportStore::class)->all();

    expect($report)->toHaveCount(1)
        ->and($report[0]['to'])->toBe('tr')
        ->and($report[0]['totals']['translated'])->toBe(1);

    @unlink($reportPath);
});

it('rapor dosyasını keep sınırında tutar', function () {
    $reportPath = $this->langPath.'/../rapor.json';
    config()->set('ai-translator.report.path', $reportPath);
    config()->set('ai-translator.report.keep', 2);
    app()->forgetInstance(ReportStore::class);

    $store = app(ReportStore::class);
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);

    foreach (range(1, 5) as $ignored) {
        $store->record(AiTranslator::from('en')->to('tr')->dryRun()->translate());
    }

    expect($store->all())->toHaveCount(2);

    @unlink($reportPath);
});
