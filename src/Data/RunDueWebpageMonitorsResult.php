<?php

namespace Rivetworks\WebpageMonitor\Data;

/**
 * Immutable summary of one due-monitor batch execution.
 */
final readonly class RunDueWebpageMonitorsResult
{
    /**
     * @param  list<string>  $exceptionMessages  Concise monitor-specific exception lines for console output.
     */
    public function __construct(
        public int $dueMonitors,
        public int $dispatched,
        public int $skippedAlreadyClaimed,
        public int $exceptions,
        public array $exceptionMessages = [],
    ) {}
}
