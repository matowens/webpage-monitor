<?php

namespace Rivetworks\WebpageMonitor\Actions;

use Illuminate\Support\Carbon;
use Rivetworks\WebpageMonitor\Events\MonitorDisabled;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;

/**
 * Disables one saved monitor and emits its lifecycle event only on the active-to-inactive transition.
 */
class DisableWebpageMonitor
{
    /**
     * Persist the inactive state and emit the disabled event only once.
     */
    public function execute(WebpageMonitor $monitor): bool
    {
        if (! $monitor->is_active) {
            return false;
        }

        $monitor->forceFill(['is_active' => false])->save();

        event(new MonitorDisabled($monitor->fresh(), Carbon::now()));

        return true;
    }
}
