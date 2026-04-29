# Changelog

All notable changes to `laravel-github-monolog` will be documented in this file.

## Unreleased

### What's Changed

* Add Laravel 13 support

## v3.8.0 - 2026-02-08

### What's Changed

#### New Features

* **feat(tracing): add breadcrumbs/event trail system** (#50) - Capture an ordered trail of log messages and cache events leading up to an error, similar to Sentry/Flare breadcrumbs. Configurable ring buffer (default 40 entries), formatted as markdown tables in GitHub issues.
* **feat(tracing): auto-detect git information** (#48) - Automatically detect git hash, branch, tag, and dirty status using Laravel's `Process` facade. `config('app.git_commit')` still works as an override. New `tracing.git` config toggle.
* **feat(deduplication): add occurrence counter** (#49) - Track how many times each error signature has been seen. Comments on duplicate issues now show `Occurrence: #N`. New `deduplication.track_occurrences` config option.
* **feat(livewire): capture component state from snapshots** (#45) - Extract component `data` from Livewire snapshot payloads with truncation limits (50 keys, 8KB). Sensitive values are automatically redacted.
* **feat(livewire): capture method parameters and update values** (#43) - Methods now include their `params`, and property updates preserve full key-value pairs instead of just keys.

#### Bug Fixes

* **fix(formatters): strip duplicate keys from Extra Data section** (#41) - `ExtraFormatter` now excludes keys that have dedicated sections (environment, request, user, etc.) to avoid duplicate output.
* **fix(templates): un-nest stack trace details in comment template** (#47) - Fix `<details>` blocks in comment template to be siblings instead of nested, matching the issue template structure.
* **fix(tracing): truncate serialized job payload data** (#44) - Long serialized strings in job payloads are now truncated to 500 characters using `Str::limit()`.
* **fix(session): strip empty flash data and token** (#42) - Remove `_token` and empty `_flash` from session data. Flash data only appears when non-empty. Uses `Arr::except()` for cleaner filtering.

**Full Changelog**: https://github.com/Naoray/laravel-github-monolog/compare/v3.7.0...v3.8.0

## v3.7.0 - 2026-01-27

### What's Changed

#### New Features

* **feat(tracing): add Livewire and Inertia.js support** - Add request-based detection for Livewire v3+ and Inertia.js requests, capturing component data for enhanced debugging context.

  **Key Design Decisions:**
  - **Request-based detection** - No package dependencies required. Collectors detect Livewire/Inertia requests by examining headers and request payload.
  - **Livewire v3+ only** - Targets Livewire v3+ request structure only.
  - **Per-request accuracy** - Only captures data when the request is actually Livewire/Inertia.

  **Livewire Data Captured:**
  - Component name, id, path
  - Methods called (e.g., `save`, `delete`)
  - Updated properties (wire:model bindings)
  - Originating page URL

  **Inertia Data Captured:**
  - Component name (Vue/React page)
  - Inertia version
  - Partial reload status
  - Partial data keys requested/excluded
  - Request URL

  **Changes:**
  - Add `LivewireDataCollector` with request-based detection
  - Add `InertiaDataCollector` with request-based detection
  - Add `ResolvesTracingConfig` trait for consistent config resolution
  - Update `EventHandler` to support multiple collectors per event
  - Add Livewire and Inertia sections to issue templates
  - Add comprehensive default configuration file
  - Enhance `RouteDataCollector` with Livewire route detection
  - Enhance `UserDataCollector` with logout handling and caching

**Full Changelog**: https://github.com/Naoray/laravel-github-monolog/compare/v3.6.1...v3.7.0

## v3.6.1 - 2026-01-27

### What's Changed

#### Bug Fixes

* **fix: prevent large context data from being serialized into job payloads** - Use `Context::addHidden()` for large tracing data (queries, outgoing_requests, session, request) to prevent serialization into Laravel job payloads.
  
  Hidden context is available during the request for error logging but is NOT serialized when jobs are queued, preventing ~6.8MB job payloads and Redis OOM errors.
  
  **Changes:**
  
  - `QueryCollector`: use hidden context for queries
  - `OutgoingRequestSendingCollector`: use hidden context for request tracking
  - `OutgoingRequestResponseCollector`: use hidden context for outgoing requests
  - `SessionCollector`: use hidden context for session data
  - `RequestDataCollector`: use hidden context for request data
  - `ContextProcessor`: merge both regular and hidden context for logging
  - `GithubMonologServiceProvider`: add dehydration callback as safety net
  

**Full Changelog**: https://github.com/Naoray/laravel-github-monolog/compare/v3.6.0...v3.6.1

## v3.6.0 - 2026-01-11

### What's Changed

* fix(issues): correct stack trace formatting and section mapping by @Naoray in https://github.com/Naoray/laravel-github-monolog/pull/27

**Full Changelog**: https://github.com/Naoray/laravel-github-monolog/compare/v3.5.0...v3.6.0

## v3.5.0 - 2026-01-11

### What's Changed

* feat(deduplication): add signature context extraction and message templating by @Naoray in https://github.com/Naoray/laravel-github-monolog/pull/26

**Full Changelog**: https://github.com/Naoray/laravel-github-monolog/compare/v3.4.2...v3.5.0

## v3.4.2 - 2026-01-10

### What's Changed

* fix: handle deleted temporary files and improve error deduplication by @Naoray in https://github.com/Naoray/laravel-github-monolog/pull/25

**Full Changelog**: https://github.com/Naoray/laravel-github-monolog/compare/v3.4.1...v3.4.2

## v3.4.1 - 2026-01-10

### What's Changed

* fixes file in request data collector by @Naoray in https://github.com/Naoray/laravel-github-monolog/pull/24

**Full Changelog**: https://github.com/Naoray/laravel-github-monolog/compare/v3.4.0...v3.4.1

## v3.4.0 - 2026-01-09

### What's Changed

* Bump dependabot/fetch-metadata from 2.3.0 to 2.4.0 by @dependabot[bot] in https://github.com/Naoray/laravel-github-monolog/pull/16
* Bump aglipanci/laravel-pint-action from 2.5 to 2.6 by @dependabot[bot] in https://github.com/Naoray/laravel-github-monolog/pull/18
* Bump actions/checkout from 4 to 6 by @dependabot[bot] in https://github.com/Naoray/laravel-github-monolog/pull/21
* Bump stefanzweifel/git-auto-commit-action from 5 to 7 by @dependabot[bot] in https://github.com/Naoray/laravel-github-monolog/pull/20
* feat(tracing): add context collectors and processor for enhanced logging by @Naoray in https://github.com/Naoray/laravel-github-monolog/pull/22
* refactor(templates): restructure issue and comment templates with triage header by @Naoray in https://github.com/Naoray/laravel-github-monolog/pull/23

**Full Changelog**: https://github.com/Naoray/laravel-github-monolog/compare/v3.3.0...v3.4.0

## v3.3.0 - 2025-03-26

### What's Changed

* Feat/improve stack trace formatting by @Naoray in https://github.com/Naoray/laravel-github-monolog/pull/14

**Full Changelog**: https://github.com/Naoray/laravel-github-monolog/compare/v3.2.1...v3.3.0

## v3.2.1 - 2025-03-21

### What's Changed

* Fix/min version requirement by @Naoray in https://github.com/Naoray/laravel-github-monolog/pull/13

**Full Changelog**: https://github.com/Naoray/laravel-github-monolog/compare/v3.2.0...v3.2.1

## v3.2.0 - 2025-03-21

### What's Changed

* feat: add tracing capabilities by @Naoray in https://github.com/Naoray/laravel-github-monolog/pull/12

**Full Changelog**: https://github.com/Naoray/laravel-github-monolog/compare/v3.1.0...v3.2.0

## v3.1.0 - 2025-03-20

### What's Changed

* fix: TypeError: DeduplicationHandler::__construct(): Argument #3 ($store) must be of type string, null given by @andrey-helldar in https://github.com/Naoray/laravel-github-monolog/pull/10
* feat: enhance templates by @Naoray in https://github.com/Naoray/laravel-github-monolog/pull/11

**Full Changelog**: https://github.com/Naoray/laravel-github-monolog/compare/v3.0.0...v3.1.0

## v3.0.0 - 2025-02-28

### What's Changed

* remove custom store implementation in favor of laravel's cache
* add customizable stubs
* Added Laravel 12 support by @andrey-helldar in https://github.com/Naoray/laravel-github-monolog/pull/7

s. [UPGRADE.md](https://github.com/Naoray/laravel-github-monolog/blob/main/UPGRADE.md) for an upgrade guide as this release includes a few breaking changes.

### New Contributors

* @andrey-helldar made their first contribution in https://github.com/Naoray/laravel-github-monolog/pull/7

**Full Changelog**: https://github.com/Naoray/laravel-github-monolog/compare/v2.1.1...v3.0.0

## v2.1.1 - 2025-01-13

- fix wrong array key being used for deduplication stores (before `driver`, now `store`)
- fix table config not being passed on to `DatabaseStore`

**Full Changelog**: https://github.com/Naoray/laravel-github-monolog/compare/v2.1.0...v2.1.1

## v2.1.0 - 2025-01-13

### What's Changed

* Feature/added deduplication stores by @Naoray in https://github.com/Naoray/laravel-github-monolog/pull/2

**Full Changelog**: https://github.com/Naoray/laravel-github-monolog/compare/v2.0.1...v2.1.0

## v2.0.1 - 2025-01-12

- include context in reports no matter if it's an exception being reported or just a log

**Full Changelog**: https://github.com/Naoray/laravel-github-monolog/compare/v2.0.0...v2.0.1

## v2.0.0 - 2025-01-12

- drop support for Laravel 10 / Monolog < 3.6.0

**Full Changelog**: https://github.com/Naoray/laravel-github-monolog/compare/v1.1.0...v2.0.0

## v1.1.0 - 2025-01-12

- Use our own `SignatureDeduplicationHandler` to properly handle duplicated issues before submitting them to the `IssueLogHandler`
- restructure codebase

**Full Changelog**: https://github.com/Naoray/laravel-github-monolog/compare/v1.0.0...v1.1.0

## v1.0.0 - Initial Release 🚀 - 2025-01-10

- ✨ Automatically creates GitHub issues from log entries
  
- 🔍 Intelligently groups similar errors into single issues
  
- 💬 Adds comments to existing issues for recurring errors
  
- 🏷️ Supports customizable labels for efficient organization
  
- 🎯 Smart deduplication to prevent issue spam
  
  - Time-based deduplication (configurable window)
  - Prevents duplicate issues during error storms
  - Automatic storage management
  
- ⚡️ Buffered logging for better performance
  

**Full Changelog**: https://github.com/Naoray/laravel-github-monolog/commits/v1.0.0
