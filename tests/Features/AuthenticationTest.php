<?php

use DigitalCoreHub\LaravelAiTranslator\Tests\Stubs\User;
use Illuminate\Auth\Events\Login as AuthLoginEvent;
use Illuminate\Auth\Events\Logout as AuthLogoutEvent;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

it('redirects guests to the login page when auth is enabled', function () {
    config()->set('ai-translator.auth_enabled', true);

    $response = $this->get('/ai-translator');

    $response->assertRedirect(route('login'));
});

it('blocks unauthorized email addresses from accessing the panel and logs the attempt', function () {
    $filesystem = new Filesystem;
    $filesystem->delete(storage_path('logs/ai-translator.log'));

    config()->set('ai-translator.auth_enabled', true);
    config()->set('ai-translator.authorized_emails', ['admin@digitalcorehub.com']);

    $user = User::query()->create([
        'name' => 'Guest User',
        'email' => 'other@example.com',
        'password' => Hash::make('secret'),
    ]);

    $response = $this->actingAs($user)->get('/ai-translator');

    $response->assertForbidden();

    $logPath = storage_path('logs/ai-translator.log');
    expect(file_exists($logPath))->toBeTrue();

    $contents = file_get_contents($logPath);

    expect($contents)->toContain('INFO: User other@example.com attempted unauthorized access to AI Translator panel.');
});

it('allows authorized users to access the panel and records audit entries', function () {
    $filesystem = new Filesystem;
    $filesystem->delete(storage_path('logs/ai-translator.log'));

    config()->set('ai-translator.auth_enabled', true);
    config()->set('ai-translator.authorized_emails', ['admin@digitalcorehub.com']);

    $user = User::query()->create([
        'name' => 'Admin User',
        'email' => 'admin@digitalcorehub.com',
        'password' => Hash::make('secret'),
    ]);

    event(new AuthLoginEvent('web', $user, false));

    $this->actingAs($user)->get('/ai-translator')->assertOk();

    event(new AuthLogoutEvent('web', $user));

    $logPath = storage_path('logs/ai-translator.log');
    expect(file_exists($logPath))->toBeTrue();

    $contents = file_get_contents($logPath);

    expect($contents)->toContain('INFO: User admin@digitalcorehub.com logged in.')
        ->and($contents)->toContain('INFO: User admin@digitalcorehub.com accessed AI Translator panel.')
        ->and($contents)->toContain('INFO: User admin@digitalcorehub.com logged out.');
});

it('enables sanctum protection for the API when configured', function () {
    config()->set('ai-translator.api_auth', true);

    $router = app('router');
    $router->setRoutes(new RouteCollection);

    require base_path('routes/ai-translator.php');

    $route = Route::getRoutes()->getByName('ai-translator.api.translate');

    expect($route->gatherMiddleware())->toContain('auth:sanctum');
});
