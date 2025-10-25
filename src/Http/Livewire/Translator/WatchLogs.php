<?php

namespace DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator;

use Illuminate\Support\Facades\Storage;
use Livewire\Component;

/**
 * Watch logs component for viewing file change logs.
 * Dosya değişiklik günlüklerini görüntülemek için izleme günlükleri bileşeni.
 */
class WatchLogs extends Component
{
    public array $logs = [];

    public int $page = 1;

    public int $perPage = 50;

    public string $filter = 'all'; // all, info, error

    public bool $autoRefresh = true;

    protected $listeners = ['refreshLogs'];

    public function mount(): void
    {
        $this->loadLogs();
    }

    public function render(): mixed
    {
        return view('livewire.translator.watch-logs');
    }

    /**
     * Load logs from the watch log file.
     * İzleme günlük dosyasından günlükleri yükler.
     */
    public function loadLogs(): void
    {
        $this->logs = [];

        try {
            $logFile = 'ai-translator-watch.log';

            if (Storage::exists($logFile)) {
                $content = Storage::get($logFile);
                $lines = array_filter(explode("\n", $content));

                // Parse log lines
                foreach ($lines as $line) {
                    if (empty(trim($line))) {
                        continue;
                    }

                    $log = $this->parseLogLine($line);
                    if ($log) {
                        $this->logs[] = $log;
                    }
                }

                // Reverse to show newest first
                $this->logs = array_reverse($this->logs);
            }
        } catch (\Exception $e) {
            $this->addError('logs', 'Failed to load logs: '.$e->getMessage());
        }
    }

    /**
     * Parse a single log line.
     * Tek bir günlük satırını ayrıştırır.
     */
    protected function parseLogLine(string $line): ?array
    {
        // Expected format: [timestamp] Watcher: message — context
        if (! preg_match('/^\[([^\]]+)\] Watcher: (.+?)(?: — (.+))?$/', $line, $matches)) {
            return null;
        }

        $timestamp = $matches[1];
        $message = $matches[2];
        $context = isset($matches[3]) ? json_decode($matches[3], true) : [];

        return [
            'timestamp' => $timestamp,
            'message' => $message,
            'context' => $context,
            'level' => $this->determineLogLevel($message),
            'raw' => $line,
        ];
    }

    /**
     * Determine log level from message.
     * Mesajdan günlük seviyesini belirler.
     */
    protected function determineLogLevel(string $message): string
    {
        if (stripos($message, 'error') !== false || stripos($message, 'failed') !== false) {
            return 'error';
        }

        if (stripos($message, 'warning') !== false || stripos($message, 'warn') !== false) {
            return 'warning';
        }

        return 'info';
    }

    /**
     * Get filtered logs based on current filter.
     * Mevcut filtreye göre filtrelenmiş günlükleri döndürür.
     */
    public function getFilteredLogsProperty(): array
    {
        $filtered = $this->logs;

        if ($this->filter !== 'all') {
            $filtered = array_filter($filtered, fn ($log) => $log['level'] === $this->filter);
        }

        return array_values($filtered);
    }

    /**
     * Get paginated logs.
     * Sayfalanmış günlükleri döndürür.
     */
    public function getPaginatedLogsProperty(): array
    {
        $filtered = $this->filteredLogs;
        $offset = ($this->page - 1) * $this->perPage;

        return array_slice($filtered, $offset, $this->perPage);
    }

    /**
     * Get total pages for pagination.
     * Sayfalama için toplam sayfa sayısını döndürür.
     */
    public function getTotalPagesProperty(): int
    {
        return (int) ceil(count($this->filteredLogs) / $this->perPage);
    }

    /**
     * Refresh logs.
     * Günlükleri yeniler.
     */
    public function refreshLogs(): void
    {
        $this->loadLogs();
    }

    /**
     * Clear all logs.
     * Tüm günlükleri temizler.
     */
    public function clearLogs(): void
    {
        try {
            $logFile = 'ai-translator-watch.log';
            Storage::put($logFile, '');
            $this->logs = [];
            $this->page = 1;
            $this->dispatch('logsCleared');
        } catch (\Exception $e) {
            $this->addError('clear', 'Failed to clear logs: '.$e->getMessage());
        }
    }

    /**
     * Go to next page.
     * Sonraki sayfaya git.
     */
    public function nextPage(): void
    {
        if ($this->page < $this->totalPages) {
            $this->page++;
        }
    }

    /**
     * Go to previous page.
     * Önceki sayfaya git.
     */
    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    /**
     * Go to specific page.
     * Belirli bir sayfaya git.
     */
    public function goToPage(int $page): void
    {
        if ($page >= 1 && $page <= $this->totalPages) {
            $this->page = $page;
        }
    }

    /**
     * Toggle auto refresh.
     * Otomatik yenilemeyi aç/kapat.
     */
    public function toggleAutoRefresh(): void
    {
        $this->autoRefresh = ! $this->autoRefresh;
    }

    /**
     * Get log level color class.
     * Günlük seviyesi renk sınıfını döndürür.
     */
    public function getLogLevelColor(string $level): string
    {
        return match ($level) {
            'error' => 'text-red-600 bg-red-100',
            'warning' => 'text-yellow-600 bg-yellow-100',
            'info' => 'text-blue-600 bg-blue-100',
            default => 'text-gray-600 bg-gray-100'
        };
    }

    /**
     * Format context for display.
     * Görüntüleme için bağlamı biçimlendirir.
     */
    public function formatContext(array $context): string
    {
        if (empty($context)) {
            return '';
        }

        $formatted = [];
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $formatted[] = "{$key}: {$value}";
        }

        return implode(', ', $formatted);
    }
}
