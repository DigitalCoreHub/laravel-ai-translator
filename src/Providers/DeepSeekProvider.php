<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Providers;

/**
 * DeepSeek OpenAI uyumlu bir API sunuyor, o yüzden gövdeyi paylaşıyoruz.
 * Tek farkı json_schema desteklememesi; json_object modunda çalışıyor.
 */
class DeepSeekProvider extends AbstractChatProvider
{
    public function name(): string
    {
        return 'deepseek';
    }

    protected function endpoint(): string
    {
        return rtrim((string) $this->config('base_url', 'https://api.deepseek.com/v1'), '/').'/chat/completions';
    }
}
