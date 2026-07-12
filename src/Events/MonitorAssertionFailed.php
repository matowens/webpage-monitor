<?php

namespace Rivetworks\WebpageMonitor\Events;

use Rivetworks\WebpageMonitor\Data\MonitorStateSnapshot;

/**
 * Fired when a contains monitor transitions from passing to failing.
 */
final readonly class MonitorAssertionFailed
{
    public function __construct(public MonitorStateSnapshot $state) {}
}
