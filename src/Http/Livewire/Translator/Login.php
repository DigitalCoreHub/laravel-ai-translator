<?php

namespace DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator;

use DigitalCoreHub\LaravelAiTranslator\Auth\AiTranslatorUser;
use DigitalCoreHub\LaravelAiTranslator\Support\AiTranslatorLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        if (! config('ai-translator.auth_enabled', false)) {
            $this->redirect(route('ai-translator.dashboard'));
        }

        if (Auth::check()) {
            $this->redirect(route('ai-translator.dashboard'));
        }

        $this->email = config('ai-translator.login.email', '');
    }

    public function render(): mixed
    {
        return view('ai-translator::livewire.translator.login')
            ->layout('ai-translator::vendor.ai-translator.layouts.guest');
    }

    public function login(): mixed
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $configuredEmail = (string) config('ai-translator.login.email');
        $configuredPassword = (string) config('ai-translator.login.password');

        $email = trim($this->email);

        if ($email !== $configuredEmail || $this->password !== $configuredPassword) {
            $this->errorMessage = 'Geçersiz giriş bilgileri.';
            $this->password = '';

            throw ValidationException::withMessages([
                'email' => [$this->errorMessage],
            ]);
        }

        $authorized = array_map('strtolower', Arr::wrap(config('ai-translator.authorized_emails', [])));

        if ($authorized !== [] && ! in_array(strtolower($email), $authorized, true)) {
            $this->errorMessage = 'Bu e-posta AI Translator paneli için yetkili değil.';
            $this->password = '';

            throw ValidationException::withMessages([
                'email' => [$this->errorMessage],
            ]);
        }

        $user = AiTranslatorUser::make($email);

        Auth::login($user);

        Session::regenerate();
        Session::put('ai_translator_logged_in', true);
        Session::put('ai_translator_email', $email);
        Session::put('email', $email);

        AiTranslatorLogger::info(sprintf('User %s logged into AI Translator panel.', $email));

        return redirect()->intended(route('ai-translator.dashboard'));
    }
}
