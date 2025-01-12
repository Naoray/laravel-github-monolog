# Changelog

All notable changes to `laravel-github-monolog` will be documented in this file.

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
