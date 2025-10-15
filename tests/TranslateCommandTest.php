<?php

use Codex\LaravelAiTranslator\Tests\Fakes\FakeProvider;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->filesystem = new Filesystem();
    $this->langPath = base_path('resources/lang');

    $this->filesystem->deleteDirectory($this->langPath);
    $this->filesystem->makeDirectory($this->langPath, 0755, true);

    $this->app->instance(FakeProvider::class, new FakeProvider());
});

it('translates missing keys across php and json files', function () {
    seedLanguageFiles();

    $this->artisan('ai:translate', ['from' => 'en', 'to' => 'tr'])
        ->expectsOutput('Scanning language directories: en -> tr')
        ->expectsTable(
            ['File', 'Missing', 'Translated'],
            [
                ['messages.php', 1, 1],
                ['en.json', 1, 1],
            ]
        )
        ->expectsOutput('Total missing: 2 | Translated: 2')
        ->assertSuccessful();

    $provider = $this->app->make(FakeProvider::class);

    expect($provider->calls)
        ->toHaveCount(2)
        ->sequence(
            fn ($call) => $call->text->toBe('__AI_HTML_0__Bold__AI_HTML_1__ text')->from->toBe('en')->to->toBe('tr'),
            fn ($call) => $call->text->toBe('Login')->from->toBe('en')->to->toBe('tr')
        );

    $turkishMessages = require base_path('resources/lang/tr/messages.php');
    $turkishJson = json_decode(file_get_contents(base_path('resources/lang/tr.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($turkishMessages['greeting'])->toBe('Merhaba')
        ->and($turkishMessages['nested']['html'])->toBe('[en->tr] <strong>Bold</strong> text');

    expect($turkishJson['Login'])->toBe('[en->tr] Login');
});

function seedLanguageFiles(): void
{
    $filesystem = new Filesystem();
    $filesystem->makeDirectory(base_path('resources/lang/en'), 0755, true, true);
    $filesystem->makeDirectory(base_path('resources/lang/tr'), 0755, true, true);

    file_put_contents(base_path('resources/lang/en/messages.php'), <<<'PHP'
<?php

return [
    'greeting' => 'Hello',
    'nested' => [
        'html' => '<strong>Bold</strong> text',
    ],
];
PHP);

    file_put_contents(base_path('resources/lang/tr/messages.php'), <<<'PHP'
<?php

return [
    'greeting' => 'Merhaba',
];
PHP);

    file_put_contents(base_path('resources/lang/en.json'), json_encode([
        'Login' => 'Login',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    file_put_contents(base_path('resources/lang/tr.json'), json_encode([
        'Welcome' => 'Hoş geldiniz',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
