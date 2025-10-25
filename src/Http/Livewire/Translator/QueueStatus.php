<?php

namespace DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

/**
 * Real-time queue status component for translation jobs.
 * Çeviri işleri için gerçek zamanlı kuyruk durumu bileşeni.
 */
class QueueStatus extends Component
{
    public int $completed = 0;

    public int $total = 0;

    public int $failed = 0;

    public int $pending = 0;

    public array $recentJobs = [];

    public bool $isProcessing = false;

    protected $listeners = ['refreshQueueStatus'];

    public function mount(): void
    {
        $this->updateQueueStatus();
    }

    public function render(): mixed
    {
        return view('livewire.translator.queue-status');
    }

    /**
     * Update queue status from database and logs.
     * Veritabanı ve günlüklerden kuyruk durumunu günceller.
     */
    public function updateQueueStatus(): void
    {
        $this->updateFromDatabase();
        $this->updateFromLogs();
        $this->updateRecentJobs();
    }

    /**
     * Update status from database queue tables.
     * Veritabanı kuyruk tablolarından durumu günceller.
     */
    protected function updateFromDatabase(): void
    {
        try {
            // Check if jobs table exists
            if (! DB::getSchemaBuilder()->hasTable('jobs')) {
                return;
            }

            $queueName = config('ai-translator.queue_name', 'ai-translations');

            // Count pending jobs
            $this->pending = DB::table('jobs')
                ->where('queue', $queueName)
                ->count();

            // Count failed jobs
            if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                $this->failed = DB::table('failed_jobs')
                    ->where('payload', 'like', '%ProcessTranslationJob%')
                    ->count();
            }

        } catch (\Exception $e) {
            // Silently handle database errors
        }
    }

    /**
     * Update status from log files.
     * Günlük dosyalarından durumu günceller.
     */
    protected function updateFromLogs(): void
    {
        try {
            $reportFile = 'ai-translator-report.json';

            if (Storage::exists($reportFile)) {
                $report = json_decode(Storage::get($reportFile), true);

                if (is_array($report)) {
                    $this->completed = count(array_filter($report, fn ($job) => ($job['status'] ?? '') === 'completed'
                    ));
                }
            }
        } catch (\Exception $e) {
            // Silently handle file errors
        }
    }

    /**
     * Update recent jobs list.
     * Son işler listesini günceller.
     */
    protected function updateRecentJobs(): void
    {
        try {
            $reportFile = 'ai-translator-report.json';

            if (Storage::exists($reportFile)) {
                $report = json_decode(Storage::get($reportFile), true);

                if (is_array($report)) {
                    // Get last 10 jobs, sorted by timestamp
                    $this->recentJobs = array_slice(
                        array_reverse($report),
                        0,
                        10
                    );
                }
            }
        } catch (\Exception $e) {
            $this->recentJobs = [];
        }
    }

    /**
     * Refresh queue status (called by wire:poll).
     * Kuyruk durumunu yeniler (wire:poll tarafından çağrılır).
     */
    public function refreshQueueStatus(): void
    {
        $this->updateQueueStatus();
    }

    /**
     * Get the completion percentage.
     * Tamamlanma yüzdesini döndürür.
     */
    public function getCompletionPercentageProperty(): float
    {
        if ($this->total === 0) {
            return 0;
        }

        return round(($this->completed / $this->total) * 100, 1);
    }

    /**
     * Get the status color for the progress bar.
     * İlerleme çubuğu için durum rengini döndürür.
     */
    public function getStatusColorProperty(): string
    {
        if ($this->failed > 0) {
            return 'red';
        }

        if ($this->pending > 0) {
            return 'blue';
        }

        if ($this->completed > 0 && $this->pending === 0) {
            return 'green';
        }

        return 'gray';
    }

    /**
     * Get the status message.
     * Durum mesajını döndürür.
     */
    public function getStatusMessageProperty(): string
    {
        if ($this->failed > 0) {
            return "Failed ({$this->failed})";
        }

        if ($this->pending > 0) {
            return "Processing ({$this->completed}/{$this->total})";
        }

        if ($this->completed > 0 && $this->pending === 0) {
            return "✅ Completed ({$this->completed})";
        }

        return 'No active jobs';
    }

    /**
     * Check if there are any active jobs.
     * Aktif iş olup olmadığını kontrol eder.
     */
    public function getHasActiveJobsProperty(): bool
    {
        return $this->pending > 0 || $this->completed > 0;
    }

    /**
     * Clear completed jobs from the report.
     * Tamamlanan işleri rapordan temizler.
     */
    public function clearCompleted(): void
    {
        try {
            $reportFile = 'ai-translator-report.json';

            if (Storage::exists($reportFile)) {
                $report = json_decode(Storage::get($reportFile), true);

                if (is_array($report)) {
                    // Keep only failed jobs
                    $filtered = array_filter($report, fn ($job) => ($job['status'] ?? '') !== 'completed'
                    );

                    Storage::put($reportFile, json_encode(array_values($filtered), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            }

            $this->updateQueueStatus();
            $this->dispatch('queueStatusUpdated');

        } catch (\Exception $e) {
            $this->addError('clear', 'Failed to clear completed jobs: '.$e->getMessage());
        }
    }
}
