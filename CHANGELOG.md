# Changelog

All notable changes to `laravel-github-monolog` will be documented in this file.

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
