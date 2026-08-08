<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('eksik anahtarları raporlar ve varsayılan olarak başarıyla çıkar', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed', 'password' => 'Wrong']);
    $this->writeLangFile('tr/auth.php', ['failed' => 'Hatalı']);

    $this->artisan('ai:translate:status en tr')
        ->expectsOutputToContain('Toplam 1 eksik anahtar')
        ->assertSuccessful();
});

it('eksik yoksa temiz rapor verir', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);
    $this->writeLangFile('tr/auth.php', ['failed' => 'Hatalı']);

    $this->artisan('ai:translate:status en tr')
        ->expectsOutputToContain('Eksik çeviri yok')
        ->assertSuccessful();
});

/**
 * CI kapısı: eksik çeviriyle merge edilmesin.
 */
it('fail-on-missing ile eksik varsa 1 döner', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);
    $this->writeLangFile('tr/auth.php', []);

    $this->artisan('ai:translate:status en tr --fail-on-missing')->assertExitCode(1);
});

it('fail-on-missing ile eksik yoksa 0 döner', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);
    $this->writeLangFile('tr/auth.php', ['failed' => 'Hatalı']);

    $this->artisan('ai:translate:status en tr --fail-on-missing')->assertExitCode(0);
});

it('json çıktısı makine tarafından okunabilir', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed', 'password' => 'Wrong']);
    $this->writeLangFile('tr/auth.php', ['failed' => 'Hatalı']);

    $exitCode = Artisan::call('ai:translate:status en tr --json');

    expect($exitCode)->toBe(0);

    $output = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    expect($output['from'])->toBe('en')
        ->and($output['locales']['tr']['total'])->toBe(2)
        ->and($output['locales']['tr']['missing'])->toBe(1)
        ->and($output['locales']['tr']['keys'][0]['key'])->toBe('password');
});

it('hiçbir şey çevirmez, dosya oluşturmaz', function () {
    $this->writeLangFile('en/auth.php', ['failed' => 'Failed']);

    $this->artisan('ai:translate:status en tr')->assertSuccessful();

    expect(file_exists($this->langPath.'/tr/auth.php'))->toBeFalse();
});
