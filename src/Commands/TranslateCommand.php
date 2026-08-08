<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Commands;

use DigitalCoreHub\LaravelAiTranslator\Data\FailedKey;
use DigitalCoreHub\LaravelAiTranslator\Data\TranslationRun;
use DigitalCoreHub\LaravelAiTranslator\Exceptions\AiTranslatorException;
use DigitalCoreHub\LaravelAiTranslator\Jobs\TranslateFileJob;
use DigitalCoreHub\LaravelAiTranslator\Support\ReportStore;
use DigitalCoreHub\LaravelAiTranslator\Translation\Translator;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\ProgressBar;

class TranslateCommand extends Command
{
    protected $signature = 'ai:translate
        {from? : Kaynak dil kodu (varsayılan config: source_locale)}
        {to?* : Hedef dil kodları; boş bırakılırsa kaynak dışındaki tüm diller}
        {--dry : Hiçbir dosyaya yazmadan sonucu gösterir}
        {--force : Mevcut çevirileri de yeniden üretir}
        {--provider= : Bu koşu için sağlayıcıyı geçici olarak değiştirir}
        {--only= : Sadece belirli dosya/dizin (ör. auth.php veya en/admin)}
        {--review : Kaynak → çeviri → sağlayıcı dökümü gösterir (yazmaz)}
        {--queue : Çeviriyi kuyruğa devreder}';

    protected $description = 'Eksik dil anahtarlarını yapay zekâ ile çevirir';

    public function handle(Translator $translator, ReportStore $reports): int
    {
        $from = $this->resolveFrom($translator);
        $targets = $this->resolveTargets($translator, $from);

        if ($targets === []) {
            $this->components->error('Çevrilecek hedef dil bulunamadı. Hedef dili açıkça belirtin: ai:translate en tr');

            return self::INVALID;
        }

        $review = (bool) $this->option('review');
        $dry = (bool) $this->option('dry') || $review;

        if ($this->option('queue')) {
            return $this->dispatchToQueue($translator, $from, $targets);
        }

        $failed = 0;

        foreach ($targets as $to) {
            if ($to === $from) {
                $this->components->warn("[{$to}] kaynak dille aynı, atlanıyor.");

                continue;
            }

            $pending = $translator->from($from)->to($to)
                ->provider($this->option('provider'))
                ->only($this->option('only'))
                ->force((bool) $this->option('force'))
                ->dryRun($dry);

            $missing = count($pending->missing());

            $this->components->info(sprintf('%s → %s: %d anahtar', $from, $to, $missing));

            if ($missing === 0) {
                continue;
            }

            $bar = $this->createProgressBar($missing);

            try {
                $run = $pending->onProgress(static fn () => $bar?->advance())->translate();
            } catch (AiTranslatorException $exception) {
                $bar?->finish();
                $this->newLine();
                $this->components->error($exception->getMessage());

                return self::FAILURE;
            }

            $bar?->finish();
            $this->newLine();

            $reports->record($run);

            $review ? $this->renderReview($run) : $this->renderSummary($run);

            $failed += $run->failedCount();
        }

        if ($dry) {
            $this->components->warn('Deneme koşusu: hiçbir dosyaya yazılmadı.');
        }

        // Başarısız anahtar varsa exit kodu 1: CI'da sessizce yeşile geçmesin.
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $targets
     */
    protected function dispatchToQueue(Translator $translator, string $from, array $targets): int
    {
        $connection = config('ai-translator.queue.connection');
        $queue = (string) config('ai-translator.queue.name', 'ai-translations');
        $dispatched = 0;

        foreach ($targets as $to) {
            if ($to === $from) {
                continue;
            }

            // Dosya başına bir iş: biri patlarsa diğerleri etkilenmesin,
            // ve queue:retry ile sadece o dosya tekrar denensin.
            foreach ($translator->from($from)->to($to)->only($this->option('only'))->sourceFiles() as $relative) {
                TranslateFileJob::dispatch(
                    file: $relative,
                    from: $from,
                    to: $to,
                    provider: $this->option('provider'),
                    force: (bool) $this->option('force'),
                )->onConnection($connection)->onQueue($queue);

                $dispatched++;
            }
        }

        $this->components->info(sprintf('%d iş [%s] kuyruğuna alındı.', $dispatched, $queue));
        $this->components->bulletList([
            "İşlemek için: php artisan queue:work --queue={$queue}",
        ]);

        return self::SUCCESS;
    }

    protected function resolveFrom(Translator $translator): string
    {
        $from = $this->argument('from');

        return is_string($from) && $from !== ''
            ? $from
            : (string) config('ai-translator.source_locale', 'en');
    }

    /**
     * @return array<int, string>
     */
    protected function resolveTargets(Translator $translator, string $from): array
    {
        $targets = array_values(array_filter((array) $this->argument('to')));

        if ($targets !== []) {
            return $targets;
        }

        // Hedef verilmediyse dil kökündeki kaynak dışı bütün diller.
        return array_values(array_filter(
            $translator->locales(),
            static fn (string $locale) => $locale !== $from
        ));
    }

    protected function createProgressBar(int $total): ?ProgressBar
    {
        if ($total <= 0 || $this->output->isQuiet()) {
            return null;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%');
        $bar->start();

        return $bar;
    }

    protected function renderSummary(TranslationRun $run): void
    {
        $rows = [];

        foreach ($run->files as $outcome) {
            if ($outcome->missing === 0) {
                continue;
            }

            $rows[] = [
                $outcome->file,
                $outcome->missing,
                $outcome->translatedCount(),
                $outcome->failedCount() ?: '-',
                $outcome->cacheHits() ?: '-',
                $outcome->primaryProvider() ?? '-',
            ];
        }

        if ($rows !== []) {
            $this->table(['Dosya', 'Eksik', 'Çevrilen', 'Hatalı', 'Cache', 'Sağlayıcı'], $rows);
        }

        $this->components->twoColumnDetail(
            'Toplam',
            sprintf(
                '%d çevrildi, %d hatalı, %d cache, %.1f sn',
                $run->translatedCount(),
                $run->failedCount(),
                $run->cacheHits(),
                $run->durationMs / 1000,
            )
        );

        $this->renderFailures($run->allFailed());
    }

    protected function renderReview(TranslationRun $run): void
    {
        foreach ($run->files as $outcome) {
            if ($outcome->translated === [] && $outcome->failed === []) {
                continue;
            }

            $this->line("  <comment>{$outcome->file}</comment>");

            foreach ($outcome->translated as $key) {
                $this->line(sprintf(
                    '    %s: %s <fg=gray>→</> %s <fg=gray>(%s%s)</>',
                    $key->key,
                    $key->source,
                    $key->translation,
                    $key->provider,
                    $key->fromCache ? ', cache' : '',
                ));
            }
        }

        $this->newLine();
        $this->renderFailures($run->allFailed());
    }

    /**
     * @param  array<int, FailedKey>  $failures
     */
    protected function renderFailures(array $failures): void
    {
        if ($failures === []) {
            return;
        }

        $this->newLine();
        $this->components->error(sprintf('%d anahtar çevrilemedi ve dosyaya yazılmadı:', count($failures)));

        foreach (array_slice($failures, 0, 10) as $failure) {
            $this->components->twoColumnDetail(
                "{$failure->file} · {$failure->key}",
                $failure->reason,
            );
        }

        if (count($failures) > 10) {
            $this->components->warn(sprintf('… ve %d tane daha.', count($failures) - 10));
        }
    }
}
