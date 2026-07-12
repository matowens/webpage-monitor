<?php

namespace Rivetworks\WebpageMonitor\Console;

use Illuminate\Console\Command;
use Rivetworks\WebpageMonitor\Actions\DeleteWebpageMonitor;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;

/**
 * Deletes a saved monitor after queuing any required termination notification.
 */
class DeleteWebpageMonitorCommand extends Command
{
    protected $signature = 'webpage-monitor:delete {monitor}';

    protected $description = 'Delete a saved webpage monitor.';

    /**
     * Resolve the monitor by numeric ID, queue its deletion notification, and then remove it.
     */
    public function handle(DeleteWebpageMonitor $deleteWebpageMonitor): int
    {
        $monitorIdentifier = (string) $this->argument('monitor');

        if (filter_var($monitorIdentifier, FILTER_VALIDATE_INT) === false) {
            $this->error('The monitor must be identified by a numeric ID.');

            return self::FAILURE;
        }

        $monitor = WebpageMonitor::query()->find((int) $monitorIdentifier);

        if ($monitor === null) {
            $this->error('The requested monitor could not be found.');

            return self::FAILURE;
        }

        $monitorName = $monitor->name;

        $deleteWebpageMonitor->execute($monitor);

        $this->line('Monitor deleted: '.$monitorName);

        return self::SUCCESS;
    }
}
