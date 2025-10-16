<?php

namespace DigitalCoreHub\LaravelAiTranslator\Http\Livewire\Translator;

use DigitalCoreHub\LaravelAiTranslator\Services\TranslationManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use RuntimeException;

class EditTranslation extends Component
{
    public string $from;

    public string $to;

    public string $file;

    public array $entries = [];

    public ?string $statusMessage = null;

    public ?string $statusType = null;

    protected TranslationManager $manager;

    public function mount(TranslationManager $manager, string $from, string $to, string $encoded): void
    {
        $this->manager = $manager;
        $decoded = base64_decode($encoded, true);

        if ($decoded === false) {
            throw new RuntimeException('Geçersiz dosya parametresi');
        }

        $this->from = $from;
        $this->to = $to;
        $this->file = $decoded;

        $this->loadEntries();
    }

    public function saveEntry(int $index): void
    {
        if (! isset($this->entries[$index])) {
            return;
        }

        $entry = $this->entries[$index];
        $value = (string) ($entry['target'] ?? '');

        $this->manager->updateTranslationEntry($this->to, $this->file, $entry['key'], $value);

        $this->statusMessage = 'Anahtar güncellendi: '.$entry['key'];
        $this->statusType = 'success';

        $this->logAction('Manuel çeviri kaydedildi', [
            'file' => $this->file,
            'key' => $entry['key'],
            'locale' => $this->to,
        ]);
    }

    public function saveAll(): void
    {
        foreach ($this->entries as $index => $entry) {
            $this->manager->updateTranslationEntry(
                $this->to,
                $this->file,
                $entry['key'],
                (string) ($entry['target'] ?? '')
            );
        }

        $this->statusMessage = 'Tüm değişiklikler kaydedildi';
        $this->statusType = 'success';

        $this->logAction('Dosya manuel olarak güncellendi', [
            'file' => $this->file,
            'locale' => $this->to,
        ]);
    }

    public function reload(): void
    {
        $this->loadEntries();
        $this->statusMessage = 'Çeviriler yenilendi';
        $this->statusType = 'info';
    }

    protected function loadEntries(): void
    {
        $entries = $this->manager->getFileEntries($this->from, $this->to, $this->file);
        $this->entries = array_map(function (array $entry) {
            $entry['target'] = $entry['target'] ?? '';

            return $entry;
        }, $entries);
    }

    protected function logAction(string $message, array $context = []): void
    {
        $path = storage_path('logs/ai-translator.log');
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $timestamp = Carbon::now()->format('Y-m-d H:i:s');
        $payload = $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        File::append($path, "[{$timestamp}] {$message} {$payload}".PHP_EOL);
    }

    public function render()
    {
        return view('ai-translator::livewire.translator.edit')
            ->layout('ai-translator::livewire.translator.layout');
    }
}
