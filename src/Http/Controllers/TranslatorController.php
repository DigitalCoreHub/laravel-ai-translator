<?php

namespace DigitalCoreHub\LaravelAiTranslator\Http\Controllers;

use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Dashboard;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\EditTranslation;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Logs;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\QueueStatus;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Settings;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\Sync as SyncComponent;
use DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator\WatchLogs;
use Illuminate\Http\Request;

/**
 * Controller for rendering Livewire components in the AI Translator panel.
 * AI Translator paneli için Livewire bileşenlerini render eden controller.
 */
class TranslatorController
{
    public function dashboard()
    {
        return app(Dashboard::class);
    }

    public function edit(Request $request)
    {
        return app(EditTranslation::class);
    }

    public function sync()
    {
        return app(SyncComponent::class);
    }

    public function queue()
    {
        return app(QueueStatus::class);
    }

    public function settings()
    {
        return app(Settings::class);
    }

    public function logs()
    {
        return app(Logs::class);
    }

    public function watchLogs()
    {
        return app(WatchLogs::class);
    }
}
