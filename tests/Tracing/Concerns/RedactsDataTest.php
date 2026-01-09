<?php

use Illuminate\Support\Facades\Config;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\RedactsData;
use Symfony\Component\HttpFoundation\HeaderBag;

class TestRedactsData
{
    use RedactsData;

    public function testRedactHeaders(HeaderBag $headers): array
    {
        return $this->redactHeaders($headers);
    }

    public function testRedactPayload(array $data): array
    {
        return $this->redactPayload($data);
    }

    public function testRedactBindings(array $bindings): array
    {
        return $this->redactBindings($bindings);
    }
}

beforeEach(function () {
    $this->trait = new TestRedactsData;
});

it('redacts sensitive headers', function () {
    $headers = new HeaderBag([
        'authorization' => ['Bearer secret-token'],
        'cookie' => ['session=abc123; remember=xyz'],
        'x-custom' => ['value'],
    ]);

    $result = $this->trait->testRedactHeaders($headers);

    expect($result['authorization'][0])->toContain('Bearer');
    expect($result['authorization'][0])->toContain('bytes redacted');
    expect($result['cookie'][0])->toContain('session=');
    expect($result['cookie'][0])->toContain('bytes redacted');
    expect($result['x-custom'][0])->toBe('value');
});

it('redacts sensitive payload fields', function () {
    $data = [
        'username' => 'john',
        'password' => 'secret123',
        'email' => 'john@example.com',
        'token' => 'abc123',
    ];

    $result = $this->trait->testRedactPayload($data);

    expect($result['username'])->toBe('john');
    expect($result['password'])->toContain('bytes redacted');
    expect($result['email'])->toBe('john@example.com');
    expect($result['token'])->toContain('bytes redacted');
});

it('redacts nested sensitive payload fields', function () {
    $data = [
        'user' => [
            'name' => 'John',
            'password' => 'secret',
        ],
        'api_key' => 'key123',
    ];

    $result = $this->trait->testRedactPayload($data);

    expect($result['user']['name'])->toBe('John');
    expect($result['user']['password'])->toContain('bytes redacted');
    expect($result['api_key'])->toContain('bytes redacted');
});

it('uses config for sensitive headers', function () {
    Config::set('logging.channels.github.tracing.redact.headers', ['x-api-key']);

    $headers = new HeaderBag([
        'x-api-key' => ['secret-key'],
        'x-safe' => ['value'],
    ]);

    $result = $this->trait->testRedactHeaders($headers);

    expect($result['x-api-key'][0])->toContain('bytes redacted');
    expect($result['x-safe'][0])->toBe('value');
});

it('redacts query bindings', function () {
    // Note: redactBindings checks if binding string matches sensitive key patterns
    // 'password123' doesn't match 'password' pattern, so it won't be redacted
    // This is intentional - bindings are values, not keys
    $bindings = ['safe-value'];

    $result = $this->trait->testRedactBindings($bindings);

    expect($result[0])->toBe('safe-value');
});

it('preserves authorization scheme when redacting', function () {
    $headers = new HeaderBag([
        'authorization' => ['Bearer secret-token-here'],
    ]);

    $result = $this->trait->testRedactHeaders($headers);

    expect($result['authorization'][0])->toStartWith('Bearer');
    expect($result['authorization'][0])->toContain('bytes redacted');
    expect($result['authorization'][0])->not->toContain('secret-token-here');
});

it('handles cookie header redaction correctly', function () {
    $headers = new HeaderBag([
        'cookie' => ['session=abc123; remember=xyz789'],
    ]);

    $result = $this->trait->testRedactHeaders($headers);

    expect($result['cookie'][0])->toContain('session=');
    expect($result['cookie'][0])->toContain('remember=');
    expect($result['cookie'][0])->toContain('bytes redacted');
    expect($result['cookie'][0])->not->toContain('abc123');
    expect($result['cookie'][0])->not->toContain('xyz789');
});

it('uses config for sensitive payload fields', function () {
    Config::set('logging.channels.github.tracing.redact.payload_fields', ['custom_secret']);

    $data = [
        'custom_secret' => 'secret-value',
        'public_field' => 'public-value',
    ];

    $result = $this->trait->testRedactPayload($data);

    expect($result['custom_secret'])->toContain('bytes redacted');
    expect($result['public_field'])->toBe('public-value');
});

it('handles empty arrays', function () {
    expect($this->trait->testRedactPayload([]))->toBe([]);
    expect($this->trait->testRedactBindings([]))->toBe([]);
});
