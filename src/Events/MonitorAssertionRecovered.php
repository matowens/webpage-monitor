<?php

namespace Rivetworks\WebpageMonitor\Events;

use Rivetworks\WebpageMonitor\Data\MonitorStateSnapshot;

/**
 * Fired when a contains monitor transitions from failing to passing.
 */
final readonly class MonitorAssertionRecovered
{
    public function __construct(public MonitorStateSnapshot $state) {}
}
