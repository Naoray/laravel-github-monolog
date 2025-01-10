# Laravel GitHub Issue Logger

A Laravel package that automatically creates GitHub issues from your application logs. Perfect for smaller projects where full-featured logging services like Sentry or Bugsnag might be overkill, but you still want to track bugs effectively.

When an error occurs in your application, a new GitHub issue is automatically created with detailed error information and stack trace:

<img src="https://github.com/user-attachments/assets/2fa83b7a-3020-45d9-b669-3e7e17134024" width="600" alt="issue raised">

The issue appears in your repository with all the detailed information about the error:

<img src="https://github.com/user-attachments/assets/0fe6e6d7-8ecd-4253-8c05-e8ba2025a536" width="600" alt="issue detail">

If the same error occurs again, instead of creating a duplicate, a new comment is automatically added to track the occurrence:

<img src="https://github.com/user-attachments/assets/c76fd583-63a9-49b8-a7fb-a6dcf2c00ee6" width="600" alt="comment added">

## Features

😊 Automatically creates GitHub issues from log entries&#8203;
🔍 Groups similar errors into the same issue&#8203;
💬 Adds comments to existing issues when the same error occurs again&#8203;
🏷️ Customizable labels for better organization&#8203;

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
        'driver' => 'custom',
        'via' => \Naoray\LaravelGithubMonolog\GithubIssueHandlerFactory::class,
        'level' => env('LOG_LEVEL', 'error'),
        'repo' => env('GITHUB_REPO'),    // Format: "username/repository"
        'token' => env('GITHUB_TOKEN'),  // Your GitHub Personal Access Token
        'labels' => ['bug'],            // Optional: Additional labels for issues
    ],
]
```

Add these variables to your `.env` file:

```
GITHUB_REPO=username/repository
GITHUB_TOKEN=your-github-personal-access-token
```

### Getting a GitHub Token

To obtain a Personal Access Token:

1. Go to [Generate a new token](https://github.com/settings/tokens/new?description=Laravel%20GitHub%20Issue%20Logger&scopes=repo) (this link pre-selects the required scopes)
2. Review the pre-selected scopes (you should see `repo` checked)
3. Click "Generate token"
4. Copy the token immediately (you won't be able to see it again!)
5. Add it to your `.env` file as `GITHUB_TOKEN`

> **Note**: The token needs the `repo` scope to create issues in both public and private repositories.

## Usage

Use it like any other Laravel logging channel:

```php
// Single channel
Log::channel('github')->error('Something went wrong!');

// Or as part of a stack
Log::stack(['daily', 'github'])->error('Something went wrong!');
```

Each unique error will create a new GitHub issue. If the same error occurs again, it will be added as a comment to the existing issue instead of creating a duplicate.

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
