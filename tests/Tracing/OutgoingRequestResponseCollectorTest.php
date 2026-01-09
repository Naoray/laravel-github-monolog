<?php

use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\OutgoingRequestResponseCollector;

beforeEach(function () {
    $this->collector = new OutgoingRequestResponseCollector;
    Config::set('logging.channels.github.tracing.outgoing_requests', ['enabled' => true, 'limit' => 5]);
});

afterEach(function () {
    Context::flush();
});

it('tracks outgoing request response', function () {
    $psrRequest = new PsrRequest('GET', 'https://api.example.com/test', ['Authorization' => 'Bearer token']);
    $request = new Request($psrRequest);
    $requestId = spl_object_hash($request);

    // Simulate request sending data
    Context::add("outgoing_request.{$requestId}", [
        'url' => 'https://api.example.com/test',
        'method' => 'GET',
        'headers' => [],
        'body' => [],
        'started_at' => microtime(true) - 0.1, // 100ms ago
    ]);

    $response = Mockery::mock(Response::class);
    $response->shouldReceive('status')->andReturn(200);

    $receivedEvent = new ResponseReceived($request, $response);
    ($this->collector)($receivedEvent);

    $requests = Context::get('outgoing_requests');

    expect($requests)->toHaveCount(1);
    expect($requests[0])->toHaveKeys(['url', 'method', 'status', 'duration_ms']);
    expect($requests[0]['url'])->toBe('https://api.example.com/test');
    expect($requests[0]['method'])->toBe('GET');
    expect($requests[0]['status'])->toBe(200);
    expect($requests[0]['duration_ms'])->toBeNumeric();

    // Verify temporary request data is cleaned up
    expect(Context::has("outgoing_request.{$requestId}"))->toBeFalse();
});

it('respects request limit', function () {
    Config::set('logging.channels.github.tracing.outgoing_requests', ['enabled' => true, 'limit' => 2]);

    $response = Mockery::mock(Response::class);
    $response->shouldReceive('status')->andReturn(200);

    for ($i = 0; $i < 5; $i++) {
        $psrRequest = new PsrRequest('GET', "https://api.example.com/test{$i}");
        $request = new Request($psrRequest);
        $requestId = spl_object_hash($request);

        // Simulate request sending data
        Context::add("outgoing_request.{$requestId}", [
            'url' => "https://api.example.com/test{$i}",
            'method' => 'GET',
            'headers' => [],
            'body' => [],
            'started_at' => microtime(true),
        ]);

        ($this->collector)(new ResponseReceived($request, $response));
    }

    $requests = Context::get('outgoing_requests');

    expect($requests)->toHaveCount(2);
    expect($requests[0]['url'])->toContain('test3');
    expect($requests[1]['url'])->toContain('test4');
});

it('does not track when disabled', function () {
    Config::set('logging.channels.github.tracing.outgoing_requests', ['enabled' => false]);

    $psrRequest = new PsrRequest('GET', 'https://api.example.com/test');
    $request = new Request($psrRequest);
    $requestId = spl_object_hash($request);

    // Simulate request sending data
    Context::add("outgoing_request.{$requestId}", [
        'url' => 'https://api.example.com/test',
        'method' => 'GET',
        'headers' => [],
        'body' => [],
        'started_at' => microtime(true),
    ]);

    $response = Mockery::mock(Response::class);

    ($this->collector)(new ResponseReceived($request, $response));

    expect(Context::has('outgoing_requests'))->toBeFalse();
});
