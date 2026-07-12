<?php

namespace Rivetworks\WebpageMonitor\Actions;

use Illuminate\Support\Carbon;
use Rivetworks\WebpageMonitor\Data\DeletedMonitorSnapshot;
use Rivetworks\WebpageMonitor\Events\MonitorDeleted;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;

/**
 * Deletes one saved monitor after capturing an immutable notification snapshot.
 */
class DeleteWebpageMonitor
{
    /**
     * Emit the deletion event before removing the monitor row and cascaded checks.
     */
    public function execute(WebpageMonitor $monitor): void
    {
        $snapshot = DeletedMonitorSnapshot::fromModel($monitor, Carbon::now());

        event(new MonitorDeleted($snapshot));

        $monitor->delete();
    }
}
