<?php

use DigitalCoreHub\LaravelAiTranslator\Http\Controllers\ApiTranslateController;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Dashboard;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\EditTranslation;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Logs;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Settings;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'ai-translator',
    'middleware' => config('ai-translator.middleware', ['web']),
], function () {
    Route::get('/', Dashboard::class)->name('ai-translator.dashboard');
    Route::get('/edit', EditTranslation::class)->name('ai-translator.edit');
    Route::get('/settings', Settings::class)->name('ai-translator.settings');
    Route::get('/logs', Logs::class)->name('ai-translator.logs');
});

Route::middleware(config('ai-translator.api_middleware', ['api']))
    ->post('/api/translate', ApiTranslateController::class)
    ->name('ai-translator.api.translate');
