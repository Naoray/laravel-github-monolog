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

    $session = Context::getHidden('session');

    expect($session)->toHaveKey('data');
    expect($session['data']['key'])->toBe('value');
    expect($session['data']['password'])->toContain('bytes redacted');
});

it('does not collect when session is not started', function () {
    $this->collector->collect();

    expect(Context::hasHidden('session'))->toBeFalse();
});

it('strips empty flash data from session', function () {
    Session::start();
    Session::put('key', 'value');

    $this->collector->collect();

    $session = Context::getHidden('session');

    expect($session)->not->toHaveKey('flash');
    expect($session['data'])->not->toHaveKey('_flash');
});

it('preserves non-empty flash data', function () {
    Session::start();
    Session::put('key', 'value');
    Session::flash('message', 'Hello World');

    $this->collector->collect();

    $session = Context::getHidden('session');

    expect($session)->toHaveKey('flash');
    expect($session['flash']['new'])->toContain('message');
});

it('strips _token from session data', function () {
    Session::start();
    Session::put('key', 'value');
    Session::put('_token', 'csrf-token-value');

    $this->collector->collect();

    $session = Context::getHidden('session');

    expect($session['data'])->not->toHaveKey('_token');
    expect($session['data']['key'])->toBe('value');
});
