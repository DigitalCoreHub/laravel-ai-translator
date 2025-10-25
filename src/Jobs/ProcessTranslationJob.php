<?php

namespace DigitalCoreHub\LaravelAiTranslator\Jobs;

use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use DigitalCoreHub\LaravelAiTranslator\Support\AiTranslatorLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Process a single translation file in the background.
 * Arka planda tek bir çeviri dosyasını işler.
 */
class ProcessTranslationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes

    public int $tries = 3;

    protected string $logFile;

    public function __construct(
        public string $file,
        public string $from,
        public string $to,
        public string $provider
    ) {
        $this->logFile = 'ai-translator-sync.log';
    }

    /**
     * Execute the job.
     * İşi çalıştırır.
     */
    public function handle(TranslationManager $manager): void
    {
        $startTime = microtime(true);

        try {
            $this->log('Job started', [
                'file' => $this->file,
                'from' => $this->from,
                'to' => $this->to,
                'provider' => $this->provider,
            ]);

            // Translate the file
            $result = $manager->translatePath(
                $this->file,
                $this->from,
                $this->to,
                null, // progress callback
                false, // force
                $this->provider
            );

            $duration = round(microtime(true) - $startTime, 2);

            $this->log('Job completed successfully', [
                'file' => $this->file,
                'missing' => $result['missing'],
                'translated' => $result['translated'],
                'duration' => $duration.'s',
                'provider' => $this->provider,
            ]);

            // Update the report
            $this->updateReport($result, $duration);

        } catch (\Exception $e) {
            $duration = round(microtime(true) - $startTime, 2);

            $this->log('Job failed', [
                'file' => $this->file,
                'error' => $e->getMessage(),
                'duration' => $duration.'s',
            ], 'error');

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     * İş başarısızlığını işler.
     */
    public function failed(\Throwable $exception): void
    {
        $this->log('Job failed permanently', [
            'file' => $this->file,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ], 'error');
    }

    /**
     * Log a message to the sync log file.
     * Senkronizasyon günlük dosyasına mesaj yazar.
     */
    protected function log(string $message, array $context = [], string $level = 'info'): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $jobId = $this->job ? $this->job->getJobId() : 'unknown';
        $logMessage = "[{$timestamp}] Job #{$jobId}: {$message}";

        if (! empty($context)) {
            $logMessage .= ' — '.json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        $logMessage .= PHP_EOL;

        Storage::append($this->logFile, $logMessage);

        // Also log to Laravel's logger
        if ($level === 'error') {
            AiTranslatorLogger::write('ai-translator-sync.log', $message, 'error');
        } else {
            AiTranslatorLogger::sync($message);
        }
    }

    /**
     * Update the translation report.
     * Çeviri raporunu günceller.
     */
    protected function updateReport(array $result, float $duration): void
    {
        $reportFile = 'ai-translator-report.json';
        $reportPath = storage_path("logs/{$reportFile}");

        $report = [];
        if (file_exists($reportPath)) {
            $report = json_decode(file_get_contents($reportPath), true) ?: [];
        }

        $report[] = [
            'timestamp' => now()->toIso8601String(),
            'file' => $this->file,
            'from' => $this->from,
            'to' => $this->to,
            'provider' => $this->provider,
            'missing' => $result['missing'],
            'translated' => $result['translated'],
            'duration' => $duration,
            'status' => 'completed',
        ];

        // Ensure directory exists
        if (! is_dir(dirname($reportPath))) {
            mkdir(dirname($reportPath), 0755, true);
        }

        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Get the tags that should be assigned to the job.
     * İşe atanması gereken etiketleri döndürür.
     */
    public function tags(): array
    {
        return [
            'ai-translator',
            'file:'.$this->file,
            'from:'.$this->from,
            'to:'.$this->to,
            'provider:'.$this->provider,
        ];
    }
}
