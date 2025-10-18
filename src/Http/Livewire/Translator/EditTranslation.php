<?php

namespace DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator;

use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use Livewire\Volt\Component;

class EditTranslation extends Component
{
    public string $path = '';
    public string $key = '';
    public string $from = '';
    public string $to = '';
    public string $source = '';
    public string $value = '';
    public string $message = '';

    public function mount(): void
    {
        $request = request();
        $this->path = (string) $request->query('path', '');
        $this->key = (string) $request->query('key', '');
        $this->from = (string) $request->query('from', '');
        $this->to = (string) $request->query('to', '');

        if ($this->path === '' || $this->key === '' || $this->to === '') {
            $this->message = 'Geçersiz çeviri parametreleri.';

            return;
        }

        $entry = $this->manager()->findEntry($this->path, $this->key, $this->from, $this->to);

        if ($entry) {
            $this->source = (string) ($entry['source'] ?? '');
            $this->value = (string) ($entry['target'] ?? '');
        }
    }

    public function render()
    {
        return view('ai-translator::livewire.translator.edit');
    }

    public function save(): void
    {
        if ($this->path === '' || $this->key === '') {
            $this->message = 'Çeviri kaydedilemedi. Parametreler eksik.';

            return;
        }

        $this->manager()->updateTranslation($this->path, $this->key, $this->value);
        $this->logManualUpdate();

        $this->message = 'Çeviri kaydedildi.';
    }

    protected function logManualUpdate(): void
    {
        $filesystem = app('files');
        $logPath = storage_path('logs/ai-translator.log');

        if (! $filesystem->isDirectory(dirname($logPath))) {
            $filesystem->makeDirectory(dirname($logPath), 0755, true);
        }

        $line = sprintf(
            '[%s] context=web action=manual path=%s key=%s locale=%s',
            now()->toDateTimeString(),
            $this->path,
            $this->key,
            $this->to
        );

        $filesystem->append($logPath, $line.PHP_EOL);
    }

    protected function manager(): TranslationManager
    {
        return app(TranslationManager::class);
    }
}
