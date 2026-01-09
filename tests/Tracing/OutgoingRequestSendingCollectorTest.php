<?php

use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\OutgoingRequestSendingCollector;

beforeEach(function () {
    $this->collector = new OutgoingRequestSendingCollector;
    Config::set('logging.channels.github.tracing.outgoing_requests', ['enabled' => true, 'limit' => 5]);
});

afterEach(function () {
    Context::flush();
});

it('tracks outgoing request sending', function () {
    $psrRequest = new PsrRequest('GET', 'https://api.example.com/test', ['Authorization' => 'Bearer token']);
    $request = new Request($psrRequest);

    $sendingEvent = new RequestSending($request);
    ($this->collector)($sendingEvent);

    $requestId = spl_object_hash($request);
    $requestData = Context::get("outgoing_request.{$requestId}");

    expect($requestData)->not->toBeNull();
    expect($requestData)->toHaveKeys(['url', 'method', 'headers', 'body', 'started_at']);
    expect($requestData['url'])->toBe('https://api.example.com/test');
    expect($requestData['method'])->toBe('GET');
    expect($requestData['started_at'])->toBeNumeric();
});

it('does not track when disabled', function () {
    Config::set('logging.channels.github.tracing.outgoing_requests', ['enabled' => false]);

    $psrRequest = new PsrRequest('GET', 'https://api.example.com/test');
    $request = new Request($psrRequest);

    $sendingEvent = new RequestSending($request);
    ($this->collector)($sendingEvent);

    $requestId = spl_object_hash($request);
    expect(Context::has("outgoing_request.{$requestId}"))->toBeFalse();
});
