<?php

namespace DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator;

use Livewire\Volt\Component;

class Logs extends Component
{
    public array $entries = [];

    public function mount(): void
    {
        $filesystem = app('files');
        $reportPath = storage_path('logs/ai-translator-report.json');

        if (! $filesystem->exists($reportPath)) {
            $this->entries = [];

            return;
        }

        $payload = json_decode($filesystem->get($reportPath), true);

        if (! is_array($payload)) {
            $this->entries = [];

            return;
        }

        $entries = [];

        foreach ($payload as $run) {
            $files = $run['files'] ?? [];

            foreach ($files as $file) {
                $entries[] = [
                    'executed_at' => $run['executed_at'] ?? null,
                    'from' => $run['from'] ?? '',
                    'to' => $run['to'] ?? '',
                    'provider' => $run['provider'] ?? ($file['primary_provider'] ?? ''),
                    'file' => $file['file'] ?? $file['name'] ?? '',
                    'translated' => $file['translated'] ?? 0,
                    'missing' => $file['missing'] ?? 0,
                    'duration' => $file['duration_ms'] ?? null,
                ];
            }
        }

        $this->entries = $entries;
    }

    public function render(): mixed
    {
        return view('ai-translator::livewire.translator.logs')
            ->layout('ai-translator::layouts.app');
    }
}
