<?php

namespace Rivetworks\WebpageMonitor\Console;

use Illuminate\Console\Command;
use Rivetworks\WebpageMonitor\Actions\DisableWebpageMonitor;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;

/**
 * Disables a saved monitor without removing its history.
 */
class DisableWebpageMonitorCommand extends Command
{
    protected $signature = 'webpage-monitor:disable {monitor}';

    protected $description = 'Disable a saved webpage monitor.';

    /**
     * Resolve the monitor by numeric ID, then disable it if it is still active.
     */
    public function handle(DisableWebpageMonitor $disableWebpageMonitor): int
    {
        $monitor = $this->resolveMonitor();

        if ($monitor === null) {
            return self::FAILURE;
        }

        if (! $disableWebpageMonitor->execute($monitor)) {
            $this->line('The requested monitor is already inactive.');

            return self::SUCCESS;
        }

        $this->line('Monitor disabled: '.$monitor->name);

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
