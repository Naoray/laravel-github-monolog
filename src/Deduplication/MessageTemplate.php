<?php

namespace Naoray\LaravelGithubMonolog\Deduplication;

class MessageTemplate
{
    /**
     * Template a message by replacing unstable values with placeholders
     */
    public function template(string $message): string
    {
        $result = $message;

        // 1. Replace UUIDs
        $result = preg_replace(
            '/\b[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/i',
            '{uuid}',
            $result
        ) ?? $result;

        // 2. Replace emails
        $result = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '{email}', $result) ?? $result;

        // 3. Replace IPv4 addresses
        $result = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '{ip}', $result) ?? $result;

        // 4. Replace long hex tokens (hashes, access tokens, etc.) - >=16 chars
        $result = preg_replace('/\b[0-9a-f]{16,}\b/i', '{hex}', $result) ?? $result;

        // 5. Replace long numbers (IDs, timestamps) - >=6 digits
        $result = preg_replace('/\b\d{6,}\b/', '{num}', $result) ?? $result;

        // 6. Replace PHP upload tmp paths
        $result = preg_replace('/\/tmp\/php[[:alnum:]]+/', '/tmp/php{upload}', $result) ?? $result;
        $result = preg_replace('/\/var\/tmp\/php[[:alnum:]]+/', '/var/tmp/php{upload}', $result) ?? $result;
        $result = preg_replace('/\/private\/var\/tmp\/php[[:alnum:]]+/', '/private/var/tmp/php{upload}', $result) ?? $result;

        // 7. Replace quoted absolute paths (conservative, filesystem-like)
        // Only match paths that look like filesystem paths in quotes
        $result = preg_replace(
            '/"(\/[a-zA-Z0-9_\-\.\/]+(?:\.php|\.js|\.ts|\.json|\.md|\.txt|\.log|\.lock|\.env|\.ini|\.xml|\.yml|\.yaml)?)"/',
            '"{path}"',
            $result
        ) ?? $result;

        return $result;
    }
}
