<?php

namespace DigitalCoreHub\LaravelAiTranslator\Http\Controllers;

use DigitalCoreHub\LaravelAiTranslator\Support\AiTranslatorLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $email = Auth::user()->email ?? $request->session()->get('ai_translator_email');

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->forget(['ai_translator_logged_in', 'ai_translator_email', 'email']);

        if ($email) {
            AiTranslatorLogger::info(sprintf('User %s logged out.', $email));
        }

        return redirect()->route('login');
    }
}
