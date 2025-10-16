<?php

use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(config('ai-translator.api_middleware', ['api']))
    ->post('/api/translate', function (Request $request, TranslationManager $manager): JsonResponse {
        $validated = $request->validate([
            'from' => ['required', 'string'],
            'to' => ['required', 'string'],
            'text' => ['required', 'string'],
            'provider' => ['nullable', 'string'],
        ]);

        $provider = $validated['provider'] ?? null;

        $result = $manager->translateText(
            text: $validated['text'],
            from: $validated['from'],
            to: $validated['to'],
            provider: $provider
        );

        return response()->json([
            'translation' => $result['translation'],
            'provider' => $result['provider'],
            'cache_hit' => $result['cache_hit'],
            'duration' => $result['duration'],
        ]);
    })
    ->name('ai-translator.api.translate');
