<?php

namespace DigitalCoreHub\LaravelAiTranslator\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class TranslationWatcher
{
    protected array $extensions;

    public function __construct(
        protected Filesystem $filesystem
    ) {
        $this->extensions = ['php', 'json'];
    }

    /**
     * Continuously watch provided paths and trigger the callback on changes.
     */
    public function watch(array $paths, callable $callback, int $sleepSeconds = 2): void
    {
        $lastChecked = microtime(true);

        while (true) {
            $changes = $this->scan($paths, $lastChecked);
            $lastChecked = microtime(true);

            foreach ($changes as $file => $metadata) {
                $callback($file, $metadata);
            }

            sleep($sleepSeconds);
        }
    }

    /**
     * Perform a single scan over the configured paths to detect changes.
     *
     * @return array<string, array{mtime: float}>
     */
    public function scan(array $paths, float $since): array
    {
        $changed = [];

        foreach ($this->normalizePaths($paths) as $path) {
            if (! $this->filesystem->exists($path)) {
                continue;
            }

            foreach ($this->files($path) as $file) {
                $absolutePath = $file->getRealPath() ?: $file->getPathname();

                if (! $this->isWatchable($absolutePath)) {
                    continue;
                }

                $mtime = @filemtime($absolutePath);

                if ($mtime === false) {
                    continue;
                }

                if ($mtime > $since) {
                    $changed[$absolutePath] = ['mtime' => $mtime];
                }
            }
        }

        return $changed;
    }

    protected function files(string $path): Finder
    {
        return Finder::create()->files()->in($path);
    }

    protected function normalizePaths(array $paths): array
    {
        return array_values(array_filter(array_map(static function ($path) {
            if (! is_string($path) || trim($path) === '') {
                return null;
            }

            return rtrim($path, '/');
        }, $paths)));
    }

    protected function isWatchable(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, $this->extensions, true)) {
            return false;
        }

        $basename = basename($path);

        return ! Str::startsWith($basename, ['.', '~']);
    }

    public function setExtensions(array $extensions): void
    {
        $this->extensions = array_values(array_filter(array_map(
            static fn ($ext) => ltrim(strtolower((string) $ext), '.'),
            $extensions
        )));
    }
}
