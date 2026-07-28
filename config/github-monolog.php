<?php

use Naoray\LaravelGithubMonolog\Deduplication\DefaultSignatureGenerator;

return [
    /*
    |--------------------------------------------------------------------------
    | Enable GitHub Monolog
    |--------------------------------------------------------------------------
    |
    | When enabled, the package will automatically register the 'github' logging
    | channel and configure tracing collectors. Set to false to disable all
    | automatic configuration.
    |
    */
    'enabled' => env('GITHUB_MONOLOG_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | GitHub Repository
    |--------------------------------------------------------------------------
    |
    | The GitHub repository where issues will be created. Format: owner/repo
    |
    */
    'repo' => env('GITHUB_MONOLOG_REPO'),

    /*
    |--------------------------------------------------------------------------
    | GitHub Token
    |--------------------------------------------------------------------------
    |
    | A GitHub personal access token with 'repo' scope for creating issues.
    | Not required if 'github_app' below is configured instead.
    |
    */
    'token' => env('GITHUB_MONOLOG_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | GitHub App Authentication
    |--------------------------------------------------------------------------
    |
    | As an alternative to a personal access token, you can authenticate as
    | a GitHub App installation. The package exchanges these credentials for
    | a short-lived installation access token (cached until shortly before
    | it expires). This takes precedence over 'token' above when 'id',
    | 'installation_id' and either 'private_key' or 'private_key_path' are
    | all set.
    |
    */
    'github_app' => [
        // The GitHub App ID (see the app's "General" settings page)
        'id' => env('GITHUB_MONOLOG_APP_ID'),

        // The installation ID for the app on the target account/repo
        'installation_id' => env('GITHUB_MONOLOG_APP_INSTALLATION_ID'),

        // PEM contents of the app's private key. May be base64-encoded to
        // make it easier to store as a single-line .env value.
        'private_key' => env('GITHUB_MONOLOG_APP_PRIVATE_KEY'),

        // Alternatively, a path to the private key .pem file on disk
        'private_key_path' => env('GITHUB_MONOLOG_APP_PRIVATE_KEY_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Issue Labels
    |--------------------------------------------------------------------------
    |
    | Default labels to apply to created issues.
    |
    */
    'labels' => [],

    /*
    |--------------------------------------------------------------------------
    | Log Level
    |--------------------------------------------------------------------------
    |
    | The minimum log level that will create GitHub issues.
    |
    */
    'level' => env('GITHUB_MONOLOG_LEVEL', 'error'),

    /*
    |--------------------------------------------------------------------------
    | Deduplication Settings
    |--------------------------------------------------------------------------
    |
    | Configure how duplicate errors are handled. The package uses signatures
    | to identify duplicate issues and adds comments instead of creating new ones.
    |
    */
    'deduplication' => [
        // Cache store to use for deduplication (null = default cache)
        'store' => null,

        // Cache key prefix for deduplication entries
        'prefix' => 'github-monolog:',

        // Time in seconds before an error can create a new issue (default: 1 hour)
        'time' => 3600,

        // Track how many times each error signature has been seen and include
        // the occurrence number in issue comments (default: true)
        'track_occurrences' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Buffer Settings
    |--------------------------------------------------------------------------
    |
    | Configure buffering behavior for log records.
    |
    */
    'buffer' => [
        // Maximum number of records to buffer (0 = no limit)
        'limit' => 0,

        // Whether to flush all buffered records when limit is reached
        'flush_on_overflow' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Signature Generator
    |--------------------------------------------------------------------------
    |
    | The class used to generate signatures for error grouping. You can provide
    | your own implementation by implementing SignatureGeneratorInterface.
    |
    */
    'signature_generator' => DefaultSignatureGenerator::class,

    /*
    |--------------------------------------------------------------------------
    | Tracing Configuration
    |--------------------------------------------------------------------------
    |
    | Configure what context data is collected when an error occurs.
    | Each collector can be enabled/disabled individually.
    |
    */
    'tracing' => [
        // Master switch to enable/disable all tracing
        'enabled' => true,

        // Collect environment information (Laravel version, PHP version, etc.)
        'environment' => true,

        // Collect authenticated user information
        'user' => true,

        // Collect route information (name, URI, parameters, controller)
        'route' => true,

        // Collect HTTP request data (URL, method, headers, body)
        'requests' => true,

        // Collect session data
        'session' => true,

        // Collect recent database queries
        'queries' => true,

        // Collect job context when errors occur in queued jobs
        'jobs' => true,

        // Collect command context when errors occur in artisan commands
        'commands' => true,

        // Collect outgoing HTTP request/response data
        'outgoing_requests' => true,

        // Collect Livewire component context (auto-detects Livewire requests)
        'livewire' => true,

        // Collect Inertia.js request context (auto-detects Inertia requests)
        'inertia' => true,

        // Auto-detect git information (hash, branch, tag, dirty status)
        'git' => true,

        // Collect breadcrumbs (ordered trail of events before the error)
        'breadcrumbs' => true,

        /*
        |--------------------------------------------------------------------------
        | Query Collector Settings
        |--------------------------------------------------------------------------
        */
        'query_limit' => 50,

        /*
        |--------------------------------------------------------------------------
        | Breadcrumb Collector Settings
        |--------------------------------------------------------------------------
        */
        'breadcrumb_limit' => 40,

        /*
        |--------------------------------------------------------------------------
        | Outgoing Request Collector Settings
        |--------------------------------------------------------------------------
        */
        'outgoing_request_limit' => 20,

        /*
        |--------------------------------------------------------------------------
        | Redaction Settings
        |--------------------------------------------------------------------------
        |
        | Configure which fields should be redacted from captured data.
        |
        */
        'redact' => [
            // Headers to redact (supports wildcards)
            'headers' => [
                'authorization',
                'proxy-authorization',
                'cookie',
                'x-xsrf-token',
            ],

            // Request/response payload fields to redact (supports wildcards)
            'payload_fields' => [
                'password',
                'password_confirmation',
                '_token',
                'token',
                'secret',
                'api_key',
                'api_secret',
            ],

            // Query bindings to redact
            'query_bindings' => [
                'password',
                'secret',
                'token',
            ],
        ],
    ],
];
