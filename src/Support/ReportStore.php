<?php

namespace DigitalCoreHub\LaravelAiTranslator\Support;

use Illuminate\Filesystem\Filesystem;

class ReportStore
{
    public function __construct(
        protected Filesystem $filesystem
    ) {}

    public function path(): string
    {
        return storage_path('logs/ai-translator-report.json');
    }

    public function all(): array
    {
        $path = $this->path();

        if (! $this->filesystem->exists($path)) {
            return [];
        }

        $decoded = json_decode($this->filesystem->get($path), true);

        if (! is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    public function append(array $run): void
    {
        $runs = $this->all();
        $runs[] = $run;

        $path = $this->path();

        if (! $this->filesystem->isDirectory(dirname($path))) {
            $this->filesystem->makeDirectory(dirname($path), 0755, true, true);
        }

        $this->filesystem->put(
            $path,
            json_encode($runs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL
        );
    }

    public function appendTranslationRun(
        string $from,
        string $to,
        string $provider,
        array $files,
        array $extra = []
    ): void {
        $payload = array_merge($extra, [
            'from' => $from,
            'to' => $to,
            'provider' => $provider,
            'executed_at' => $extra['executed_at'] ?? now()->toIso8601String(),
            'files' => $files,
        ]);

        $this->append($payload);
    }
}
