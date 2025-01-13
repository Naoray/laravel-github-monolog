# Changelog

All notable changes to `laravel-github-monolog` will be documented in this file.

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
