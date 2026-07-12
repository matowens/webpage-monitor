<?php

namespace Rivetworks\WebpageMonitor\Events;

use Rivetworks\WebpageMonitor\Data\DeletedMonitorSnapshot;

/**
 * Fired before a monitor row is deleted so notifications can rely on immutable snapshot data.
 */
final readonly class MonitorDeleted
{
    public function __construct(public DeletedMonitorSnapshot $monitor) {}
}
