<?php

use DigitalCoreHub\LaravelAiTranslator\Http\Controllers\ApiTranslateController;
use DigitalCoreHub\LaravelAiTranslator\Http\Controllers\LogoutController;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Dashboard;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\EditTranslation;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Login;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Logs;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Settings;
use DigitalCoreHub\LaravelAiTranslator\Http\Middleware\EnsureAiTranslatorAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->get('/ai-translator/login', Login::class)->name('login');

$panelMiddleware = config('ai-translator.middleware', ['web']);

if (! config('ai-translator.auth_enabled', false)) {
    $panelMiddleware = array_values(array_filter($panelMiddleware, function ($middleware) {
        return ! in_array($middleware, ['auth', EnsureAiTranslatorAccess::class], true);
    }));
}

Route::prefix('ai-translator')->group(function () use ($panelMiddleware) {
    Route::middleware($panelMiddleware)->group(function () {
        Route::get('/', Dashboard::class)->name('ai-translator.dashboard');
        Route::get('/edit', EditTranslation::class)->name('ai-translator.edit');
        Route::get('/settings', Settings::class)->name('ai-translator.settings');
        Route::get('/logs', Logs::class)->name('ai-translator.logs');
    });

    Route::post('/logout', LogoutController::class)
        ->middleware(['web', 'auth'])
        ->name('ai-translator.logout');
});

$apiMiddleware = config('ai-translator.api_middleware', ['api']);

if (config('ai-translator.api_auth', false)) {
    $apiMiddleware[] = 'auth:sanctum';
}

Route::middleware($apiMiddleware)
    ->post('/api/translate', ApiTranslateController::class)
    ->name('ai-translator.api.translate');
