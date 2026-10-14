<?php

use Naoray\LaravelGithubMonolog\Deduplication\DefaultSignatureGenerator;
use Naoray\LaravelGithubMonolog\Deduplication\LegacySignatureGenerator;

beforeEach(function () {
    $this->generator = new LegacySignatureGenerator;
    $this->exception = new RuntimeException('Payload warm-up failed');
});

test('a job error dispatched from a route gets the signature v3.10 gave it', function () {
    $route = ['name' => 'orders.store', 'uri' => 'orders', 'controller' => 'App\\Http\\Controllers\\OrderController@store'];
    $job = ['class' => 'App\\Jobs\\WarmAnalyticsPayloads', 'queue' => 'statistics-rollups'];

    $jobRecord = createLogRecord(context: ['route' => $route, 'job' => $job], exception: $this->exception);

    // v3.10 checked HTTP first and ignored the job, so it signed the record
    // exactly like the same error raised in that route.
    $httpRecord = createLogRecord(context: ['route' => $route], exception: $this->exception);

    expect($this->generator->generate($jobRecord))
        ->toBe((new DefaultSignatureGenerator)->generate($httpRecord))
        ->not->toBe((new DefaultSignatureGenerator)->generate($jobRecord));
});

test('it returns null for records whose signature did not change', function (array $context) {
    $record = createLogRecord(context: $context, exception: $this->exception);

    expect($this->generator->generate($record))->toBeNull();
})->with([
    'job without http context' => [['job' => ['class' => 'App\\Jobs\\SendEmail']]],
    'http request' => [['route' => ['name' => 'orders.store'], 'request' => ['method' => 'POST']]],
    'command' => [['command' => ['name' => 'orders:sync']]],
    'no context' => [[]],
]);

test('it treats a request method alone as http context, like v3.10', function () {
    $record = createLogRecord(context: [
        'request' => ['method' => 'POST'],
        'job' => ['class' => 'App\\Jobs\\SendEmail'],
    ], exception: $this->exception);

    expect($this->generator->generate($record))->toBe(
        (new DefaultSignatureGenerator)->generate(createLogRecord(context: ['request' => ['method' => 'POST']], exception: $this->exception))
    );
});
