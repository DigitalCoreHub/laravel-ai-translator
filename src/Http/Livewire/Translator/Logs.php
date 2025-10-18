<?php

namespace DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator;

use DigitalCoreHub\LaravelAiTranslator\Support\ReportStore;
use Livewire\Volt\Component;

class Logs extends Component
{
    public array $entries = [];

    public function mount(): void
    {
        $payload = app(ReportStore::class)->all();

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
            ->layout('ai-translator::vendor.ai-translator.layouts.app');
    }
}
