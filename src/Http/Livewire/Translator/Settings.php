<?php

namespace DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator;

use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Livewire\Component;

class Settings extends Component
{
    public string $activeProvider;

    public array $providers = [];

    public array $results = [];

    protected TranslationManager $manager;

    public function mount(TranslationManager $manager): void
    {
        $this->manager = $manager;
        $this->activeProvider = (string) config('ai-translator.provider', 'openai');
        $this->providers = config('ai-translator.providers', []);
    }

    public function test(string $provider): void
    {
        $result = $this->manager->testProvider($provider);
        $this->results[$provider] = $result;

        $this->logAction('Sağlayıcı bağlantısı test edildi', [
            'provider' => $provider,
            'ok' => $result['ok'],
        ]);
    }

    protected function logAction(string $message, array $context = []): void
    {
        $path = storage_path('logs/ai-translator.log');
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $timestamp = Carbon::now()->format('Y-m-d H:i:s');
        $payload = $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        File::append($path, "[{$timestamp}] {$message} {$payload}".PHP_EOL);
    }

    public function render()
    {
        return view('ai-translator::livewire.translator.settings', [
            'providerConfig' => $this->providers,
        ])->layout('ai-translator::livewire.translator.layout');
    }
}
