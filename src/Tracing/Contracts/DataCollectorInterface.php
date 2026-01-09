<?php

namespace Naoray\LaravelGithubMonolog\Tracing\Contracts;

interface DataCollectorInterface
{
    /**
     * Check if this collector is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Collect data and add it to the Laravel Context.
     *
     * This method is called on-demand (e.g., when an exception occurs)
     * for collectors that need to gather data at log time.
     *
     * @return void
     */
    public function collect(): void;
}
