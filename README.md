# Laravel AI Translator

Find the translation keys your app is missing, and fill them in with AI.

Scans your `lang/` directory, works out which keys exist in the source locale but not in the
target ones, sends them to a translation provider in batches, and writes the results back —
without touching what you already translated.

Providers: **OpenAI**, **DeepSeek**, **DeepL**, **Google Translate**, plus a `null` provider
for dry runs. Your own provider is a single interface away.

```bash
php artisan ai:translate en tr
```

```
  en → tr: 34 anahtar
   34/34 [============================] 100%   4s

  Dosya            Eksik  Çevrilen  Hatalı  Cache  Sağlayıcı
  tr/auth.php          6         6       -      -  openai
  tr/validation.php   28        28       -      2  openai

  Toplam ................ 34 çevrildi, 0 hatalı, 2 cache, 4.1 sn
```

---

## Requirements

| | |
|---|---|
| PHP | 8.3+ |
| Laravel | 12.x, 13.x |

## Installation

```bash
composer require digitalcorehub/laravel-ai-translator
```

The service provider is auto-discovered. Publish the config if you want to tweak it:

```bash
php artisan vendor:publish --tag=ai-translator-config
```

Then set a provider and its key:

```dotenv
AI_TRANSLATOR_PROVIDER=openai
OPENAI_API_KEY=sk-...
```

That's the whole setup. Everything else has a working default.

---

## Commands

### `ai:translate` — fill in what's missing

```bash
php artisan ai:translate en tr              # en → tr
php artisan ai:translate en tr de fr        # several targets at once
php artisan ai:translate en                 # every locale in lang/ except en
php artisan ai:translate                    # source locale comes from config too
```

| Option | What it does |
|---|---|
| `--dry` | Translates and reports, writes nothing |
| `--review` | Like `--dry`, but prints `source → translation → provider` per key |
| `--force` | Re-translates keys that already have a translation |
| `--provider=deepl` | Overrides the provider for this run only |
| `--only=auth.php` | Restricts to one file or directory |
| `--queue` | Dispatches one job per file instead of translating inline |

Keys that already have a non-empty translation are left alone. Keys whose value is an empty
string are treated as missing. Non-string values (`'retry_after' => 60`) are never sent to a
provider.

The command exits with **1** if any key failed to translate, so a broken API key doesn't slip
through CI as a green build.

### `ai:translate:status` — what's missing, without spending anything

```bash
php artisan ai:translate:status en           # coverage table for every locale
php artisan ai:translate:status en tr -v     # list the missing keys
php artisan ai:translate:status en --json    # machine-readable
```

```
  Dil  Toplam  Çevrilmiş  Eksik  Kapsam
  de      412        412      -    100%
  fr      412        389     23     94%
  tr      412        412      -    100%
```

Add `--fail-on-missing` to turn it into a CI gate:

```yaml
- run: php artisan ai:translate:status en --fail-on-missing
```

### `ai:translate:cache-clear` — forget cached translations

```bash
php artisan ai:translate:cache-clear
```

Only clears this package's cache entries. Your application cache is untouched.

### `ai:watch` — translate as you write

```bash
php artisan ai:watch --from=en --to=tr
php artisan ai:watch --from=en --to=tr --once   # single pass, good for cron
```

Watches the source language files and queues a translation job whenever one changes.
Needs a queue worker running:

```bash
php artisan queue:work --queue=ai-translations
```

---

## Programmatic use

```php
use DigitalCoreHub\LaravelAiTranslator\Facades\AiTranslator;

$run = AiTranslator::from('en')->to('tr')->translate();

$run->translatedCount();   // 34
$run->failedCount();       // 0
$run->cacheHits();         // 2
$run->toArray();           // full breakdown, per file
```

Inspect before committing to anything:

```php
$missing = AiTranslator::from('en')->to('tr')->missing();

foreach ($missing as $key) {
    echo "{$key->file} · {$key->key}: {$key->source}\n";
}
```

Translate a single string:

```php
$result = AiTranslator::from('en')->to('tr')->text('Save changes');

$result->translation;   // "Değişiklikleri kaydet"
$result->provider;      // "openai"
$result->fromCache;     // false
```

Every setter returns a new instance, so a configured chain is safe to hold onto and reuse.

### HTTP endpoint

The package deliberately registers **no routes**. If you want one, it's a few lines and stays
under your own middleware, rate limits and authorisation:

```php
Route::post('/translate', function (Request $request) {
    $validated = $request->validate([
        'text' => ['required', 'string', 'max:2000'],
        'to'   => ['required', 'string', 'size:2'],
    ]);

    return AiTranslator::from(app()->getLocale())
        ->to($validated['to'])
        ->text($validated['text']);
})->middleware(['auth', 'throttle:20,1']);
```

### Events

| Event | Fired when |
|---|---|
| `TranslationRunStarted` | A run begins, with the total key count |
| `FileTranslated` | Each file finishes |
| `KeyTranslationFailed` | A key could not be translated (and was not written) |
| `TranslationRunCompleted` | The run finishes, carrying the full `TranslationRun` |

---

## How it works

### Batching

Keys are sent in batches, not one request per key. A 500-key file is a handful of requests
rather than 500. Batch sizes are per provider and configurable (`providers.*.max_batch`).

LLM providers receive the batch as id/text records and answer with a JSON object keyed by id —
via OpenAI's structured outputs where available, so the shape is enforced rather than hoped for.
DeepL and Google use their native batch endpoints.

### Placeholders are protected

`:name`, `{{ $total }}`, `<a href="…">`, `%s`, `@lang(…)` and `{count}` are swapped for neutral
tokens before translation and restored afterwards. If a provider loses a token, **the result is
discarded rather than written** — a translation with a missing `:name` is worse than no
translation. Those keys are reported as failures and the command exits non-zero.

### Fallback chain

```dotenv
AI_TRANSLATOR_PROVIDER=openai
AI_TRANSLATOR_FALLBACK=deepl,google
```

If a provider fails, the next one is asked — but only for the keys still outstanding, not the
whole batch again. Providers without an API key are skipped without being called. If every
provider fails, you get one exception listing what each of them said.

Rate limits (429) and server errors are retried with exponential backoff, honouring
`Retry-After`. Auth errors are not retried — there's no point.

### Caching

Translations are cached per provider and locale pair, so re-running a command doesn't pay for
the same string twice. `AI_TRANSLATOR_CACHE_TTL` controls the lifetime (30 days by default,
empty for forever), `AI_TRANSLATOR_CACHE_STORE` picks a specific store.

### File writing

PHP language files are written by a purpose-built exporter, not `var_export`. Output keeps the
key order of the source file, so diffs stay readable. Writes are atomic — an interrupted run
cannot leave a half-written file that fatals your app.

### TOON (optional)

If you install [`digitalcorehub/laravel-toon`](https://github.com/DigitalCoreHub/laravel-toon),
batches sent to LLM providers can be encoded as TOON tables instead of JSON — roughly 40% fewer
input tokens on large files, because the field names aren't repeated on every row:

```dotenv
AI_TRANSLATOR_PROMPT_FORMAT=toon
```

Responses still come back as JSON, so the parsing guarantees are unchanged. If the package
isn't installed, this silently falls back to JSON.

---

## Configuration

Common settings, all optional:

```dotenv
AI_TRANSLATOR_PROVIDER=openai
AI_TRANSLATOR_FALLBACK=deepl,google
AI_TRANSLATOR_SOURCE_LOCALE=en
AI_TRANSLATOR_PATHS="lang,resources/lang"

AI_TRANSLATOR_CONCURRENCY=5
AI_TRANSLATOR_CACHE_TTL=2592000

AI_TRANSLATOR_QUEUE=ai-translations
AI_TRANSLATOR_WATCH_INTERVAL=2
```

Per-project translation rules — tone, brand names, glossary — go to the model verbatim:

```dotenv
AI_TRANSLATOR_INSTRUCTIONS="Use formal 'siz'. Never translate the product name Acme."
```

### Custom providers

Implement the interface:

```php
use DigitalCoreHub\LaravelAiTranslator\Contracts\TranslationProvider;

class MyProvider implements TranslationProvider
{
    public function name(): string { return 'mine'; }

    public function translateBatch(array $texts, string $from, string $to): array
    {
        // ['key' => 'source'] in, ['key' => 'translation'] out
    }

    public function maxBatchSize(): int { return 25; }

    public function isConfigured(): bool { return true; }
}
```

Register it in `config/ai-translator.php`:

```php
'providers' => [
    'mine' => ['class' => App\Translation\MyProvider::class],
],
```

Returning fewer keys than you were given is fine — the chain hands the rest to the next
provider.

---

## Testing

```bash
composer test      # Pest
composer lint      # Pint
composer analyse   # PHPStan
```

No test touches the network. Use the `null` provider in your own tests:

```php
config()->set('ai-translator.provider', 'null');
config()->set('ai-translator.providers.null.prefix', '[tr] ');
```

---

## Upgrading from 0.x

v1.0 is a clean break — see [UPGRADE.md](UPGRADE.md).

## Contributing

Bug reports and pull requests are welcome. Please keep `composer test`, `composer lint` and
`composer analyse` green.

## License

MIT. See [LICENSE](LICENSE).
