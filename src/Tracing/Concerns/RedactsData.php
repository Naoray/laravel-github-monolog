<?php

namespace Naoray\LaravelGithubMonolog\Tracing\Concerns;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderBag;
use Throwable;

trait RedactsData
{
    /**
     * Redact sensitive headers from a HeaderBag.
     *
     * @param  array<string>  $sensitiveKeys
     * @return array<string, array<string>>
     */
    protected function redactHeaders(HeaderBag $headers, array $sensitiveKeys = []): array
    {
        $allHeaders = $headers->all();
        $sensitiveKeys = array_merge($this->getDefaultSensitiveHeaders(), $sensitiveKeys);

        return collect($allHeaders)
            ->map(function ($value, $key) use ($sensitiveKeys) {
                if ($this->isSensitiveHeader($key, $sensitiveKeys)) {
                    return $this->redactHeaderValue($key, $value);
                }

                return $value;
            })
            ->toArray();
    }

    /**
     * Redact sensitive fields from an array (e.g., request payload, query bindings).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string>  $sensitiveKeys
     * @return array<string, mixed>
     */
    protected function redactPayload(array $data, array $sensitiveKeys = []): array
    {
        $sensitiveKeys = array_merge($this->getDefaultSensitivePayloadFields(), $sensitiveKeys);

        return $this->redactArrayRecursive($data, $sensitiveKeys);
    }

    /**
     * Redact sensitive query bindings.
     *
     * @param  array<mixed>  $bindings
     * @param  array<string>  $sensitiveKeys
     * @return array<mixed>
     */
    protected function redactBindings(array $bindings, array $sensitiveKeys = []): array
    {
        $sensitiveKeys = array_merge($this->getDefaultSensitiveBindingFields(), $sensitiveKeys);

        return array_map(function ($binding) use ($sensitiveKeys) {
            if (is_string($binding) && $this->matchesSensitiveKey($binding, $sensitiveKeys)) {
                return $this->redactValue($binding);
            }

            return $binding;
        }, $bindings);
    }

    /**
     * Check if a header key is sensitive.
     */
    protected function isSensitiveHeader(string $key, array $sensitiveKeys): bool
    {
        return Str::is($sensitiveKeys, $key, true);
    }

    /**
     * Redact a header value based on its type.
     *
     * @param  array<string>  $values
     * @return array<string>
     */
    protected function redactHeaderValue(string $key, array $values): array
    {
        return array_map(function ($value) use ($key) {
            return match (strtolower($key)) {
                'authorization', 'proxy-authorization' => $this->redactAuthorizationHeaderValue((string) $value),
                'cookie' => $this->redactCookieHeaderValue((string) $value),
                default => $this->redactValue((string) $value),
            };
        }, $values);
    }

    /**
     * Redact an authorization header value, preserving the scheme.
     */
    protected function redactAuthorizationHeaderValue(string $value): string
    {
        if (! str_contains($value, ' ')) {
            return $this->redactValue($value);
        }

        [$type, $remainder] = explode(' ', $value, 2);

        $knownSchemes = [
            'basic', 'bearer', 'concealed', 'digest', 'dpop', 'gnap',
            'hoba', 'mutual', 'negotiate', 'oauth', 'privatetoken',
            'scram-sha-1', 'scram-sha-256', 'vapid',
        ];

        if (in_array(strtolower($type), $knownSchemes, true)) {
            return $type.' '.$this->redactValue($remainder);
        }

        return $this->redactValue($value);
    }

    /**
     * Redact cookie header value, preserving cookie names.
     */
    protected function redactCookieHeaderValue(string $value): string
    {
        $cookies = explode(';', $value);

        try {
            $result = implode('; ', array_map(function ($cookie) {
                if (! str_contains($cookie, '=')) {
                    throw new RuntimeException('Invalid cookie format.');
                }

                [$name, $cookieValue] = explode('=', $cookie, 2);

                return trim($name).'='.$this->redactValue($cookieValue);
            }, $cookies));

            return $result;
        } catch (Throwable) {
            return $this->redactValue($value);
        }
    }

    /**
     * Redact a value, showing byte count.
     */
    protected function redactValue(string $value): string
    {
        return '['.strlen($value).' bytes redacted]';
    }

    /**
     * Recursively redact sensitive keys from an array.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string>  $sensitiveKeys
     * @return array<string, mixed>
     */
    protected function redactArrayRecursive(array $data, array $sensitiveKeys): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $result[$key] = match (true) {
                $this->matchesSensitiveKey($key, $sensitiveKeys) => is_string($value) ? $this->redactValue($value) : '[redacted]',
                is_array($value) => $this->redactArrayRecursive($value, $sensitiveKeys),
                default => $value,
            };
        }

        return $result;
    }

    /**
     * Check if a key matches any sensitive key pattern.
     */
    protected function matchesSensitiveKey(string $key, array $sensitiveKeys): bool
    {
        return Str::is($sensitiveKeys, $key, true);
    }

    /**
     * Get default sensitive headers from config.
     *
     * @return array<string>
     */
    protected function getDefaultSensitiveHeaders(): array
    {
        $config = config('logging.channels.github.tracing.redact.headers', []);

        $defaults = [
            config('session.cookie'),
            'remember_*',
            'XSRF-TOKEN',
            'cookie',
            'authorization',
            'proxy-authorization',
        ];

        return array_unique(array_merge($defaults, $config));
    }

    /**
     * Get default sensitive payload fields from config.
     *
     * @return array<string>
     */
    protected function getDefaultSensitivePayloadFields(): array
    {
        $config = config('logging.channels.github.tracing.redact.payload_fields', []);

        $defaults = [
            'password',
            'password_confirmation',
            '_token',
            'token',
            'secret',
            'api_key',
            'api_secret',
        ];

        return array_unique(array_merge($defaults, $config));
    }

    /**
     * Get default sensitive binding fields from config.
     *
     * @return array<string>
     */
    protected function getDefaultSensitiveBindingFields(): array
    {
        $config = config('logging.channels.github.tracing.redact.query_bindings', []);

        $defaults = [
            'password',
            'secret',
            'token',
        ];

        return array_unique(array_merge($defaults, $config));
    }
}
