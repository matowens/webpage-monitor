<?php

namespace Rivetworks\WebpageMonitor\Actions;

use Illuminate\Support\Carbon;
use Rivetworks\WebpageMonitor\Enums\MonitorType;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;

/**
 * Persists a saved monitor definition from validated command input.
 */
class CreateWebpageMonitor
{
    /**
     * Create a saved monitor using the mode selection already validated by the command.
     */
    public function execute(string $name, string $url, ?string $contains = null, ?string $selector = null, ?int $everyMinutes = null): WebpageMonitor
    {
        [$type, $target] = $this->determineTypeAndTarget($contains, $selector);
        $nextRunAt = $everyMinutes !== null
            ? Carbon::now()->addMinutes($everyMinutes)
            : null;

        return WebpageMonitor::query()->create([
            'name' => $name,
            'url' => $url,
            'type' => $type,
            'target' => $target,
            'is_active' => true,
            'interval_minutes' => $everyMinutes,
            'next_run_at' => $nextRunAt,
            'last_run_at' => null,
        ]);
    }

    /**
     * Resolve the saved monitor mode and persisted target from the validated options.
     *
     * @return array{0: MonitorType, 1: ?string}
     */
    private function determineTypeAndTarget(?string $contains, ?string $selector): array
    {
        return match (true) {
            $contains !== null => [MonitorType::Contains, $contains],
            $selector !== null => [MonitorType::Selector, $selector],
            default => [MonitorType::Availability, null],
        };
    }
}
