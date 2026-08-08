# Upgrading to 1.0

v1.0 is a rewrite. The CLI does the same job and does it better, but names have changed and the
web panel is gone. Budget about ten minutes.

---

## Why the break

Several parts of 0.x did not work, and some of them damaged data:

- **PHP language files were being corrupted.** The writer ran
  `str_replace(['array (', ')'], ['[', ']'], var_export(...))`, which also replaced parentheses
  *inside translation strings*: `'Save (required)'` was written to disk as `'Save (required]'`.
- **`ai:sync` wrote to the wrong paths.** It produced paths relative to the language root but
  resolved them against the project root, so it looked for `base_path('en/auth.php')`. The
  queue-backed path never worked.
- **Clearing the translation cache flushed the entire application cache.**
- **Queue configuration was ignored.** Jobs went to the `default` queue while the docs told you
  to run `queue:work --queue=ai-translations`.
- **The Livewire panel never rendered.** Routes returned component instances instead of
  responses, and the package shipped fake `Livewire\Component` stubs into your application via
  `autoload.files`.
- **The test suite never ran** — there was no `phpunit.xml`.

Fixing these while keeping the old names would have meant carrying the old shape around. So:
clean cut.

---

## Requirements

| | 0.x | 1.0 |
|---|---|---|
| PHP | 8.3 | 8.3+ |
| Laravel | 11, 12 | 12, 13 |

`openai-php/client` is no longer a dependency — every provider now talks over
`Illuminate\Http`. You can remove it if nothing else in your app uses it.

---

## Commands

| 0.x | 1.0 |
|---|---|
| `ai:translate en tr` | `ai:translate en tr` — unchanged |
| `ai:translate --cache-clear` | `ai:translate:cache-clear` |
| `ai:sync en tr` | `ai:translate en tr` |
| `ai:sync en tr --queue` | `ai:translate en tr --queue` |
| `ai:watch --to=tr` | `ai:watch --to=tr` — unchanged |
| — | `ai:translate:status` (new) |

`--dry`, `--force`, `--provider=` and `--review` all still mean what they meant.

**New:** `ai:translate` now exits with `1` when keys fail to translate. If a script of yours
relied on it always returning `0`, adjust it — or treat the new behaviour as the bug report it is.

---

## Configuration

Republish and re-apply your settings; the file was reorganised:

```bash
php artisan vendor:publish --tag=ai-translator-config --force
```

| 0.x | 1.0 |
|---|---|
| `provider` | `provider` |
| — | `fallback` (was hard-coded to `openai,deepl,google`) |
| `providers.openai.model` | same |
| `cache_enabled` | `cache.enabled` |
| `cache_driver` | `cache.store` |
| — | `cache.ttl` |
| `paths` | `paths` |
| `queue_connection` | `queue.connection` |
| `queue_name` | `queue.name` |
| `queue_timeout` | `queue.timeout` |
| `queue_max_concurrent` | `concurrency` |
| `watch_interval` | `watch.interval` |
| `watch_targets` | `watch.locales` |
| `watch_paths` | *removed* — `paths` is used |
| `auto_create_missing_files` | *removed* — always on |
| `auth_enabled`, `authorized_emails` | *removed* with the panel |
| `middleware`, `api_middleware`, `api_auth` | *removed* with the panel |

Environment variables:

| 0.x | 1.0 |
|---|---|
| `AI_TRANSLATOR_CACHE_DRIVER` | `AI_TRANSLATOR_CACHE_STORE` |
| `AI_TRANSLATOR_QUEUE` (connection) | `AI_TRANSLATOR_QUEUE_CONNECTION` |
| `AI_TRANSLATOR_QUEUE_NAME` | `AI_TRANSLATOR_QUEUE` |
| `AI_TRANSLATOR_QUEUE_CONCURRENCY` | `AI_TRANSLATOR_CONCURRENCY` |
| `AI_TRANSLATOR_WATCH_TARGETS` | `AI_TRANSLATOR_WATCH_LOCALES` |
| `OPENAI_MODEL` | `AI_TRANSLATOR_OPENAI_MODEL` |
| `DEEPSEEK_API_BASE` | `AI_TRANSLATOR_DEEPSEEK_BASE_URL` |
| `AI_TRANSLATOR_AUTH_ENABLED` | *removed* |
| `AI_TRANSLATOR_AUTHORIZED_EMAILS` | *removed* |
| `AI_TRANSLATOR_API_AUTH` | *removed* |

The old `authorized_emails` default shipped two `@digitalcorehub.com` addresses, which meant
nobody else could open the panel on a fresh install. Deleting the panel took that with it.

---

## The web panel

Removed. The Livewire/Volt panel, its routes (`/ai-translator/*`), the
`EnsureAiTranslatorAccess` middleware and the published Blade views are all gone.

It will come back as a separate `laravel-ai-translator-ui` package, so the core stays free of
Livewire and Blade. If you published the views, delete them:

```bash
rm -rf resources/views/vendor/ai-translator
```

## The `POST /api/translate` route

Removed. It claimed a very generic path in every application that installed the package and
assumed Sanctum. Write your own — it's short, and the middleware is yours:

```php
use DigitalCoreHub\LaravelAiTranslator\Facades\AiTranslator;

Route::post('/translate', function (Request $request) {
    $validated = $request->validate([
        'text' => ['required', 'string', 'max:2000'],
        'to'   => ['required', 'string', 'size:2'],
    ]);

    return AiTranslator::from(app()->getLocale())->to($validated['to'])->text($validated['text']);
})->middleware(['auth:sanctum', 'throttle:20,1']);
```

---

## Classes

If you referenced package classes directly:

| 0.x | 1.0 |
|---|---|
| `Services\TranslationManager` | `Translation\Translator` (or the `AiTranslator` facade) |
| `Services\TranslationCache` | `Cache\TranslationCache` |
| `Services\TranslationWatcher` | `Translation\TranslationWatcher` |
| `Jobs\ProcessTranslationJob` | `Jobs\TranslateFileJob` |
| `Support\QueueMonitor` | *removed* — it was never called by anything |
| `Support\AiTranslatorLogger` | *removed* — use `log_channel` config |
| `Contracts\TranslationProvider` | same namespace, new signature (see below) |

Custom providers now implement batching:

```php
// 0.x
public function translate(string $text, ?string $from = null, ?string $to = null): string;

// 1.0
public function name(): string;
public function translateBatch(array $texts, string $from, string $to): array;  // ['k' => 'src'] → ['k' => 'çeviri']
public function maxBatchSize(): int;
public function isConfigured(): bool;
```

Extend `Providers\AbstractProvider` to get the shared HTTP client, retry policy and config
accessor for free.

---

## Reports and logs

0.x wrote to several places at once and read from others — `storage/logs/ai-translator*.log`,
`storage/logs/ai-translator-report.json`, `storage/logs/ai-translator-queue.json`, plus
`Storage::append()` into `storage/app`. That is why the panel's log pages were always empty.

1.0 writes one report file, capped at the last 50 runs:

```php
'report' => [
    'path' => storage_path('logs/ai-translator-report.json'),
    'keep' => 50,
],
```

Everything else goes through your configured log channel (`ai-translator.log_channel`, or the
application default). The old log files are safe to delete.

---

## After upgrading

Re-run a translation pass with `--force` on files written by 0.x. Any string containing a
closing parenthesis may have been corrupted on disk:

```bash
php artisan ai:translate:status en           # see where you stand
php artisan ai:translate en tr --force --dry # check what would change
```

Or find the damage directly:

```bash
grep -rn "([^)]*\]" lang/
```
