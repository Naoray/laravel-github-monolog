<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\RedactsData;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\EventDrivenCollectorInterface;

class RequestDataCollector implements EventDrivenCollectorInterface
{
    use RedactsData;

    public function isEnabled(): bool
    {
        $config = config('logging.channels.github.tracing', []);

        return isset($config['requests']) && $config['requests'];
    }

    public function __invoke(RequestHandled $event): void
    {
        $request = $event->request;

        try {
            $files = $this->formatFiles($request->allFiles());
        } catch (\Throwable $e) {
            $files = null;
        }

        Context::add('request', array_filter([
            'url' => $request->url(),
            'full_url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'ips' => $request->ips(),
            'user_agent' => $request->userAgent(),
            'headers' => $this->redactHeaders($request->headers),
            'cookies' => $this->redactPayload($request->cookies->all()),
            'query' => $request->query->all(),
            'body' => $this->redactPayload($request->all()),
            'files' => $files,
            'size' => $request->header('Content-Length') ? (int) $request->header('Content-Length') : null,
        ]));
    }

    /**
     * Format uploaded files metadata.
     *
     * @param  array<string, \Illuminate\Http\UploadedFile|array>  $files
     * @return array<string, mixed>
     */
    private function formatFiles(array $files): array
    {
        return collect($files)
            ->map(function ($file) {
                if (is_array($file)) {
                    return $this->formatFiles($file);
                }

                /** @var \Illuminate\Http\UploadedFile $file */
                $name = $file->getClientOriginalName();
                $mimeType = $file->getMimeType();
                $size = $file->getSize();

                return [
                    'name' => $name,
                    'size' => $size,
                    'mime_type' => $mimeType,
                ];
            })
            ->toArray();
    }
}
