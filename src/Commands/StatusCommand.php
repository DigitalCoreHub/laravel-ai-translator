<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Commands;

use DigitalCoreHub\LaravelAiTranslator\Data\MissingKey;
use DigitalCoreHub\LaravelAiTranslator\Translation\Translator;
use Illuminate\Console\Command;

/**
 * Hiçbir şey çevirmez, para harcamaz — sadece "ne eksik" der.
 *
 * --fail-on-missing ile CI'da kapı görevi görür: eksik çeviriyle main'e merge edilmesin.
 */
class StatusCommand extends Command
{
    protected $signature = 'ai:translate:status
        {from? : Kaynak dil kodu}
        {to?* : Kontrol edilecek hedef diller; boş bırakılırsa hepsi}
        {--only= : Sadece belirli dosya/dizin}
        {--json : Çıktıyı JSON olarak verir}
        {--fail-on-missing : Eksik varsa 1 ile çıkar (CI kapısı)}';

    protected $description = 'Diller arası eksik çeviri anahtarlarını raporlar';

    public function handle(Translator $translator): int
    {
        $from = $this->argument('from') ?: (string) config('ai-translator.source_locale', 'en');
        $targets = array_values(array_filter((array) $this->argument('to')));

        if ($targets === []) {
            $targets = array_values(array_filter(
                $translator->locales(),
                static fn (string $locale) => $locale !== $from
            ));
        }

        $report = [];

        foreach ($targets as $to) {
            if ($to === $from) {
                continue;
            }

            $scoped = $translator->from($from)->to($to)->only($this->option('only'));

            $entries = $scoped->entries();
            $missing = $scoped->missing();

            $report[$to] = [
                'total' => count($entries),
                'missing' => count($missing),
                'translated' => count($entries) - count($missing),
                'keys' => array_map(static fn (MissingKey $k) => $k->toArray(), $missing),
            ];
        }

        $totalMissing = array_sum(array_column($report, 'missing'));

        $this->option('json')
            ? $this->renderJson($from, $report)
            : $this->renderTable($from, $report, $totalMissing);

        return $totalMissing > 0 && $this->option('fail-on-missing')
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * @param  array<string, array<string, mixed>>  $report
     */
    protected function renderJson(string $from, array $report): void
    {
        $this->output->writeln((string) json_encode([
            'from' => $from,
            'locales' => $report,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<string, array<string, mixed>>  $report
     */
    protected function renderTable(string $from, array $report, int $totalMissing): void
    {
        if ($report === []) {
            $this->components->warn("[{$from}] dışında hiç dil bulunamadı.");

            return;
        }

        $rows = [];

        foreach ($report as $locale => $stats) {
            $total = (int) $stats['total'];
            $coverage = $total > 0 ? round(($stats['translated'] / $total) * 100) : 100;

            $rows[] = [
                $locale,
                $total,
                $stats['translated'],
                $stats['missing'] ?: '-',
                $coverage.'%',
            ];
        }

        $this->table(['Dil', 'Toplam', 'Çevrilmiş', 'Eksik', 'Kapsam'], $rows);

        if ($totalMissing === 0) {
            $this->components->info('Eksik çeviri yok.');

            return;
        }

        $this->components->warn(sprintf('Toplam %d eksik anahtar.', $totalMissing));

        if ($this->output->isVerbose()) {
            foreach ($report as $locale => $stats) {
                foreach ($stats['keys'] as $key) {
                    $this->components->twoColumnDetail(
                        "{$locale} · {$key['file']}",
                        $key['key']
                    );
                }
            }

            return;
        }

        $this->components->bulletList(['Anahtarları görmek için -v ekleyin.']);
    }
}
