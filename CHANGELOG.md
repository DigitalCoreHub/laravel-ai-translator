# Changelog

All notable changes to `laravel-ai-translator` will be documented in this file.

## [0.5.0] - 2024-10-22
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

## [0.4.0] - 2024-08-12
### Added
- Livewire + Volt dashboard with scan, translate, and manual edit workflows.
- Provider settings screen with connection tests and multi-provider support.
- Logs & statistics page backed by JSON reports.
- REST API endpoint for machine translation requests.
