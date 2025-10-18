<?php

namespace DigitalCoreHub\LaravelAiTranslator\Http\Middleware;

use Closure;
use DigitalCoreHub\LaravelAiTranslator\Support\AiTranslatorLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class EnsureAiTranslatorAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            AiTranslatorLogger::info('Guest attempted unauthorized access to AI Translator panel.');

            if ($request->expectsJson()) {
                abort(401, 'Authentication required for AI Translator panel.');
            }

            return redirect()->route('login');
        }

        $user = Auth::user();
        $email = strtolower((string) ($user->email ?? ''));

        if (config('ai-translator.auth_enabled', true)) {
            $allowed = array_filter(array_map(
                'strtolower',
                Arr::wrap(config('ai-translator.authorized_emails', []))
            ));

            if ($allowed !== [] && ($email === '' || ! in_array($email, $allowed, true))) {
                $display = $user->email ?? 'unknown';
                AiTranslatorLogger::info(sprintf('User %s attempted unauthorized access to AI Translator panel.', $display));

                abort(403, 'Unauthorized access to AI Translator panel');
            }
        }

        return tap($next($request), function () use ($user) {
            $display = $user->email ?? 'unknown';
            AiTranslatorLogger::info(sprintf('User %s accessed AI Translator panel.', $display));
        });
    }
}
