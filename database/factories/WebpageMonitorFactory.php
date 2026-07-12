<?php

namespace Rivetworks\WebpageMonitor\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Rivetworks\WebpageMonitor\Enums\MonitorType;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;

/**
 * @extends Factory<WebpageMonitor>
 */
class WebpageMonitorFactory extends Factory
{
    protected $model = WebpageMonitor::class;

    /**
     * Provide a default active availability monitor definition.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'url' => fake()->url(),
            'type' => MonitorType::Availability,
            'target' => null,
            'is_active' => true,
            'interval_minutes' => null,
            'next_run_at' => null,
            'last_run_at' => null,
            'claimed_at' => null,
            'claim_token' => null,
        ];
    }

    /**
     * Build an availability monitor with no assertion target.
     */
    public function availability(): self
    {
        return $this->state(fn () => [
            'type' => MonitorType::Availability,
            'target' => null,
        ]);
    }

    /**
     * Build a contains monitor for the supplied expected text.
     */
    public function contains(string $expectedText = 'Example Domain'): self
    {
        return $this->state(fn () => [
            'type' => MonitorType::Contains,
            'target' => $expectedText,
        ]);
    }

    /**
     * Build a selector monitor for the supplied CSS selector.
     */
    public function selector(string $selector = 'h1'): self
    {
        return $this->state(fn () => [
            'type' => MonitorType::Selector,
            'target' => $selector,
        ]);
    }

    /**
     * Mark the saved monitor as inactive so run attempts fail before fetching.
     */
    public function inactive(): self
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    /**
     * Build a scheduled monitor with explicit scheduling timestamps for deterministic tests.
     */
    public function scheduled(int $intervalMinutes = 15, ?Carbon $nextRunAt = null, ?Carbon $lastRunAt = null): self
    {
        return $this->state(fn () => [
            'interval_minutes' => $intervalMinutes,
            'next_run_at' => $nextRunAt ?? Carbon::now()->addMinutes($intervalMinutes),
            'last_run_at' => $lastRunAt,
        ]);
    }

    /**
     * Build a claimed monitor state for duplicate-prevention and stale-claim tests.
     */
    public function claimed(?Carbon $claimedAt = null, string $claimToken = 'claim-token'): self
    {
        return $this->state(fn () => [
            'claimed_at' => $claimedAt ?? Carbon::now(),
            'claim_token' => $claimToken,
        ]);
    }
}
