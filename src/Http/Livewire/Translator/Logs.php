<?php

namespace DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator;

use Illuminate\Support\Facades\File;
use Livewire\Component;

class Logs extends Component
{
    public array $entries = [];

    public function mount(): void
    {
        $this->loadEntries();
    }

    public function refreshLogs(): void
    {
        $this->loadEntries();
    }

    protected function loadEntries(): void
    {
        $path = storage_path('logs/ai-translator-report.json');

        if (! File::exists($path)) {
            $this->entries = [];

            return;
        }

        $decoded = json_decode(File::get($path), true);

        if (! is_array($decoded)) {
            $this->entries = [];

            return;
        }

        $this->entries = array_map(function (array $entry) {
            $entry['timestamp'] = $entry['timestamp'] ?? null;

            return $entry;
        }, $decoded);
    }

    public function render()
    {
        return view('ai-translator::livewire.translator.logs')
            ->layout('ai-translator::livewire.translator.layout');
    }
}
