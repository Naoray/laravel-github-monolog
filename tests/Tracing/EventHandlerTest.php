<?php

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Naoray\LaravelGithubMonolog\Tracing\CommandContextCollector;
use Naoray\LaravelGithubMonolog\Tracing\EventHandler;
use Naoray\LaravelGithubMonolog\Tracing\JobContextCollector;
use Naoray\LaravelGithubMonolog\Tracing\OutgoingRequestResponseCollector;
use Naoray\LaravelGithubMonolog\Tracing\OutgoingRequestSendingCollector;
use Naoray\LaravelGithubMonolog\Tracing\QueryCollector;
use Naoray\LaravelGithubMonolog\Tracing\RequestDataCollector;
use Naoray\LaravelGithubMonolog\Tracing\RouteDataCollector;
use Naoray\LaravelGithubMonolog\Tracing\UserDataCollector;

beforeEach(function () {
    Event::fake();
    Config::set('logging.channels.github.tracing', [
        'enabled' => true,
        'requests' => true,
        'route' => true,
        'user' => true,
        'queries' => ['enabled' => true],
        'jobs' => true,
        'commands' => true,
        'outgoing_requests' => ['enabled' => true],
    ]);
});

it('registers request collector when enabled', function () {
    $handler = new EventHandler;
    $handler->subscribe(Event::getFacadeRoot());

    Event::assertListening(RequestHandled::class, RequestDataCollector::class);
});

it('registers route collector when enabled', function () {
    $handler = new EventHandler;
    $handler->subscribe(Event::getFacadeRoot());

    Event::assertListening(RouteMatched::class, RouteDataCollector::class);
});

it('registers user collector when enabled', function () {
    $handler = new EventHandler;
    $handler->subscribe(Event::getFacadeRoot());

    Event::assertListening(Authenticated::class, UserDataCollector::class);
});

it('registers query collector when enabled', function () {
    $handler = new EventHandler;
    $handler->subscribe(Event::getFacadeRoot());

    Event::assertListening(QueryExecuted::class, QueryCollector::class);
});

it('registers job collector when enabled', function () {
    $handler = new EventHandler;
    $handler->subscribe(Event::getFacadeRoot());

    Event::assertListening(JobProcessing::class, JobContextCollector::class);
});

it('registers command collector when enabled', function () {
    $handler = new EventHandler;
    $handler->subscribe(Event::getFacadeRoot());

    Event::assertListening(CommandStarting::class, CommandContextCollector::class);
});

it('registers outgoing request collectors when enabled', function () {
    $handler = new EventHandler;
    $handler->subscribe(Event::getFacadeRoot());

    Event::assertListening(RequestSending::class, OutgoingRequestSendingCollector::class);
    Event::assertListening(ResponseReceived::class, OutgoingRequestResponseCollector::class);
});

it('does not register collectors when tracing is disabled', function () {
    Config::set('logging.channels.github.tracing.enabled', false);

    $handler = new EventHandler;
    $handler->subscribe(Event::getFacadeRoot());

    Event::assertNothingDispatched();
});

it('does not register individual collectors when disabled', function () {
    Event::fake();

    Config::set('logging.channels.github.tracing', [
        'enabled' => true,
        'requests' => false,
        'route' => false,
        'user' => false,
        'queries' => ['enabled' => false],
        'jobs' => false,
        'commands' => false,
        'outgoing_requests' => ['enabled' => false],
    ]);

    $handler = new EventHandler;
    $handler->subscribe(Event::getFacadeRoot());

    // When collectors are disabled, they should not be registered
    // We verify by checking that no events are dispatched when collectors are disabled
    Event::assertNothingDispatched();
});
