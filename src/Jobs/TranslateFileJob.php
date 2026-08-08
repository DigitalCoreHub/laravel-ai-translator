<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Jobs;

use DigitalCoreHub\LaravelAiTranslator\Support\ReportStore;
use DigitalCoreHub\LaravelAiTranslator\Translation\Translator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tek bir dil dosyasını arka planda çevirir.
 *
 * v0.x'te bu iş config'teki kuyruk ayarlarını hiç okumuyordu; README "--queue=ai-translations
 * ile çalıştırın" derken işler default kuyruğa gidiyor ve orada öylece bekliyordu.
 * Artık bağlantı ve kuyruk adı komuttan/config'ten geliyor.
 */
class TranslateFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        /** Dil köküne göre KAYNAK dosya yolu, ör. "en/auth.php" veya "en.json" */
        public string $file,
        public string $from,
        public string $to,
        public ?string $provider = null,
        public bool $force = false,
    ) {}

    public function handle(Translator $translator, ReportStore $reports): void
    {
        $run = $translator
            ->from($this->from)
            ->to($this->to)
            ->provider($this->provider)
            ->force($this->force)
            ->only($this->file)
            ->translate();

        $reports->record($run);

        if ($run->hasFailures()) {
            Log::channel($this->logChannel())->warning('ai-translator: bazı anahtarlar çevrilemedi.', [
                'file' => $this->file,
                'from' => $this->from,
                'to' => $this->to,
                'failed' => $run->failedCount(),
            ]);
        }
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addSeconds((int) config('ai-translator.queue.timeout', 600));
    }

    public function tries(): int
    {
        return max(1, (int) config('ai-translator.queue.tries', 3));
    }

    /**
     * Geçici hatalarda üstel bekleme; rate limit yiyen bir kuyruğu daha da dövmeyelim.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function failed(Throwable $exception): void
    {
        Log::channel($this->logChannel())->error('ai-translator: dosya çevirisi başarısız.', [
            'file' => $this->file,
            'from' => $this->from,
            'to' => $this->to,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['ai-translator', "file:{$this->file}", "locale:{$this->from}-{$this->to}"];
    }

    protected function logChannel(): ?string
    {
        $channel = config('ai-translator.log_channel');

        return is_string($channel) && $channel !== '' ? $channel : config('logging.default');
    }
}
