<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\RedactsData;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\EventDrivenCollectorInterface;

class OutgoingRequestSendingCollector implements EventDrivenCollectorInterface
{
    use RedactsData;

    public function isEnabled(): bool
    {
        $config = config('logging.channels.github.tracing.outgoing_requests', []);

        return isset($config['enabled']) && $config['enabled'];
    }

    public function __invoke(RequestSending $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $request = $event->request;
        $requestId = spl_object_hash($request);

        // Store request start time
        $headers = $request->headers();
        $headerBag = new \Symfony\Component\HttpFoundation\HeaderBag($headers);

        Context::add("outgoing_request.{$requestId}", [
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $this->redactHeaders($headerBag),
            'body' => $this->redactPayload($request->data() ?: []),
            'started_at' => microtime(true),
        ]);
    }
}
