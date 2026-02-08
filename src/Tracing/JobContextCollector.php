<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\RedactsData;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\ResolvesTracingConfig;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\EventDrivenCollectorInterface;

class JobContextCollector implements EventDrivenCollectorInterface
{
    use RedactsData;
    use ResolvesTracingConfig;

    /**
     * Maximum length for serialized string values in the payload before truncation.
     */
    protected const MAX_SERIALIZED_LENGTH = 500;

    public function isEnabled(): bool
    {
        return $this->isTracingFeatureEnabled('jobs');
    }

    public function __invoke(JobExceptionOccurred $event): void
    {
        $job = $event->job;

        Context::add('job', [
            'name' => $job->getName(),
            'class' => $job->payload()['displayName'] ?? null,
            'queue' => $job->getQueue(),
            'connection' => $job->getConnectionName(),
            'attempts' => $job->attempts(),
            'payload' => $this->cleanPayload($job->payload()),
        ]);
    }

    /**
     * Clean the job payload by redacting sensitive data and truncating serialized values.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function cleanPayload(array $payload): array
    {
        $payload = $this->redactPayload($payload);

        return $this->truncateSerializedValues($payload);
    }

    /**
     * Recursively truncate long serialized string values in the payload.
     *
     * Serialized PHP objects (e.g. payload.data.command) can contain hundreds
     * of lines of unreadable data. This method truncates them to keep GitHub
     * issues clean and readable.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function truncateSerializedValues(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->truncateSerializedValues($value);
            } elseif (is_string($value) && strlen($value) > self::MAX_SERIALIZED_LENGTH) {
                $result[$key] = Str::limit($value, self::MAX_SERIALIZED_LENGTH, '... [truncated]');
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
