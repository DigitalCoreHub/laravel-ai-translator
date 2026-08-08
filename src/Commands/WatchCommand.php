<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Commands;

use DigitalCoreHub\LaravelAiTranslator\Translation\LocaleFileRepository;
use DigitalCoreHub\LaravelAiTranslator\Translation\TranslationWatcher;
use DigitalCoreHub\LaravelAiTranslator\Translation\Translator;
use Illuminate\Console\Command;

/**
 * Kaynak dil dosyalarını izler, değişeni kuyruğa atar.
 *
 * v0.x'te izlenecek dil listesi koda gömülüydü (['en','tr','es',...]), watch_interval
 * config'i yok sayılıyordu ve her saniye bütün ağaç baştan taranıyordu.
 */
class WatchCommand extends Command
{
    protected $signature = 'ai:watch
        {--from= : Kaynak dil kodu}
        {--to=* : Hedef diller; boş bırakılırsa config veya dizindeki tüm diller}
        {--provider= : Kullanılacak sağlayıcı}
        {--only= : Sadece belirli dosya/dizin}
        {--interval= : Tarama aralığı (saniye)}
        {--once : Tek tur çalışıp çıkar (cron veya test için)}';

    protected $description = 'Dil dosyalarını izler, değişenleri çeviri kuyruğuna alır';

    /** Sinyal geldiğinde döngüden temiz çıkmak için. */
    protected bool $stopping = false;

    public function handle(Translator $translator, LocaleFileRepository $files): int
    {
        $from = $this->option('from') ?: (string) config('ai-translator.source_locale', 'en');
        $targets = $this->resolveTargets($translator, $from);

        if ($targets === []) {
            $this->components->error(
                'İzlenecek hedef dil yok. --to ile belirtin ya da config/ai-translator.php '
                .'içindeki watch.locales listesini doldurun.'
            );

            return self::INVALID;
        }

        $watcher = (new TranslationWatcher(
            translator: $translator,
            from: $from,
            targets: $targets,
            provider: $this->option('provider'),
            only: $this->option('only'),
            connection: config('ai-translator.queue.connection'),
            queue: (string) config('ai-translator.queue.name', 'ai-translations'),
        ))->onChange(function (string $file, int $jobs): void {
            $this->components->twoColumnDetail($file, "{$jobs} iş kuyruğa alındı");
        });

        $this->components->info(sprintf('%s → %s izleniyor', $from, implode(', ', $targets)));
        $this->components->bulletList($files->existingRoots() ?: ['(dil dizini bulunamadı)']);

        if ($this->option('once')) {
            // Tek turda "değişen" kavramı yok; her şeyi taze sayıp kuyruğa alıyoruz.
            $this->components->info(sprintf('%d iş kuyruğa alındı.', $watcher->tick()));

            return self::SUCCESS;
        }

        $interval = max(1, (int) ($this->option('interval') ?: config('ai-translator.watch.interval', 2)));

        $watcher->prime();

        // Ctrl+C ya da supervisor'ın SIGTERM'i geldiğinde turu yarıda kesmeden çıkıyoruz;
        // aksi halde kuyruğa yarım dosya listesi bırakabilirdik.
        $this->trap([SIGINT, SIGTERM], function (): void {
            $this->stopping = true;
        });

        $this->components->info(sprintf('%d sn aralıkla taranıyor. Ctrl+C ile durdurun.', $interval));

        while (! $this->stopping) {
            $watcher->tick();

            sleep($interval);
        }

        $this->components->info('İzleme durduruldu.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveTargets(Translator $translator, string $from): array
    {
        $targets = array_values(array_filter((array) $this->option('to')));

        if ($targets === []) {
            $configured = config('ai-translator.watch.locales', []);
            $targets = is_array($configured) ? array_values(array_filter($configured)) : [];
        }

        if ($targets === []) {
            $targets = $translator->locales();
        }

        return array_values(array_filter($targets, static fn (string $locale) => $locale !== $from));
    }
}
