# Changelog

All notable changes to `laravel-github-monolog` will be documented in this file.

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
