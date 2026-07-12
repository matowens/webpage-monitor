<?php

namespace Rivetworks\WebpageMonitor\Actions;

use Rivetworks\WebpageMonitor\Models\WebpageMonitor;

/**
 * Re-enables one saved monitor without emitting lifecycle notifications in v1.
 */
class EnableWebpageMonitor
{
    /**
     * Persist the active state only when the monitor is currently inactive.
     */
    public function execute(WebpageMonitor $monitor): bool
    {
        if ($monitor->is_active) {
            return false;
        }

        $monitor->forceFill(['is_active' => true])->save();

        return true;
    }
}
