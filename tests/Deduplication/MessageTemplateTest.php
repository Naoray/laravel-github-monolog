<?php

use Naoray\LaravelGithubMonolog\Deduplication\MessageTemplate;

beforeEach(function () {
    $this->template = new MessageTemplate;
});

test('replaces UUIDs with placeholder', function () {
    $msg1 = 'User 550e8400-e29b-41d4-a716-446655440000 failed to login';
    $msg2 = 'User 123e4567-e89b-12d3-a456-426614174000 failed to login';

    expect($this->template->template($msg1))->toBe('User {uuid} failed to login');
    expect($this->template->template($msg2))->toBe('User {uuid} failed to login');
    expect($this->template->template($msg1))->toBe($this->template->template($msg2));
});

test('replaces emails with placeholder', function () {
    $msg1 = 'Failed to send email to user@example.com';
    $msg2 = 'Failed to send email to admin@test.org';

    expect($this->template->template($msg1))->toBe('Failed to send email to {email}');
    expect($this->template->template($msg2))->toBe('Failed to send email to {email}');
    expect($this->template->template($msg1))->toBe($this->template->template($msg2));
});

test('replaces IPv4 addresses with placeholder', function () {
    $msg1 = 'Connection failed from 192.168.1.1';
    $msg2 = 'Connection failed from 10.0.0.1';

    expect($this->template->template($msg1))->toBe('Connection failed from {ip}');
    expect($this->template->template($msg2))->toBe('Connection failed from {ip}');
    expect($this->template->template($msg1))->toBe($this->template->template($msg2));
});

test('replaces long hex tokens with placeholder', function () {
    $msg1 = 'Token abcdef1234567890abcdef1234567890 is invalid';
    $msg2 = 'Token 0123456789abcdef0123456789abcdef is invalid';

    expect($this->template->template($msg1))->toBe('Token {hex} is invalid');
    expect($this->template->template($msg2))->toBe('Token {hex} is invalid');
    expect($this->template->template($msg1))->toBe($this->template->template($msg2));
});

test('replaces long numbers with placeholder', function () {
    $msg1 = 'Order 123456789 processed successfully';
    $msg2 = 'Order 987654321 processed successfully';

    expect($this->template->template($msg1))->toBe('Order {num} processed successfully');
    expect($this->template->template($msg2))->toBe('Order {num} processed successfully');
    expect($this->template->template($msg1))->toBe($this->template->template($msg2));
});

test('replaces PHP upload tmp paths', function () {
    $msg1 = 'Failed to move file from /tmp/phpABC123';
    $msg2 = 'Failed to move file from /tmp/phpXYZ789';

    expect($this->template->template($msg1))->toBe('Failed to move file from /tmp/php{upload}');
    expect($this->template->template($msg2))->toBe('Failed to move file from /tmp/php{upload}');
    expect($this->template->template($msg1))->toBe($this->template->template($msg2));
});

test('replaces var tmp PHP upload paths', function () {
    $msg1 = 'Failed to move file from /var/tmp/phpABC123';
    $msg2 = 'Failed to move file from /var/tmp/phpXYZ789';

    expect($this->template->template($msg1))->toBe('Failed to move file from /var/tmp/php{upload}');
    expect($this->template->template($msg2))->toBe('Failed to move file from /var/tmp/php{upload}');
    expect($this->template->template($msg1))->toBe($this->template->template($msg2));
});

test('replaces private var tmp PHP upload paths', function () {
    $msg1 = 'Failed to move file from /private/var/tmp/phpABC123';
    $msg2 = 'Failed to move file from /private/var/tmp/phpXYZ789';

    expect($this->template->template($msg1))->toBe('Failed to move file from /private/var/tmp/php{upload}');
    expect($this->template->template($msg2))->toBe('Failed to move file from /private/var/tmp/php{upload}');
    expect($this->template->template($msg1))->toBe($this->template->template($msg2));
});

test('preserves non-entropy text unchanged', function () {
    $msg = 'User authentication failed';
    expect($this->template->template($msg))->toBe($msg);
});

test('handles multiple replacements in same message', function () {
    $msg = 'User user@example.com (ID: 123456789) from 192.168.1.1 with token abcdef1234567890abcdef1234567890 failed';
    $result = $this->template->template($msg);

    expect($result)->toContain('{email}');
    expect($result)->toContain('{num}');
    expect($result)->toContain('{ip}');
    expect($result)->toContain('{hex}');
    expect($result)->not->toContain('user@example.com');
    expect($result)->not->toContain('123456789');
    expect($result)->not->toContain('192.168.1.1');
    expect($result)->not->toContain('abcdef1234567890abcdef1234567890');
});

test('does not replace short hex strings', function () {
    $msg = 'Error code 0x123';
    expect($this->template->template($msg))->toBe($msg);
});

test('does not replace short numbers', function () {
    $msg = 'Error code 12345';
    expect($this->template->template($msg))->toBe($msg);
});
