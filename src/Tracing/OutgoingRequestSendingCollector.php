<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\RedactsData;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\EventDrivenCollectorInterface;
use Symfony\Component\HttpFoundation\HeaderBag;

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
        $headerBag = new HeaderBag($headers);

        Context::addHidden("outgoing_request.{$requestId}", [
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $this->redactHeaders($headerBag),
            'body' => $this->redactPayload($request->data() ?: []),
            'started_at' => microtime(true),
        ]);
    }
}
