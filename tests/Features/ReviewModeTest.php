<?php

use DigitalCoreHub\LaravelAiTranslator\Tests\Fakes\FakeProvider;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $filesystem = new Filesystem;
    $filesystem->deleteDirectory(base_path('lang'));
    $filesystem->makeDirectory(base_path('lang/en'), 0755, true, true);

    file_put_contents(base_path('lang/en/messages.php'), <<<'PHP'
<?php

return [
    'welcome' => 'Welcome',
];
PHP);

    app()->instance(FakeProvider::class, new FakeProvider);
    app()->forgetInstance('ai-translator.providers');
    app()->forgetInstance(DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager::class);
});

it('previews translations in review mode without writing files', function () {
    $filesystem = new Filesystem;

    $this->artisan('ai:translate', ['from' => 'en', 'to' => ['tr'], '--review' => true])
        ->expectsOutput('en -> tr çeviri işlemi başlatılıyor…')
        ->expectsOutput('  lang/tr/messages.php:')
        ->expectsOutput('    Welcome → [en->tr] Welcome → openai')
        ->expectsOutput('Review mode: Çeviriler dosyalara yazılmadı.')
        ->expectsOutput('Total missing: 1 | Translated: 1')
        ->assertSuccessful();

    expect($filesystem->exists(base_path('lang/tr/messages.php')))->toBeFalse();
});
