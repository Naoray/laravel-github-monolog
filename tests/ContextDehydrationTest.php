<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Context;

beforeEach(function () {
    Context::flush();
});

afterEach(function () {
    Context::flush();
});

it('strips hidden tracing context so job payloads can be serialized', function () {
    // RequestDataCollector stores the request payload as hidden context, and
    // $request->all() merges raw UploadedFile instances into the body. Those
    // cannot be serialized, so dehydrating must remove the hidden 'request' key.
    Context::addHidden('request', [
        'body' => [
            'file' => UploadedFile::fake()->create('audio.mp3', 100),
        ],
    ]);

    // dehydrate() serializes both the visible and hidden stores. Without the
    // fix this throws "Serialization of 'Illuminate\Http\UploadedFile' is not allowed".
    $dehydrated = Context::dehydrate();

    expect($dehydrated['hidden'] ?? [])->not->toHaveKey('request');
});

it('strips hidden tracing keys from dehydrated context', function () {
    Context::addHidden('queries', ['select * from users']);
    Context::addHidden('session', ['foo' => 'bar']);
    Context::addHidden('breadcrumbs', [['message' => 'clicked']]);
    Context::addHidden('outgoing_requests', [['url' => 'https://example.com']]);
    Context::addHidden('outgoing_request.abc123', ['url' => 'https://example.com']);

    $dehydrated = Context::dehydrate();
    $hidden = $dehydrated['hidden'] ?? [];

    expect($hidden)
        ->not->toHaveKey('queries')
        ->not->toHaveKey('session')
        ->not->toHaveKey('breadcrumbs')
        ->not->toHaveKey('outgoing_requests')
        ->not->toHaveKey('outgoing_request.abc123');
});

it('keeps unrelated hidden context untouched', function () {
    Context::addHidden('keep_me', ['important' => true]);

    $dehydrated = Context::dehydrate();
    $hidden = $dehydrated['hidden'] ?? [];

    expect($hidden)->toHaveKey('keep_me');
});
