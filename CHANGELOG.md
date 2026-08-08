# Changelog

All notable changes to `laravel-ai-translator` will be documented in this file.

## [1.0.0] - 2026-08-08

A rewrite. See [UPGRADE.md](UPGRADE.md) for the migration path.

### Fixed
- **PHP language files were being corrupted on write.** The old writer ran
  `str_replace(['array (', ')'], ['[', ']'])` over `var_export()` output, which also replaced
  parentheses inside translation strings — `'Save (required)'` was written as `'Save (required]'`.
  Replaced with a purpose-built exporter, covered by regression tests.
- **`ai:sync` and the queue path wrote to the wrong locations.** Relative paths were produced
  against the language root but resolved against the project root. Path resolution now lives in
  one place and is guarded against escaping the language root.
- **Clearing the translation cache flushed the entire application cache.** Cache entries are now
  namespaced and invalidated by version bump; nothing else is touched.
- **Queue configuration was ignored** — jobs went to the `default` queue regardless of
  `queue_name`/`queue_connection`.
- **Failed placeholder restoration silently wrote untranslated source text** into the target
  file. Such keys are now reported as failures and left unwritten.
- **DeepL paid API keys never worked** — every request went to the free endpoint.
- **`DeepSeekProvider` called `env()` at runtime**, breaking after `config:cache`.
- **The test suite never ran** — `phpunit.xml` was missing.

### Added
- Batch translation: keys are sent in provider-sized batches instead of one request per key.
- Per-key fallback — a second provider is only asked for the keys still outstanding.
- Retry with exponential backoff on 429/5xx, honouring `Retry-After`.
- `ai:translate:status` with `--json` and `--fail-on-missing` for CI gating.
- `AiTranslator` facade with a fluent, immutable API.
- Events: `TranslationRunStarted`, `FileTranslated`, `KeyTranslationFailed`,
  `TranslationRunCompleted`.
- `null` provider for offline dry runs and testing.
- Optional TOON encoding of LLM batch payloads via `digitalcorehub/laravel-toon`.
- `--only` filter, `instructions` config for project-specific translation rules.
- Atomic file writes; key order follows the source file.
- CI matrix across PHP 8.3/8.4 × Laravel 12/13, plus Pint and PHPStan level 6.

### Changed
- Requires PHP 8.3+ and Laravel 12 or 13.
- `openai-php/client` dropped; all providers use `Illuminate\Http`.
- `ai:sync` merged into `ai:translate --queue`.
- `ai:translate --cache-clear` split out as `ai:translate:cache-clear`.
- `ai:translate` exits non-zero when keys fail to translate.
- Config restructured into nested groups (`cache.*`, `queue.*`, `watch.*`, `report.*`).
- Custom providers now implement `translateBatch()`.

### Removed
- The Livewire/Volt web panel, its routes, middleware and views — returning as a separate
  `laravel-ai-translator-ui` package.
- `src/Support/livewire-stubs.php`, which injected fake `Livewire\Component` classes into
  consuming applications through `autoload.files`.
- The auto-registered `POST /api/translate` route and its Sanctum assumption.
- `authorized_emails` config, whose default allowed only two `@digitalcorehub.com` addresses.
- `QueueMonitor` (dead code) and `AiTranslatorLogger` (replaced by a configurable log channel).

## [0.6.0] - 2025-12-09
### Added
- **Watch Mode** (`php artisan ai:watch`) — monitors `lang/` and `resources/lang/` directories for PHP/JSON file changes and automatically dispatches `ProcessTranslationJob` to the queue
- **Queue-backed Translation Jobs** — `ProcessTranslationJob` handles background translation processing with configurable timeout, retry logic, and comprehensive logging
- **Sync Command** (`php artisan ai:sync`) — bulk translation with both direct and queue modes, supporting multiple target languages and force retranslation
- **Real-time Queue Dashboard** — Livewire component with auto-refresh showing job progress, completion status, and recent job history
- **Sync Management Panel** — Livewire interface for bulk translation operations with file scanning and progress tracking
- **Watch Logs Viewer** — Livewire component for viewing file change logs with filtering, pagination, and auto-refresh
- **Comprehensive Logging System** — separate log files for watch events (`ai-translator-watch.log`), sync operations (`ai-translator-sync.log`), and job reports (`ai-translator-report.json`)
- **Enhanced Configuration** — new settings for watch paths, queue behavior, timeout values, and retry policies
- **Updated Navigation** — new menu items for Sync, Queue Status, and Watch Logs in the web panel

### Changed
- **Service Provider** — registered new commands and Livewire components
- **Configuration File** — added watch and queue settings with detailed documentation
- **Web Panel** — enhanced with new pages and real-time monitoring capabilities
- **Logging Architecture** — improved with structured logging and better error handling

### Technical Details
- New services: `TranslationWatcher`, `ProcessTranslationJob`
- New commands: `ai:watch`, `ai:sync`
- New Livewire components: `QueueStatus`, `Sync`, `WatchLogs`
- Enhanced test coverage for watch mode, queue processing, and sync operations

## [0.5.0] - 2025-10-22
### Added
- Secure `/ai-translator` panel access guarded by Laravel `auth` middleware and the new `EnsureAiTranslatorAccess` middleware.
- Livewire login page with configurable credentials and authorized e-mail allow-list.
- Session-based logout endpoint with audit logging stored in `storage/logs/ai-translator.log`.
- Optional `auth:sanctum` protection for the `/api/translate` endpoint.
- Dedicated 403 error view for unauthorized access attempts.

### Changed
- Panel layout now surfaces the signed-in user and logout controls across dashboard, settings, logs, and edit screens.
- Configuration file exposes new authentication and API security options.
- README updated with v0.5 instructions, environment variables, and highlights.

## [0.4.0] - 2025-08-12
### Added
- Livewire + Volt dashboard with scan, translate, and manual edit workflows.
- Provider settings screen with connection tests and multi-provider support.
- Logs & statistics page backed by JSON reports.
- REST API endpoint for machine translation requests.
