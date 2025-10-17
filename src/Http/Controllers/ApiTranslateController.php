<?php

namespace DigitalCoreHub\LaravelAiTranslator\Http\Controllers;

use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ApiTranslateController extends Controller
{
    public function __invoke(Request $request, TranslationManager $manager): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'string'],
            'to' => ['required', 'string'],
            'text' => ['required', 'string'],
            'provider' => ['nullable', 'string'],
        ]);

        $provider = $validated['provider'] ?? null;

        if ($provider && ! in_array($provider, $manager->availableProviders(), true)) {
            return response()->json([
                'message' => 'Invalid translation provider supplied.',
            ], 422);
        }

        $result = $manager->translateText(
            text: $validated['text'],
            from: $validated['from'],
            to: $validated['to'],
            provider: $provider
        );

        return response()->json([
            'from' => $validated['from'],
            'to' => $validated['to'],
            'provider' => $result['provider'],
            'translation' => $result['translation'],
            'cache_hit' => $result['cache_hit'],
            'duration_ms' => round($result['duration'] * 1000, 2),
        ]);
    }
}
