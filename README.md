# Laravel GitHub Issue Logger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/naoray/laravel-github-monolog.svg?style=flat-square)](https://packagist.org/packages/naoray/laravel-github-monolog)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/naoray/laravel-github-monolog/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/naoray/laravel-github-monolog/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/naoray/laravel-github-monolog/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/naoray/laravel-github-monolog/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/naoray/laravel-github-monolog.svg?style=flat-square)](https://packagist.org/packages/naoray/laravel-github-monolog)

A Laravel package that automatically creates GitHub issues from your application logs. Perfect for smaller projects where full-featured logging services like Sentry or Bugsnag might be overkill, but you still want to track bugs effectively.

## Requirements

- PHP ^8.3
- Laravel ^11.0
- Monolog ^3.6

## Features

- ✨ Automatically creates GitHub issues from log entries
- 🔍 Intelligently groups similar errors into single issues
- 💬 Adds comments to existing issues for recurring errors
- 🏷️ Supports customizable labels for efficient organization
- 🎯 Smart deduplication to prevent issue spam
- ⚡️ Buffered logging for better performance

## Showcase

When an error occurs in your application, a GitHub issue is automatically created with comprehensive error information and stack trace:

<img src="https://github.com/user-attachments/assets/bd1a7e9b-e1f3-43ed-b779-14fbaa974916" width="800" alt="issue raised">

The issue appears in your repository with all the detailed information about the error:

<img src="https://github.com/user-attachments/assets/0fe6e6d7-8ecd-4253-8c05-e8ba2025a536" width="800" alt="issue detail">

If the same error occurs again, instead of creating a duplicate, a new comment is automatically added to track the occurrence:

<img src="https://github.com/user-attachments/assets/c76fd583-63a9-49b8-a7fb-a6dcf2c00ee6" width="800" alt="comment added">

## Installation

Install the package via composer:

```bash
composer require naoray/laravel-github-monolog
```

## Configuration

Add the GitHub logging channel to your `config/logging.php`:

```php
'channels' => [
    // ... other channels ...

    'github' => [
        // Required configuration
        'driver' => 'custom',
        'via' => \Naoray\LaravelGithubMonolog\GithubIssueHandlerFactory::class,
        'repo' => env('GITHUB_REPO'),    // Format: "username/repository"
        'token' => env('GITHUB_TOKEN'),  // Your GitHub Personal Access Token

        // Optional configuration
        'level' => env('LOG_LEVEL', 'error'),
        'labels' => ['bug'],
        'deduplication' => [
            'store' => storage_path('logs/github-issues-dedup.log'),  // Custom path for deduplication storage
            'time' => 60,  // Time in seconds to consider logs as duplicates
        ],
    ],
]
```

Add these variables to your `.env` file:

```
GITHUB_REPO=username/repository
GITHUB_TOKEN=your-github-personal-access-token
```

You can use the `github` log channel as your default `LOG_CHANNEL` or add it as part of your stack in `LOG_STACK`.

### Getting a GitHub Token

To obtain a Personal Access Token:

1. Go to [Generate a new token](https://github.com/settings/tokens/new?description=Laravel%20GitHub%20Issue%20Logger&scopes=repo) (this link pre-selects the required scopes)
2. Review the pre-selected scopes (the `repo` scope should be checked)
3. Click "Generate token"
4. Copy the token immediately (you won't be able to access it again after leaving the page)
5. Add it to your `.env` file as `GITHUB_TOKEN`

> **Note**: The token requires the `repo` scope to create issues in both public and private repositories.

## Usage

Whenever an exception is thrown it will be logged as an issue to your repository.

You can also use it like any other Laravel logging channel:

```php
// Single channel
Log::channel('github')->error('Something went wrong!');

// Or as part of a stack
Log::stack(['daily', 'github'])->error('Something went wrong!');
```

### Deduplication

The package includes smart deduplication to prevent your repository from being flooded with duplicate issues:

1. **Time-based Deduplication**: Similar errors within the configured time window (default: 60 seconds) are considered duplicates
2. **Intelligent Grouping**: Uses error signatures to group similar errors, even if the exact details differ
3. **Automatic Storage**: Deduplication data is automatically stored in your Laravel logs directory
4. **Configurable**: Customize both the storage location and deduplication time window

For example, if your application encounters the same error multiple times in quick succession:
- First occurrence: Creates a new GitHub issue
- Subsequent occurrences within the deduplication window: No new issues created
- After the deduplication window: Creates a new issue or adds a comment to the existing one

This helps keep your GitHub issues organized and prevents notification spam during error storms.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Krishan Koenig](https://github.com/Naoray)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
