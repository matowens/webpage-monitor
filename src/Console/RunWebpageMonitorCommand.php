<?php

namespace Rivetworks\WebpageMonitor\Console;

use Illuminate\Console\Command;
use Rivetworks\WebpageMonitor\Actions\RunWebpageMonitor;
use Rivetworks\WebpageMonitor\Data\MonitorRunResult;
use Rivetworks\WebpageMonitor\Enums\MonitorType;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;

/**
 * Runs a saved webpage monitor once and records the resulting check.
 */
class RunWebpageMonitorCommand extends Command
{
    protected $signature = 'webpage-monitor:run {monitor}';

    protected $description = 'Run a saved webpage monitor once.';

    /**
     * Resolve a saved monitor by numeric ID, run it once, and render the stored outcome.
     *
     * Missing or inactive monitors fail before any fetch occurs. Otherwise the
     * command returns the persisted run result's exit code.
     */
    public function handle(RunWebpageMonitor $runWebpageMonitor): int
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

        if (! $monitor->is_active) {
            $this->error('The requested monitor is inactive.');

            return self::FAILURE;
        }

        $result = $runWebpageMonitor->execute($monitor);

        $this->renderResult($result);

        return $result->exitCode;
    }

    /**
     * Render the persisted monitor run using a stable human-readable summary.
     *
     * The output always includes the saved monitor identity, fetch summary, change
     * state, and persisted check ID, then adds mode-specific details when available.
     */
    private function renderResult(MonitorRunResult $result): void
    {
        $this->line('Monitor ID: '.$result->monitor->id);
        $this->line('Monitor Name: '.$result->monitor->name);
        $this->line('Requested URL: '.$result->fetchResult->requestedUrl);
        $this->line('Reachable: '.($result->fetchResult->reachable ? 'yes' : 'no'));
        $this->line('HTTP Status: '.($result->fetchResult->statusCode ?? 'n/a'));
        $this->line('Duration: '.$result->fetchResult->durationMilliseconds.' ms');
        $this->line('Body Size: '.$result->fetchResult->bodyBytes.' bytes');

        if ($result->monitor->type === MonitorType::Contains) {
            $this->line('Expected Text: '.$result->monitor->target);
            $this->line('Text Found: '.($result->containsMatched ? 'yes' : 'no'));
        }

        if ($result->monitor->type === MonitorType::Selector && $result->selectedContentResult !== null) {
            $this->line('Selector: '.$result->selectedContentResult->selector);
            $this->line('Matches: '.$result->selectedContentResult->matchCount);
            $this->line('Selected Content: '.$result->selectedContentResult->selectedContent);
            $this->line('Content Hash: '.$result->selectedContentResult->contentHash);
        }

        if ($result->monitor->type === MonitorType::Selector && $result->selectedContentResult === null) {
            $this->line('Selector: '.$result->monitor->target);
        }

        $this->line('Change State: '.$result->check->change_state->value);
        $this->line('Check ID: '.$result->check->id);

        if ($result->check->failure_message !== null) {
            $this->line('Failure: '.$result->check->failure_message);
        }
    }
}
