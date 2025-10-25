<?php

use DigitalCoreHub\LaravelAiTranslator\Http\Controllers\ApiTranslateController;
use DigitalCoreHub\LaravelAiTranslator\Http\Controllers\TranslatorController;
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
        Route::get('/', [TranslatorController::class, 'dashboard'])->name('ai-translator.dashboard');
        Route::get('/edit', [TranslatorController::class, 'edit'])->name('ai-translator.edit');
        Route::get('/sync', [TranslatorController::class, 'sync'])->name('ai-translator.sync');
        Route::get('/queue', [TranslatorController::class, 'queue'])->name('ai-translator.queue');
        Route::get('/settings', [TranslatorController::class, 'settings'])->name('ai-translator.settings');
        Route::get('/logs', [TranslatorController::class, 'logs'])->name('ai-translator.logs');
        Route::get('/watch-logs', [TranslatorController::class, 'watchLogs'])->name('ai-translator.watch');
    });

$apiMiddleware = config('ai-translator.api_middleware', ['api']);

if (config('ai-translator.api_auth', true)) {
    $apiMiddleware[] = 'auth:sanctum';
}

Route::middleware($apiMiddleware)
    ->post('/api/translate', ApiTranslateController::class)
    ->name('ai-translator.api.translate');
