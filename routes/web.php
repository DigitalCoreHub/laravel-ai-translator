<?php

use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Dashboard;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\EditTranslation;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Logs;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Settings;
use Illuminate\Support\Facades\Route;

$middleware = config('ai-translator.web_middleware', ['web']);
$prefix = trim((string) (config('ai-translator.web_prefix') ?? ''), '/');

Route::middleware($middleware)->group(function () use ($prefix): void {
    $routes = function (): void {
        Route::get('/ai-translator', Dashboard::class)->name('ai-translator.dashboard');
        Route::get('/ai-translator/settings', Settings::class)->name('ai-translator.settings');
        Route::get('/ai-translator/logs', Logs::class)->name('ai-translator.logs');
        Route::get('/ai-translator/edit/{from}/{to}/{encoded}', EditTranslation::class)
            ->where('encoded', '.*')
            ->name('ai-translator.edit');
    };

    if ($prefix !== '') {
        Route::prefix($prefix)->group($routes);
    } else {
        Route::group([], $routes);
    }
});
