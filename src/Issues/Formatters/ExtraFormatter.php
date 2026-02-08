<?php

namespace Naoray\LaravelGithubMonolog\Issues\Formatters;

use Illuminate\Support\Arr;

class ExtraFormatter
{
    public function format(array $extra): string
    {
        // Exclude keys that have dedicated sections to avoid duplicate output
        $extra = Arr::except($extra, [
            'exception',
            'environment',
            'request',
            'route',
            'route_summary',
            'user',
            'queries',
            'job',
            'command',
            'outgoing_requests',
            'session',
            'livewire',
            'livewire_originating_page',
            'inertia',
        ]);

        if (empty($extra)) {
            return '';
        }

        return json_encode($extra, JSON_PRETTY_PRINT);
    }
}
