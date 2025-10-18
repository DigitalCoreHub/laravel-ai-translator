<?php

namespace DigitalCoreHub\LaravelAiTranslator\Http\Middleware;

use Closure;
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
        if (! config('ai-translator.auth_enabled', false)) {
            return $next($request);
        }

        if (! Auth::check()) {
            if ($request->expectsJson()) {
                abort(401, 'Authentication required.');
            }

            return redirect()->route('login');
        }

        $email = strtolower((string) (Auth::user()->email ?? ''));
        $authorized = array_map('strtolower', Arr::wrap(config('ai-translator.authorized_emails', [])));

        if ($authorized !== [] && ! in_array($email, $authorized, true)) {
            abort(403, 'Unauthorized access to AI Translator panel');
        }

        return $next($request);
    }
}
