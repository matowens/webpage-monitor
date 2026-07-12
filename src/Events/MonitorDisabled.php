<?php

namespace Rivetworks\WebpageMonitor\Events;

use Illuminate\Support\Carbon;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;

/**
 * Fired when an active monitor is disabled through the lifecycle command path.
 */
final readonly class MonitorDisabled
{
    public function __construct(
        public WebpageMonitor $monitor,
        public Carbon $disabledAt,
    ) {}
}
