<?php

declare(strict_types=1);

namespace DigitalCoreHub\LaravelAiTranslator\Providers;

use DigitalCoreHub\LaravelAiTranslator\Contracts\TranslationProvider;
use DigitalCoreHub\LaravelAiTranslator\Exceptions\AiTranslatorException;
use DigitalCoreHub\LaravelAiTranslator\Support\BatchPayload;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Config'teki sağlayıcı tanımlarını nesnelere çevirir.
 *
 * Kendi sağlayıcınızı eklemek için config'e "class" yazmanız yeterli:
 *
 *     'providers' => ['benim' => ['class' => App\MyProvider::class, 'api_key' => '...']],
 */
class ProviderRegistry
{
    /** @var array<string, class-string<TranslationProvider>> */
    protected const BUILT_IN = [
        'openai' => OpenAiProvider::class,
        'deepseek' => DeepSeekProvider::class,
        'deepl' => DeepLProvider::class,
        'google' => GoogleProvider::class,
        'null' => NullProvider::class,
    ];

    /** @var array<string, TranslationProvider> */
    protected array $resolved = [];

    /**
     * @param  array<string, array<string, mixed>>  $definitions
     */
    public function __construct(
        protected Container $container,
        protected array $definitions = [],
    ) {}

    /**
     * Config'te tanımlı bütün sağlayıcı adları.
     *
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->definitions);
    }

    /**
     * Kullanıma hazır (API anahtarı tanımlı) sağlayıcı adları.
     *
     * @return array<int, string>
     */
    public function configured(): array
    {
        return array_values(array_filter(
            $this->names(),
            fn (string $name) => $this->has($name) && $this->get($name)->isConfigured()
        ));
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->definitions) || array_key_exists($name, self::BUILT_IN);
    }

    public function get(string $name): TranslationProvider
    {
        return $this->resolved[$name] ??= $this->make($name);
    }

    protected function make(string $name): TranslationProvider
    {
        /** @var array<string, mixed> $definition */
        $definition = $this->definitions[$name] ?? [];

        $class = $definition['class'] ?? self::BUILT_IN[$name] ?? null;

        if (! is_string($class) || ! class_exists($class)) {
            throw new AiTranslatorException(sprintf(
                'Çeviri sağlayıcısı [%s] tanımlı değil. config/ai-translator.php içindeki '
                .'"providers" listesine ekleyin ya da "class" anahtarıyla kendi sınıfınızı verin.',
                $name
            ));
        }

        // Kendi sınıfını veren kullanıcı AbstractProvider'ı extend etmek zorunda değil;
        // o durumda container'ın normal çözümlemesine bırakıyoruz.
        $instance = is_subclass_of($class, AbstractProvider::class)
            ? $this->makeBuiltIn($class, $definition)
            : $this->container->make($class);

        if (! $instance instanceof TranslationProvider) {
            throw new AiTranslatorException(sprintf(
                '[%s] sınıfı %s arayüzünü uygulamıyor.',
                $class,
                TranslationProvider::class
            ));
        }

        return $instance;
    }

    /**
     * @param  class-string<AbstractProvider>  $class
     * @param  array<string, mixed>  $definition
     */
    protected function makeBuiltIn(string $class, array $definition): AbstractProvider
    {
        $config = $this->container->make('config');

        $arguments = [
            'http' => $this->container->make(HttpFactory::class),
            'config' => $definition,
            'retry' => [
                'times' => (int) $config->get('ai-translator.retry.times', 3),
                'sleep' => (int) $config->get('ai-translator.retry.sleep', 500),
            ],
        ];

        if (is_subclass_of($class, AbstractChatProvider::class)) {
            $arguments['payload'] = new BatchPayload(
                (string) $config->get('ai-translator.prompt_format', 'json')
            );
            $arguments['instructions'] = $config->get('ai-translator.instructions');
        }

        /** @var AbstractProvider */
        return new $class(...$arguments);
    }
}
