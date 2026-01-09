<?php

namespace Naoray\LaravelGithubMonolog\Issues\Formatters;

class OutgoingRequestFormatter
{
    public function format(?array $requests): string
    {
        if (empty($requests)) {
            return '';
        }

        $output = "```\n";
        foreach ($requests as $request) {
            $method = $request['method'] ?? 'GET';
            $url = $request['url'] ?? '';
            $status = $request['status'] ?? null;
            $duration = $request['duration_ms'] ?? null;

            $output .= "{$method} {$url}";
            if ($status !== null) {
                $output .= " → {$status}";
            }
            if ($duration !== null) {
                $output .= " ({$duration}ms)";
            }
            $output .= "\n";
        }

        return rtrim($output)."\n```";
    }
}
