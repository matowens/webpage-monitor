<?php

namespace Rivetworks\WebpageMonitor\Data;

use Illuminate\Support\Carbon;
use Rivetworks\WebpageMonitor\Enums\ChangeState;
use Rivetworks\WebpageMonitor\Enums\MonitorType;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;
use Rivetworks\WebpageMonitor\Models\WebpageMonitorCheck;

/**
 * Immutable notification-safe view of one persisted monitor result.
 */
final readonly class MonitorStateSnapshot
{
    /**
     * Capture stable scalar state from a persisted monitor and check pair for queued notifications.
     */
    public static function fromModels(WebpageMonitor $monitor, WebpageMonitorCheck $check): self
    {
        return new self(
            monitorId: $monitor->id,
            monitorName: $monitor->name,
            monitorUrl: $monitor->url,
            monitorType: $monitor->type,
            target: $monitor->target,
            checkId: $check->id,
            checkedAt: $check->checked_at ?? Carbon::now(),
            reachable: $check->reachable,
            httpStatus: $check->http_status,
            failureMessage: $check->failure_message,
            assertionPassed: $check->assertion_passed,
            matchCount: $check->match_count,
            selectedContent: $check->selected_content,
            contentHash: $check->content_hash,
            changeState: $check->change_state,
        );
    }

    /**
     * @param  ?string  $target  Expected text for contains monitors or selector for selector monitors.
     */
    public function __construct(
        public int $monitorId,
        public string $monitorName,
        public string $monitorUrl,
        public MonitorType $monitorType,
        public ?string $target,
        public int $checkId,
        public Carbon $checkedAt,
        public bool $reachable,
        public ?int $httpStatus,
        public ?string $failureMessage,
        public ?bool $assertionPassed,
        public ?int $matchCount,
        public ?string $selectedContent,
        public ?string $contentHash,
        public ChangeState $changeState,
    ) {}
}
