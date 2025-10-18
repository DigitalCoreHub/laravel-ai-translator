<?php

namespace DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator;

use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use Livewire\Volt\Component;
use Throwable;

class Settings extends Component
{
    public array $providers = [];

    public array $status = [];

    public string $currentProvider = '';

    public string $from = 'en';

    public string $to = 'tr';

    public array $locales = [];

    public function mount(): void
    {
        $this->providers = config('ai-translator.providers', []);
        $this->currentProvider = config('ai-translator.provider', 'openai');

        $manager = $this->manager();
        $this->locales = $manager->availableLocales();

        if ($this->locales !== []) {
            if (! in_array($this->from, $this->locales, true)) {
                $this->from = $this->locales[0];
            }

            if (! in_array($this->to, $this->locales, true)) {
                $this->to = $this->locales[1] ?? $this->from;
            }
        } else {
            $this->locales = array_values(array_unique([$this->from, $this->to]));
        }
    }

    public function render(): mixed
    {
        return view('ai-translator::livewire.translator.settings')
            ->layout('ai-translator::vendor.ai-translator.layouts.app');
    }

    public function testConnection(string $provider): void
    {
        $message = null;

        try {
            $ok = $this->manager()->testProvider($provider, $this->from, $this->to);
        } catch (Throwable $exception) {
            $ok = false;
            $message = $exception->getMessage();
        }

        $this->status[$provider] = [
            'ok' => $ok,
            'message' => $ok ? '✅ Connection OK' : ($message ?? 'Bağlantı testi başarısız oldu.'),
        ];
    }

    protected function manager(): TranslationManager
    {
        return app(TranslationManager::class);
    }
}
