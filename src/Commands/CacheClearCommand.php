<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Commands;

use DigitalCoreHub\LaravelAiTranslator\Cache\TranslationCache;
use Illuminate\Console\Command;

class CacheClearCommand extends Command
{
    protected $signature = 'ai:translate:cache-clear';

    protected $description = 'Sadece çeviri önbelleğini temizler (uygulama cache\'ine dokunmaz)';

    public function handle(TranslationCache $cache): int
    {
        if (! $cache->enabled()) {
            $this->components->warn('Çeviri önbelleği zaten kapalı.');

            return self::SUCCESS;
        }

        $cache->flush();

        $this->components->info(sprintf(
            'Çeviri önbelleği temizlendi (sürüm v%d). Uygulamanın diğer cache kayıtlarına dokunulmadı.',
            $cache->version()
        ));

        return self::SUCCESS;
    }
}
