<?php

namespace Rivetworks\WebpageMonitor\Data;

use Rivetworks\WebpageMonitor\Models\WebpageMonitor;
use Rivetworks\WebpageMonitor\Models\WebpageMonitorCheck;

/**
 * Immutable outcome of running one saved monitor and recording its check row.
 */
final readonly class MonitorRunResult
{
    /**
     * @param  ?bool  $containsMatched  Present only for contains monitors.
     * @param  ?SelectedContentResult  $selectedContentResult  Present only for successful selector evaluations.
     */
    public function __construct(
        public WebpageMonitor $monitor,
        public WebpageMonitorCheck $check,
        public FetchResult $fetchResult,
        public int $exitCode,
        public ?bool $containsMatched = null,
        public ?SelectedContentResult $selectedContentResult = null,
    ) {}
}
