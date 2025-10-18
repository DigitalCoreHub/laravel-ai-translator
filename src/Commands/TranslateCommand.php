<?php

namespace DigitalCoreHub\LaravelAiTranslator\Commands;

use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use DigitalCoreHub\LaravelAiTranslator\Support\AiTranslatorLogger;
use DigitalCoreHub\LaravelAiTranslator\Support\ReportStore;
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
        {--force : Mevcut çevirileri yeniden üretir ve üzerine yazar}
        {--provider= : Geçici olarak kullanılacak çeviri sağlayıcısı}
        {--review : Çevirileri kaydetmeden kaynak → çeviri → sağlayıcı formatında gösterir}
        {--cache-clear : Çeviri önbelleğini temizler}';

    /**
     * The console command description.
     * Konsol komutunun açıklaması.
     */
    protected $description = 'Translate missing keys between language files using AI';

    public function __construct(
        protected TranslationManager $manager,
        protected Filesystem $filesystem,
        protected ReportStore $reports
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
        $providerOverride = $this->option('provider') ? (string) $this->option('provider') : null;
        $reviewMode = (bool) $this->option('review');
        $cacheClear = (bool) $this->option('cache-clear');

        if ($cacheClear) {
            $this->manager->clearCache();
            $this->info('Çeviri önbelleği temizlendi.');
        }

        if ($reviewMode) {
            $dryRun = true;
        }

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
                $force,
                [
                    'provider' => $providerOverride,
                    'review' => $reviewMode,
                ]
            );

            if ($progressBar instanceof ProgressBar) {
                $progressBar->finish();
                $this->newLine();
            }

            if ($reviewMode) {
                if ($result['reviews'] === []) {
                    $this->line('  (Yeni çeviri bulunamadı)');
                }

                foreach ($result['reviews'] as $file => $entries) {
                    $this->line("  {$file}:");

                    foreach ($entries as $key => $details) {
                        $this->line(sprintf(
                            '    %s → %s → %s%s',
                            $details['source'],
                            $details['translation'],
                            $details['provider'],
                            $details['cache'] ? ' (cache)' : ''
                        ));
                    }
                }

                $this->comment('Review mode: Çeviriler dosyalara yazılmadı.');
            } elseif ($dryRun) {
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

            $this->logSummary($from, $to, $result, $dryRun, $force, $providerOverride);

            $this->reports->appendTranslationRun(
                $from,
                $to,
                $providerOverride ?? config('ai-translator.provider', 'openai'),
                $result['report'],
                ['executed_at' => now()->toIso8601String()]
            );

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
    protected function logSummary(string $from, string $to, array $result, bool $dryRun, bool $force, ?string $provider): void
    {
        AiTranslatorLogger::info(sprintf(
            'from=%s to=%s missing=%d translated=%d dry=%s force=%s provider=%s',
            $from,
            $to,
            $result['totals']['missing'],
            $result['totals']['translated'],
            $dryRun ? 'true' : 'false',
            $force ? 'true' : 'false',
            $provider ?? config('ai-translator.provider', 'openai')
        ));
    }
}
