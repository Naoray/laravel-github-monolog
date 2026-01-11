<?php

namespace Naoray\LaravelGithubMonolog\Deduplication;

use Illuminate\Support\Str;
use Monolog\LogRecord;

class SignatureContextExtractor
{
    /**
     * Extract execution context from log record
     *
     * @return array{kind: string, data: array<string, mixed>}
     */
    public function extract(LogRecord $record): array
    {
        $kind = $this->detectKind($record);
        $data = $this->extractKindData($kind, $record);

        return [
            'kind' => $kind->value,
            'data' => $data,
        ];
    }

    /**
     * Detect the execution context kind based on priority
     */
    private function detectKind(LogRecord $record): SignatureContextKind
    {
        // 1. HTTP if any of these exist
        $routeData = data_get($record->context, 'request.route') ?? data_get($record->context, 'route');
        $hasMethod = data_get($record->context, 'request.method') !== null
            || data_get($record->context, 'http.method') !== null;

        if (($routeData !== null && (is_array($routeData) || $routeData !== '')) || $hasMethod) {
            return SignatureContextKind::Http;
        }

        // 2. JOB if context.job.class exists
        if (data_get($record->context, 'job.class') !== null) {
            return SignatureContextKind::Job;
        }

        // 3. COMMAND if context.command.name exists
        if (data_get($record->context, 'command.name') !== null) {
            return SignatureContextKind::Command;
        }

        // 4. OTHER (fallback)
        return SignatureContextKind::Other;
    }

    /**
     * Extract kind-specific stable data
     *
     * @return array<string, mixed>
     */
    private function extractKindData(SignatureContextKind $kind, LogRecord $record): array
    {
        return match ($kind) {
            SignatureContextKind::Http => $this->extractHttpData($record),
            SignatureContextKind::Job => $this->extractJobData($record),
            SignatureContextKind::Command => $this->extractCommandData($record),
            SignatureContextKind::Other => $this->extractOtherData($record),
        };
    }

    /**
     * Extract HTTP-specific data
     *
     * @return array{method?: string, route?: string, controller?: string}
     */
    private function extractHttpData(LogRecord $record): array
    {
        $data = [];

        // Method
        $method = data_get($record->context, 'request.method') ?? data_get($record->context, 'http.method');
        if (is_string($method) && $method !== '') {
            $data['method'] = strtoupper($method);
        }

        // Route: prefer name > uri template
        $routeData = data_get($record->context, 'request.route') ?? data_get($record->context, 'route');
        if (is_array($routeData)) {
            $route = $routeData['name'] ?? $routeData['uri'] ?? null;
            if (is_string($route) && $route !== '') {
                $data['route'] = $route;
            }
        } elseif (is_string($routeData) && $routeData !== '') {
            $data['route'] = $routeData;
        }

        // Controller (class only, if present and stable)
        $controller = data_get($record->context, 'request.route.controller')
            ?? data_get($record->context, 'route.controller')
            ?? null;
        if (is_string($controller) && $controller !== '') {
            // Extract class name if it's a full action string like "App\Http\Controllers\UserController@index"
            [$controller] = Str::parseCallback($controller, null);
            $data['controller'] = $controller;
        }

        return $data;
    }

    /**
     * Extract Job-specific data
     *
     * @return array{job: string, queue?: string}
     */
    private function extractJobData(LogRecord $record): array
    {
        $data = [];

        $jobClass = data_get($record->context, 'job.class');
        if (is_string($jobClass) && $jobClass !== '') {
            $data['job'] = $jobClass;
        }

        $queue = data_get($record->context, 'job.queue');
        if (is_string($queue) && $queue !== '') {
            $data['queue'] = $queue;
        }

        return $data;
    }

    /**
     * Extract Command-specific data
     *
     * @return array{command: string}
     */
    private function extractCommandData(LogRecord $record): array
    {
        $data = [];

        $commandName = data_get($record->context, 'command.name');
        if (is_string($commandName) && $commandName !== '') {
            $data['command'] = $commandName;
        }

        return $data;
    }

    /**
     * Extract Other-specific data
     *
     * @return array{channel: string, level?: string}
     */
    private function extractOtherData(LogRecord $record): array
    {
        $data = [
            'channel' => $record->channel,
        ];

        // For exceptions, level is redundant since exception class/message already identify the issue
        // Only include level for message-only records
        if (! isset($record->context['exception'])) {
            $data['level'] = $record->level->name;
        }

        return $data;
    }
}
