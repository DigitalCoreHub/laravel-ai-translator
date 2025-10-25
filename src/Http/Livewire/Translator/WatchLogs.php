<?php

namespace DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator;

use Illuminate\Filesystem\Filesystem;
use Livewire\Volt\Component;

class WatchLogs extends Component
{
    public array $lines = [];

    public function mount(): void
    {
        $filesystem = app(Filesystem::class);
        $path = storage_path('logs/ai-translator-watch.log');

        if (! $filesystem->exists($path)) {
            $this->lines = [];

            return;
        }

        $contents = $filesystem->get($path);
        $this->lines = array_values(array_filter(array_map('trim', explode(PHP_EOL, $contents))));
        $this->lines = array_reverse($this->lines);
    }

    public function render(): mixed
    {
        return view('ai-translator::livewire.translator.watch-logs')
            ->layout('ai-translator::vendor.ai-translator.layouts.app');
    }
}
