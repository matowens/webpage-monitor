<?php

namespace Rivetworks\WebpageMonitor\Console;

use Illuminate\Console\Command;
use Rivetworks\WebpageMonitor\Actions\EnableWebpageMonitor;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;

/**
 * Re-enables a saved monitor without emitting lifecycle notifications in v1.
 */
class EnableWebpageMonitorCommand extends Command
{
    protected $signature = 'webpage-monitor:enable {monitor}';

    protected $description = 'Enable a saved webpage monitor.';

    /**
     * Resolve the monitor by numeric ID, then enable it if it is currently inactive.
     */
    public function handle(EnableWebpageMonitor $enableWebpageMonitor): int
    {
        $monitor = $this->resolveMonitor();

        if ($monitor === null) {
            return self::FAILURE;
        }

        if (! $enableWebpageMonitor->execute($monitor)) {
            $this->line('The requested monitor is already active.');

            return self::SUCCESS;
        }

        $this->line('Monitor enabled: '.$monitor->name);

        return self::SUCCESS;
    }

    /**
     * Resolve the numeric monitor identifier or print the shared command errors.
     */
    private function resolveMonitor(): ?WebpageMonitor
    {
        $monitorIdentifier = (string) $this->argument('monitor');

        if (filter_var($monitorIdentifier, FILTER_VALIDATE_INT) === false) {
            $this->error('The monitor must be identified by a numeric ID.');

            return null;
        }

        $monitor = WebpageMonitor::query()->find((int) $monitorIdentifier);

        if ($monitor === null) {
            $this->error('The requested monitor could not be found.');
        }

        return $monitor;
    }
}
