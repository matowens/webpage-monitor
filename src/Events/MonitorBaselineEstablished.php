<?php

namespace Rivetworks\WebpageMonitor\Events;

use Rivetworks\WebpageMonitor\Data\MonitorStateSnapshot;

/**
 * Fired when a monitor establishes its first notification-worthy known state.
 */
final readonly class MonitorBaselineEstablished
{
    public function __construct(public MonitorStateSnapshot $state) {}
}
