<?php

namespace DigitalCoreHub\LaravelAiTranslator\Commands;

use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use Illuminate\Console\Command;

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

        $this->info("Scanning language directories: {$from} -> {$to}");

        $result = $this->manager->translate($from, $to, function (string $file, string $key, string $source) {
            $this->line("Translating [{$file}] {$key}");
        });

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
