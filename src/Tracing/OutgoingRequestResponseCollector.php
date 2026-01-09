<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\EventDrivenCollectorInterface;

class OutgoingRequestResponseCollector implements EventDrivenCollectorInterface
{
    private const DEFAULT_LIMIT = 5;

    public function isEnabled(): bool
    {
        $config = config('logging.channels.github.tracing.outgoing_requests', []);

        return isset($config['enabled']) && $config['enabled'];
    }

    public function __invoke(ResponseReceived $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $request = $event->request;
        $response = $event->response;
        $requestId = spl_object_hash($request);

        $config = config('logging.channels.github.tracing.outgoing_requests', []);
        $limit = $config['limit'] ?? self::DEFAULT_LIMIT;

        $requestData = Context::get("outgoing_request.{$requestId}", []);
        $startedAt = $requestData['started_at'] ?? microtime(true);
        $duration = (microtime(true) - $startedAt) * 1000; // Convert to milliseconds

        $outgoingRequests = Context::get('outgoing_requests', []);

        $outgoingRequests[] = [
            'url' => $request->url(),
            'method' => $request->method(),
            'status' => $response->status(),
            'duration_ms' => round($duration, 2),
            'headers' => $requestData['headers'] ?? [],
        ];

        // Keep only the last N requests
        if (count($outgoingRequests) > $limit) {
            $outgoingRequests = array_slice($outgoingRequests, -$limit);
        }

        Context::add('outgoing_requests', $outgoingRequests);
        Context::forget("outgoing_request.{$requestId}");
    }
}
