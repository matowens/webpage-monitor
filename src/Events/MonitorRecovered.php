<?php

namespace Rivetworks\WebpageMonitor\Events;

use Rivetworks\WebpageMonitor\Data\MonitorStateSnapshot;

/**
 * Fired when a monitor becomes reachable again after an unavailable result.
 */
final readonly class MonitorRecovered
{
    public function __construct(public MonitorStateSnapshot $state) {}
}
