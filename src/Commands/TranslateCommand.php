<?php

namespace DigitalCoreHub\LaravelAiTranslator\Commands;

use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Translate missing language keys via an artisan command.
 * Eksik dil anahtarlarını artisan komutu aracılığıyla çevirir.
 */
class TranslateCommand extends Command
{
    /**
     * The console command signature.
     * Konsol komutunun imzası.
     */
    protected $signature = 'ai:translate
        {from : Kaynak dil kodu}
        {to* : Çevrilecek hedef dil kodları}
        {--dry : Çevirileri dosyalara yazmadan terminalde önizler}
        {--force : Mevcut çevirileri yeniden üretir ve üzerine yazar}';

    /**
     * The console command description.
     * Konsol komutunun açıklaması.
     */
    protected $description = 'Translate missing keys between language files using AI';

    public function __construct(
        protected TranslationManager $manager,
        protected Filesystem $filesystem
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     * Konsol komutunu çalıştırır.
     */
    public function handle(): int
    {
        $from = (string) $this->argument('from');
        $targets = array_values(array_filter((array) $this->argument('to')));
        $dryRun = (bool) $this->option('dry');
        $force = (bool) $this->option('force');

        if ($targets === []) {
            $this->error('En az bir hedef dil belirtilmelidir.');

            return self::INVALID;
        }

        $lastIndex = count($targets) - 1;

        foreach ($targets as $index => $to) {
            $this->info("{$from} -> {$to} çeviri işlemi başlatılıyor…");

            $totalMissing = $this->manager->countMissing($from, $to, $force);

            $progressBar = null;
            $currentStep = 0;

            if ($totalMissing > 0) {
                $progressBar = new ProgressBar($this->output, $totalMissing);
                $progressBar->setFormat('%message%');
                $progressBar->setOverwrite(false);
            }

            $result = $this->manager->translate(
                $from,
                $to,
                function (string $file, string $key, string $source) use (&$currentStep, $progressBar) {
                    if ($progressBar instanceof ProgressBar) {
                        $currentStep++;
                        $progressBar->setMessage(sprintf('Çevriliyor (%d/%d)', $currentStep, $progressBar->getMaxSteps()));
                        $progressBar->advance();
                    }
                },
                $dryRun,
                $force
            );

            if ($progressBar instanceof ProgressBar) {
                $progressBar->finish();
                $this->newLine();
            }

            if ($dryRun) {
                $this->comment('Dry run: Çeviriler dosyalara yazılmadı.');

                if ($result['previews'] === []) {
                    $this->line('  (Yeni çeviri bulunamadı)');
                }

                foreach ($result['previews'] as $file => $entries) {
                    $this->line("  {$file}:");

                    foreach ($entries as $key => $translation) {
                        $this->line(sprintf('    %s => %s', $key, $translation));
                    }
                }
            }

            $this->newLine();

            $this->table(
                ['File', 'Missing', 'Translated'],
                collect($result['files'])->map(function (array $file) {
                    return [$file['name'], $file['missing'], $file['translated']];
                })->all()
            );

            $this->info("Total missing: {$result['totals']['missing']} | Translated: {$result['totals']['translated']}");

            if ($result['totals']['missing'] === 0 && ! $force) {
                $this->info('No missing translations detected.');
            }

            $this->logSummary($from, $to, $result, $dryRun, $force);

            $this->info('✔ Çeviri işlemi tamamlandı!');

            if ($index < $lastIndex) {
                $this->newLine();
            }
        }

        return self::SUCCESS;
    }

    /**
     * Persist a summary of the translation run to the log file.
     * Çeviri işleminin özetini günlük dosyasına yazar.
     */
    protected function logSummary(string $from, string $to, array $result, bool $dryRun, bool $force): void
    {
        $logPath = storage_path('logs/ai-translator.log');
        $directory = dirname($logPath);

        if (! $this->filesystem->isDirectory($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true);
        }

        $line = sprintf(
            '[%s] from=%s to=%s missing=%d translated=%d dry=%s force=%s',
            now()->toDateTimeString(),
            $from,
            $to,
            $result['totals']['missing'],
            $result['totals']['translated'],
            $dryRun ? 'true' : 'false',
            $force ? 'true' : 'false'
        );

        $this->filesystem->append($logPath, $line.PHP_EOL);
    }
}
