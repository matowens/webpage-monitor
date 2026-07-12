<?php

namespace Rivetworks\WebpageMonitor\Data;

use Illuminate\Support\Carbon;
use Rivetworks\WebpageMonitor\Enums\MonitorType;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;

/**
 * Immutable monitor identity captured before deletion so queued notifications never reload the deleted row.
 */
final readonly class DeletedMonitorSnapshot
{
    /**
     * Capture the notification-safe monitor identity immediately before deletion.
     */
    public static function fromModel(WebpageMonitor $monitor, Carbon $deletedAt): self
    {
        return new self(
            monitorId: $monitor->id,
            monitorName: $monitor->name,
            monitorUrl: $monitor->url,
            monitorType: $monitor->type,
            deletedAt: $deletedAt,
        );
    }

    public function __construct(
        public int $monitorId,
        public string $monitorName,
        public string $monitorUrl,
        public MonitorType $monitorType,
        public Carbon $deletedAt,
    ) {}
}
