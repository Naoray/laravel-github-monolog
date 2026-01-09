<?php

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Session;
use Naoray\LaravelGithubMonolog\Tracing\SessionCollector;

beforeEach(function () {
    $this->collector = new SessionCollector;
});

afterEach(function () {
    Context::flush();
    Session::flush();
});

it('collects session data when session is started', function () {
    Session::start();
    Session::put('key', 'value');
    Session::put('password', 'secret');

    $this->collector->collect();

    $session = Context::get('session');

    expect($session)->toHaveKey('data');
    expect($session['data']['key'])->toBe('value');
    expect($session['data']['password'])->toContain('bytes redacted');
});

it('does not collect when session is not started', function () {
    $this->collector->collect();

    expect(Context::has('session'))->toBeFalse();
});
