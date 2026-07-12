<?php

namespace Rivetworks\WebpageMonitor\Events;

use Rivetworks\WebpageMonitor\Data\MonitorStateSnapshot;

/**
 * Fired when a monitor transitions from reachable to unreachable.
 */
final readonly class MonitorBecameUnavailable
{
    public function __construct(public MonitorStateSnapshot $state) {}
}
