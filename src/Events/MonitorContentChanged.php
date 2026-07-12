<?php

namespace Rivetworks\WebpageMonitor\Events;

use Rivetworks\WebpageMonitor\Data\MonitorStateSnapshot;

/**
 * Fired when a selector monitor extracts a different normalized value than its prior successful baseline.
 */
final readonly class MonitorContentChanged
{
    public function __construct(
        public MonitorStateSnapshot $state,
        public string $previousSelectedContent,
        public string $previousContentHash,
    ) {}
}
