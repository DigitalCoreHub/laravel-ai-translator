# Changelog

All notable changes to `laravel-ai-translator` will be documented in this file.

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
