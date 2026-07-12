<?php

namespace Rivetworks\WebpageMonitor\Actions;

use Illuminate\Support\Carbon;
use Rivetworks\WebpageMonitor\Data\FetchResult;
use Rivetworks\WebpageMonitor\Data\MonitorRunResult;
use Rivetworks\WebpageMonitor\Enums\ChangeState;
use Rivetworks\WebpageMonitor\Enums\MonitorType;
use Rivetworks\WebpageMonitor\Exceptions\SelectorExtractionException;
use Rivetworks\WebpageMonitor\Matching\ExactTextMatcher;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;
use Rivetworks\WebpageMonitor\Models\WebpageMonitorCheck;

/**
 * Executes one saved monitor, records the resulting check, and classifies its state.
 */
class RunWebpageMonitor
{
    /**
     * Compose the existing fetch and evaluation services used by saved monitor runs.
     */
    public function __construct(
        public FetchWebpage $fetchWebpage,
        public ExactTextMatcher $exactTextMatcher,
        public ExtractSelectedContent $extractSelectedContent,
        public DetermineSelectorChangeState $determineSelectorChangeState,
        public EmitMonitorStateTransitions $emitMonitorStateTransitions,
    ) {}

    /**
     * Run one saved monitor, persist its check row, and return mode-specific outcome data.
     *
     * Once execution begins, a check row is always recorded, including transport
     * failures and selector evaluation failures.
     */
    public function execute(WebpageMonitor $monitor): MonitorRunResult
    {
        $fetchResult = $this->fetchWebpage->execute($monitor->url);

        if (! $fetchResult->reachable) {
            $check = $this->createCheck($monitor, [
                'reachable' => false,
                'http_status' => null,
                'duration_milliseconds' => $fetchResult->durationMilliseconds,
                'body_bytes' => 0,
                'failure_message' => $fetchResult->failureMessage,
                'assertion_passed' => null,
                'match_count' => null,
                'selected_content' => null,
                'content_hash' => null,
                'change_state' => ChangeState::Failed,
            ]);

            $result = new MonitorRunResult($monitor, $check, $fetchResult, 1);
            $this->emitMonitorStateTransitions->execute($result);

            return $result;
        }

        return match ($monitor->type) {
            MonitorType::Availability => $this->runAvailabilityMonitor($monitor, $fetchResult),
            MonitorType::Contains => $this->runContainsMonitor($monitor, $fetchResult),
            MonitorType::Selector => $this->runSelectorMonitor($monitor, $fetchResult),
        };
    }

    /**
     * Record a reachable availability-only run with no additional assertion data.
     */
    private function runAvailabilityMonitor(WebpageMonitor $monitor, FetchResult $fetchResult): MonitorRunResult
    {
        $check = $this->createCheck($monitor, [
            'reachable' => true,
            'http_status' => $fetchResult->statusCode,
            'duration_milliseconds' => $fetchResult->durationMilliseconds,
            'body_bytes' => $fetchResult->bodyBytes,
            'failure_message' => null,
            'assertion_passed' => null,
            'match_count' => null,
            'selected_content' => null,
            'content_hash' => null,
            'change_state' => ChangeState::NotApplicable,
        ]);

        $result = new MonitorRunResult($monitor, $check, $fetchResult, 0);
        $this->emitMonitorStateTransitions->execute($result);

        return $result;
    }

    /**
     * Record a reachable contains run and fail only when the expected text is absent.
     */
    private function runContainsMonitor(WebpageMonitor $monitor, FetchResult $fetchResult): MonitorRunResult
    {
        $containsMatched = $this->exactTextMatcher->matches($fetchResult->body, $monitor->target ?? '');

        $check = $this->createCheck($monitor, [
            'reachable' => true,
            'http_status' => $fetchResult->statusCode,
            'duration_milliseconds' => $fetchResult->durationMilliseconds,
            'body_bytes' => $fetchResult->bodyBytes,
            'failure_message' => null,
            'assertion_passed' => $containsMatched,
            'match_count' => null,
            'selected_content' => null,
            'content_hash' => null,
            'change_state' => ChangeState::NotApplicable,
        ]);

        $result = new MonitorRunResult(
            monitor: $monitor,
            check: $check,
            fetchResult: $fetchResult,
            exitCode: $containsMatched ? 0 : 1,
            containsMatched: $containsMatched,
        );

        $this->emitMonitorStateTransitions->execute($result);

        return $result;
    }

    /**
     * Record a selector run and classify it against the latest successful selector hash.
     *
     * Selector extraction failures are stored as failed checks instead of being treated
     * as change results.
     */
    private function runSelectorMonitor(WebpageMonitor $monitor, FetchResult $fetchResult): MonitorRunResult
    {
        try {
            $selectedContentResult = $this->extractSelectedContent->execute($fetchResult->body, $monitor->target ?? '');
        } catch (SelectorExtractionException $exception) {
            $check = $this->createCheck($monitor, [
                'reachable' => true,
                'http_status' => $fetchResult->statusCode,
                'duration_milliseconds' => $fetchResult->durationMilliseconds,
                'body_bytes' => $fetchResult->bodyBytes,
                'failure_message' => $exception->getMessage(),
                'assertion_passed' => null,
                'match_count' => null,
                'selected_content' => null,
                'content_hash' => null,
                'change_state' => ChangeState::Failed,
            ]);

            $result = new MonitorRunResult($monitor, $check, $fetchResult, 1);
            $this->emitMonitorStateTransitions->execute($result);

            return $result;
        }

        $changeState = $this->determineSelectorChangeState->execute($monitor, $selectedContentResult->contentHash);

        $check = $this->createCheck($monitor, [
            'reachable' => true,
            'http_status' => $fetchResult->statusCode,
            'duration_milliseconds' => $fetchResult->durationMilliseconds,
            'body_bytes' => $fetchResult->bodyBytes,
            'failure_message' => null,
            'assertion_passed' => null,
            'match_count' => $selectedContentResult->matchCount,
            'selected_content' => $selectedContentResult->selectedContent,
            'content_hash' => $selectedContentResult->contentHash,
            'change_state' => $changeState,
        ]);

        $result = new MonitorRunResult(
            monitor: $monitor,
            check: $check,
            fetchResult: $fetchResult,
            exitCode: 0,
            selectedContentResult: $selectedContentResult,
        );

        $this->emitMonitorStateTransitions->execute($result);

        return $result;
    }

    /**
     * @param  array{
     *     reachable: bool,
     *     http_status: ?int,
     *     duration_milliseconds: int,
     *     body_bytes: int,
     *     failure_message: ?string,
     *     assertion_passed: ?bool,
     *     match_count: ?int,
     *     selected_content: ?string,
     *     content_hash: ?string,
     *     change_state: ChangeState
     * }  $attributes
     *
     * Persists exactly one check row for a monitor run.
     */
    private function createCheck(WebpageMonitor $monitor, array $attributes): WebpageMonitorCheck
    {
        return $monitor->checks()->create([
            ...$attributes,
            'checked_at' => Carbon::now(),
        ]);
    }
}
