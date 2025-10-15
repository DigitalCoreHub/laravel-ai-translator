<?php

namespace DigitalCoreHub\LaravelAiTranslator\Commands;

use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use Illuminate\Console\Command;
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
    protected $signature = 'ai:translate {from} {to}';

    /**
     * The console command description.
     * Konsol komutunun açıklaması.
     */
    protected $description = 'Translate missing keys between language files using AI';

    public function __construct(protected TranslationManager $manager)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     * Konsol komutunu çalıştırır.
     */
    public function handle(): int
    {
        $from = $this->argument('from');
        $to = $this->argument('to');

        $totalMissing = $this->manager->countMissing($from, $to);

        $this->info("{$from} -> {$to} çeviri işlemi başlatılıyor…");

        $progressBar = null;
        $currentStep = 0;

        if ($totalMissing > 0) {
            $progressBar = new ProgressBar($this->output, $totalMissing);
            $progressBar->setFormat('%message%');
            $progressBar->setOverwrite(false);
        }

        $result = $this->manager->translate($from, $to, function (string $file, string $key, string $source) use (&$currentStep, $progressBar) {
            if ($progressBar instanceof ProgressBar) {
                $currentStep++;
                $progressBar->setMessage(sprintf('Çevriliyor (%d/%d)', $currentStep, $progressBar->getMaxSteps()));
                $progressBar->advance();
            }
        });

        if ($progressBar instanceof ProgressBar) {
            $progressBar->finish();
            $this->newLine();
        }

        $this->info('✔ Çeviri işlemi tamamlandı!');

        $this->newLine();
        $this->table(
            ['File', 'Missing', 'Translated'],
            collect($result['files'])->map(function (array $file) {
                return [$file['name'], $file['missing'], $file['translated']];
            })->all()
        );

        $this->info("Total missing: {$result['totals']['missing']} | Translated: {$result['totals']['translated']}");

        if ($result['totals']['missing'] === 0) {
            $this->info('No missing translations detected.');
        }

        return self::SUCCESS;
    }
}
