<?php

namespace Rivetworks\WebpageMonitor\Actions;

use Rivetworks\WebpageMonitor\Data\MonitorRunResult;
use Rivetworks\WebpageMonitor\Data\MonitorStateSnapshot;
use Rivetworks\WebpageMonitor\Enums\ChangeState;
use Rivetworks\WebpageMonitor\Enums\MonitorType;
use Rivetworks\WebpageMonitor\Events\MonitorAssertionFailed;
use Rivetworks\WebpageMonitor\Events\MonitorAssertionRecovered;
use Rivetworks\WebpageMonitor\Events\MonitorBaselineEstablished;
use Rivetworks\WebpageMonitor\Events\MonitorBecameUnavailable;
use Rivetworks\WebpageMonitor\Events\MonitorContentChanged;
use Rivetworks\WebpageMonitor\Events\MonitorRecovered;
use Rivetworks\WebpageMonitor\Models\WebpageMonitorCheck;

/**
 * Derives notification-worthy monitor transitions from persisted checks and emits one or more domain events.
 */
class EmitMonitorStateTransitions
{
    /**
     * Emit all result-based transitions from exactly one execution layer after the current check exists.
     */
    public function execute(MonitorRunResult $result): void
    {
        $currentState = MonitorStateSnapshot::fromModels($result->monitor, $result->check);
        $this->emitReachabilityTransitions($result, $currentState);

        match ($result->monitor->type) {
            MonitorType::Availability => $this->emitAvailabilityTransitions($result, $currentState),
            MonitorType::Contains => $this->emitContainsTransitions($result, $currentState),
            MonitorType::Selector => $this->emitSelectorTransitions($result, $currentState),
        };
    }

    /**
     * Emit unavailable and recovery transitions by comparing the current result with the immediately preceding check.
     */
    private function emitReachabilityTransitions(MonitorRunResult $result, MonitorStateSnapshot $currentState): void
    {
        $previousCheck = $this->previousAnyCheck($result);

        if ($previousCheck === null) {
            return;
        }

        if ($previousCheck->reachable && ! $result->check->reachable) {
            event(new MonitorBecameUnavailable($currentState));
        }

        if (! $previousCheck->reachable && $result->check->reachable && config('webpage-monitor.notifications.recovery_enabled', true)) {
            event(new MonitorRecovered($currentState));
        }
    }

    /**
     * Emit only the first known-state baseline for availability monitors.
     */
    private function emitAvailabilityTransitions(MonitorRunResult $result, MonitorStateSnapshot $currentState): void
    {
        if ($this->previousAnyCheck($result) === null && config('webpage-monitor.notifications.baseline_enabled', true)) {
            event(new MonitorBaselineEstablished($currentState));
        }
    }

    /**
     * Emit contains baselines and assertion-state transitions based on the latest preceding reachable contains check.
     */
    private function emitContainsTransitions(MonitorRunResult $result, MonitorStateSnapshot $currentState): void
    {
        if ($this->previousAnyCheck($result) === null && config('webpage-monitor.notifications.baseline_enabled', true)) {
            event(new MonitorBaselineEstablished($currentState));
        }

        if (! $result->check->reachable) {
            return;
        }

        $previousReachableContainsCheck = $this->previousReachableContainsCheck($result);

        if ($previousReachableContainsCheck === null) {
            return;
        }

        if ($previousReachableContainsCheck->assertion_passed === true && $result->check->assertion_passed === false) {
            event(new MonitorAssertionFailed($currentState));
        }

        if ($previousReachableContainsCheck->assertion_passed === false
            && $result->check->assertion_passed === true
            && config('webpage-monitor.notifications.recovery_enabled', true)
        ) {
            event(new MonitorAssertionRecovered($currentState));
        }
    }

    /**
     * Emit selector baseline and change notifications from successful selector extractions only.
     */
    private function emitSelectorTransitions(MonitorRunResult $result, MonitorStateSnapshot $currentState): void
    {
        if ($result->check->content_hash === null || $result->check->selected_content === null) {
            return;
        }

        $previousSuccessfulSelectorCheck = $this->previousSuccessfulSelectorCheck($result);

        if ($previousSuccessfulSelectorCheck === null) {
            if (config('webpage-monitor.notifications.baseline_enabled', true)) {
                event(new MonitorBaselineEstablished($currentState));
            }

            return;
        }

        if ($previousSuccessfulSelectorCheck->content_hash !== $result->check->content_hash) {
            event(new MonitorContentChanged(
                state: $currentState,
                previousSelectedContent: $previousSuccessfulSelectorCheck->selected_content ?? '',
                previousContentHash: $previousSuccessfulSelectorCheck->content_hash ?? '',
            ));
        }
    }

    /**
     * Retrieve the immediately preceding check while explicitly excluding the current persisted row.
     */
    private function previousAnyCheck(MonitorRunResult $result): ?WebpageMonitorCheck
    {
        return $result->monitor->checks()
            ->whereKeyNot($result->check->id)
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Retrieve the latest preceding reachable contains check so outages do not overwrite assertion history.
     */
    private function previousReachableContainsCheck(MonitorRunResult $result): ?WebpageMonitorCheck
    {
        return $result->monitor->checks()
            ->whereKeyNot($result->check->id)
            ->where('reachable', true)
            ->whereNotNull('assertion_passed')
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Retrieve the latest preceding successful selector extraction so failed selector rows never become baselines.
     */
    private function previousSuccessfulSelectorCheck(MonitorRunResult $result): ?WebpageMonitorCheck
    {
        return $result->monitor->checks()
            ->whereKeyNot($result->check->id)
            ->whereNotNull('content_hash')
            ->whereNotNull('selected_content')
            ->whereIn('change_state', [
                ChangeState::Baseline,
                ChangeState::Unchanged,
                ChangeState::Changed,
            ])
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->first();
    }
}
