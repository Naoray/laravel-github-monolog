<?php

use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\LivewireDataCollector;

beforeEach(function () {
    $this->collector = new LivewireDataCollector;
});

afterEach(function () {
    Context::flush();
});

it('captures livewire component data on hydrate', function () {
    // Create a simple object that acts like a Livewire component
    $component = new class
    {
        public function getName(): string
        {
            return 'user-profile';
        }
    };

    $this->collector->hydrate($component);

    $livewireData = Context::get('livewire');
    expect($livewireData)->toBeArray();
    expect($livewireData)->toHaveKey('component');
    expect($livewireData)->toHaveKey('component_name');
    expect($livewireData['component_name'])->toBe('user-profile');
});

it('captures livewire component data on componentHydrateSubsequent', function () {
    // Create a simple object that acts like a Livewire component
    $component = new class
    {
        public function getName(): string
        {
            return 'counter';
        }
    };

    $this->collector->componentHydrateSubsequent($component);

    $livewireData = Context::get('livewire');
    expect($livewireData)->toBeArray();
    expect($livewireData)->toHaveKey('component');
    expect($livewireData)->toHaveKey('component_name');
    expect($livewireData['component_name'])->toBe('counter');
});

it('stores originating page for route summary from referer', function () {
    // Create a request with referer header
    $request = Illuminate\Http\Request::create('/livewire/message/dashboard-widget', 'POST');
    $request->headers->set('referer', 'https://example.com/dashboard?tab=settings');
    app()->instance('request', $request);

    // Create a simple object that acts like a Livewire component
    $component = new class
    {
        public function getName(): string
        {
            return 'dashboard-widget';
        }
    };

    $this->collector->hydrate($component);

    // Check that originating page is stored from referer
    $originatingPage = Context::get('livewire_originating_page');
    expect($originatingPage)->toBe('/dashboard?tab=settings');

    $livewireData = Context::get('livewire');
    expect($livewireData)->toHaveKey('originating_page');
    expect($livewireData['originating_page'])->toBe('/dashboard?tab=settings');
});

it('returns enabled status based on config', function () {
    config(['logging.channels.github.tracing.livewire' => true]);
    config(['github-monolog.tracing.livewire' => null]);
    expect($this->collector->isEnabled())->toBeTrue();

    config(['logging.channels.github.tracing.livewire' => false]);
    config(['github-monolog.tracing.livewire' => false]);
    expect($this->collector->isEnabled())->toBeFalse();
});
