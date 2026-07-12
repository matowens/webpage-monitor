<?php

namespace Rivetworks\WebpageMonitor\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Rivetworks\WebpageMonitor\Enums\ChangeState;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;
use Rivetworks\WebpageMonitor\Models\WebpageMonitorCheck;

/**
 * @extends Factory<WebpageMonitorCheck>
 */
class WebpageMonitorCheckFactory extends Factory
{
    protected $model = WebpageMonitorCheck::class;

    /**
     * Provide a default successful availability-style check row.
     */
    public function definition(): array
    {
        return [
            'webpage_monitor_id' => WebpageMonitor::factory(),
            'reachable' => true,
            'http_status' => 200,
            'duration_milliseconds' => 100,
            'body_bytes' => 1000,
            'failure_message' => null,
            'assertion_passed' => null,
            'match_count' => null,
            'selected_content' => null,
            'content_hash' => null,
            'change_state' => ChangeState::NotApplicable,
            'checked_at' => Carbon::now(),
        ];
    }
}
