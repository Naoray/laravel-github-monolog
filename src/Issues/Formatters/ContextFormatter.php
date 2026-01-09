<?php

namespace Naoray\LaravelGithubMonolog\Issues\Formatters;

use Illuminate\Support\Arr;

class ContextFormatter
{
    public function format(array $context): string
    {
        // Exclude exception and sections that are rendered separately
        $context = Arr::except($context, [
            'exception',
            'environment',
            'request',
            'route',
            'user',
            'queries',
            'job',
            'command',
            'outgoing_requests',
            'session',
        ]);

        if (empty($context)) {
            return '';
        }

        return json_encode($context, JSON_PRETTY_PRINT);
    }
}
