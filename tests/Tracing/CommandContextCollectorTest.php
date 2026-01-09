<?php

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\CommandContextCollector;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

beforeEach(function () {
    $this->collector = new CommandContextCollector;
});

afterEach(function () {
    Context::flush();
});

it('collects command context', function () {
    $input = new ArrayInput([
        'arg1' => 'value1',
        '--option' => 'value2',
        '--password' => 'secret',
    ]);

    $output = new NullOutput;
    $event = new CommandStarting('test:command', $input, $output);

    ($this->collector)($event);

    $commandContext = Context::get('command');

    expect($commandContext)->toHaveKeys(['name', 'arguments', 'options']);
    expect($commandContext['name'])->toBe('test:command');
    expect($commandContext['arguments'])->toBeArray();
    expect($commandContext['options'])->toBeArray();
    // Password should be redacted if it matches sensitive patterns
    if (isset($commandContext['options']['password'])) {
        expect($commandContext['options']['password'])->toContain('bytes redacted');
    }
});
