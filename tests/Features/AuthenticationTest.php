<?php

use DigitalCoreHub\LaravelAiTranslator\Auth\AiTranslatorUser;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Login;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

it('redirects guests to the login page when auth is enabled', function () {
    config()->set('ai-translator.auth_enabled', true);

    $response = $this->get('/ai-translator');

    $response->assertRedirect(route('login'));
});

it('blocks unauthorized email addresses from accessing the panel', function () {
    config()->set('ai-translator.auth_enabled', true);
    config()->set('ai-translator.authorized_emails', ['admin@digitalcorehub.com']);

    $user = AiTranslatorUser::make('other@example.com');

    $response = $this->actingAs($user)->get('/ai-translator');

    $response->assertForbidden();
});

it('logs in authorized users and records audit entries', function () {
    $filesystem = new Filesystem;
    $filesystem->deleteDirectory(storage_path('logs'));

    config()->set('ai-translator.auth_enabled', true);
    config()->set('ai-translator.authorized_emails', ['admin@digitalcorehub.com']);
    config()->set('ai-translator.login.email', 'admin@digitalcorehub.com');
    config()->set('ai-translator.login.password', 'secret123');

    session()->start();

    /** @var \DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Login $component */
    $component = app(Login::class);
    $component->mount();
    $component->email = 'admin@digitalcorehub.com';
    $component->password = 'secret123';

    $component->login();

    expect(Auth::check())->toBeTrue()
        ->and(session('ai_translator_logged_in'))->toBeTrue()
        ->and(session('ai_translator_email'))->toBe('admin@digitalcorehub.com');

    $this->get('/ai-translator')->assertOk();

    $logoutResponse = $this->post('/ai-translator/logout');
    $logoutResponse->assertRedirect(route('login'));

    expect(Auth::check())->toBeFalse()
        ->and(session('ai_translator_logged_in'))->toBeNull();

    $logPath = storage_path('logs/ai-translator.log');
    expect(file_exists($logPath))->toBeTrue();

    $contents = file_get_contents($logPath);

    expect($contents)->toContain('User admin@digitalcorehub.com logged into AI Translator panel.')
        ->and($contents)->toContain('User admin@digitalcorehub.com logged out.');
});

it('enables sanctum protection for the API when configured', function () {
    config()->set('ai-translator.api_auth', true);

    $router = app('router');
    $router->setRoutes(new RouteCollection);

    require base_path('routes/ai-translator.php');

    $route = Route::getRoutes()->getByName('ai-translator.api.translate');

    expect($route->gatherMiddleware())->toContain('auth:sanctum');
});
