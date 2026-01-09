<?php

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\RequestDataCollector;

beforeEach(function () {
    $this->collector = new RequestDataCollector;
});

afterEach(function () {
    Context::flush();
});

it('collects request data', function () {
    // Arrange
    $request = Request::create('https://example.com/test?foo=bar', 'POST', ['key' => 'value']);
    $request->headers->set('accept', 'application/json');
    $request->headers->set('cookie', 'sensitive-cookie');
    $request->headers->set('x-custom', 'custom-value');
    $request->headers->set('content-length', '1024');

    $event = new RequestHandled($request, Mockery::mock('Illuminate\Http\Response'));

    // Act
    ($this->collector)($event);

    // Assert
    $requestData = Context::get('request');
    expect($requestData)->toHaveKeys(['url', 'full_url', 'method', 'ip', 'headers', 'cookies', 'query', 'body']);
    expect($requestData['url'])->toBe('https://example.com/test');
    expect($requestData['full_url'])->toBe('https://example.com/test?foo=bar');
    expect($requestData['method'])->toBe('POST');
});

it('filters sensitive headers', function () {
    // Arrange
    $request = Request::create('https://example.com/test', 'GET');
    $request->headers->set('authorization', 'Bearer secret-token');
    $request->headers->set('cookie', 'session=abc123');
    $request->headers->set('safe-header', 'value');

    $event = new RequestHandled($request, Mockery::mock('Illuminate\Http\Response'));

    // Act
    ($this->collector)($event);

    // Assert
    $requestData = Context::get('request');
    expect($requestData['headers']['authorization'][0])->toContain('Bearer');
    expect($requestData['headers']['authorization'][0])->toContain('bytes redacted');
    expect($requestData['headers']['safe-header'][0])->toBe('value');
});
