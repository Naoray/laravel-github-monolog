# Upgrade Guide

## Upgrading from 2.x to 3.0

### Breaking Changes

Version 3.0 introduces several breaking changes in how deduplication storage is handled:

1. **Removed Custom Store Implementations**
   - FileStore, RedisStore, and DatabaseStore have been removed
   - All deduplication storage now uses Laravel's cache system

2. **Configuration Changes**
   - Store-specific configuration options have been removed
   - New simplified cache-based configuration

### Migration Steps

1. **Update Package**
   ```bash
   composer require naoray/laravel-github-monolog:^3.0
   ```

2. **Run Cleanup**
   - Keep your old configuration in place
   - Run the cleanup code above to remove old storage artifacts
   - The cleanup code needs your old configuration to know what to clean up

3. **Update Configuration**
   - Migrate to new store-specific configuration
   - Add new cache-based configuration
   - Configure Laravel cache as needed

### Configuration Updates

#### Before (2.x)
- [ ] ```php
'deduplication' => [
    'store' => 'redis',              // or 'file', 'database'
    'connection' => 'default',       // Redis/Database connection
    'prefix' => 'github-monolog:',   // Redis prefix
    'table' => 'github_monolog_deduplication', // Database table
    'time' => 60,
],
```

#### After (3.0)
```php
'deduplication' => [
    'store' => null,      // (optional) Uses Laravel's default cache store
    'time' => 60,         // Time window in seconds
    'prefix' => 'dedup',  // Cache key prefix
],
```

### Cleanup Code

Before updating your configuration to the new format, you should clean up artifacts from the 2.x version. The cleanup code uses your existing configuration to find and remove old storage:

```php
use Illuminate\Support\Facades\{Schema, Redis, File, DB};

// Get your current config
$config = config('logging.channels.github.deduplication', []);
$store = $config['store'] ?? 'file';

if ($store === 'database') {
    // Clean up database table using your configured connection and table name
    $connection = $config['connection'] ?? config('database.default');
    $table = $config['table'] ?? 'github_monolog_deduplication';

    Schema::connection($connection)->dropIfExists($table);
}

if ($store === 'redis') {
    // Clean up Redis entries using your configured connection and prefix
    $connection = $config['connection'] ?? 'default';
    $prefix = $config['prefix'] ?? 'github-monolog:';
    Redis::connection($connection)->del($prefix . 'dedup');
}

if ($store === 'file') {
    // Clean up file storage using your configured path
    $path = $config['path'] ?? storage_path('logs/github-monolog-deduplication.log');
    if (File::exists($path)) {
        File::delete($path);
    }
}
```
