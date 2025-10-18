<?php

use DigitalCoreHub\LaravelAiTranslator\Http\Controllers\ApiTranslateController;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Dashboard;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\EditTranslation;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Logs;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Settings;
use DigitalCoreHub\LaravelAiTranslator\Http\Middleware\EnsureAiTranslatorAccess;
use Illuminate\Support\Facades\Route;

$panelMiddleware = config('ai-translator.middleware', [
    'web',
    'auth',
    EnsureAiTranslatorAccess::class,
]);

Route::prefix('ai-translator')
    ->middleware($panelMiddleware)
    ->group(function () {
        Route::get('/', Dashboard::class)->name('ai-translator.dashboard');
        Route::get('/edit', EditTranslation::class)->name('ai-translator.edit');
        Route::get('/settings', Settings::class)->name('ai-translator.settings');
        Route::get('/logs', Logs::class)->name('ai-translator.logs');
    });

$apiMiddleware = config('ai-translator.api_middleware', ['api']);

if (config('ai-translator.api_auth', true)) {
    $apiMiddleware[] = 'auth:sanctum';
}

Route::middleware($apiMiddleware)
    ->post('/api/translate', ApiTranslateController::class)
    ->name('ai-translator.api.translate');
