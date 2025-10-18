<?php

namespace DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator;

use DigitalCoreHub\LaravelAiTranslator\Support\QueueMonitor;
use Livewire\Volt\Component;

class QueueStatus extends Component
{
    public array $jobs = [];

    public array $totals = [];

    public string $summary = '';

    public function mount(QueueMonitor $monitor): void
    {
        $this->updateState($monitor);
    }

    public function refreshStatus(QueueMonitor $monitor): void
    {
        $this->updateState($monitor);
    }

    protected function updateState(QueueMonitor $monitor): void
    {
        $state = $monitor->state();
        $this->jobs = $state['jobs'] ?? [];
        $this->totals = $state['totals'] ?? ['pending' => 0, 'completed' => 0, 'failed' => 0];

        if ($this->jobs === []) {
            $this->summary = 'Queue idle';

            return;
        }

        $translated = collect($this->jobs)->sum(static fn ($job) => (int) ($job['translated'] ?? 0));
        $total = collect($this->jobs)->sum(static fn ($job) => (int) ($job['progress_total'] ?? 0));

        if ($total === 0) {
            $total = max($translated, 1);
        }

        $this->summary = sprintf('Translating (%d/%d)', $translated, $total);
    }

    public function render(): mixed
    {
        return view('ai-translator::livewire.translator.queue-status')
            ->layout('ai-translator::vendor.ai-translator.layouts.app');
    }
}
