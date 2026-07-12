<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Rivetworks\WebpageMonitor\Enums\ChangeState;
use Rivetworks\WebpageMonitor\Enums\MonitorType;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;
use Rivetworks\WebpageMonitor\Models\WebpageMonitorCheck;

it('loads package migrations successfully', function () {
    expect(Schema::hasTable('webpage_monitors'))->toBeTrue()
        ->and(Schema::hasTable('webpage_monitor_checks'))->toBeTrue()
        ->and(Schema::hasColumns('webpage_monitors', [
            'interval_minutes',
            'next_run_at',
            'last_run_at',
            'claimed_at',
            'claim_token',
        ]))->toBeTrue();
});

it('supports scheduling and claim fields on monitors', function () {
    $monitor = WebpageMonitor::factory()->scheduled(
        intervalMinutes: 30,
        nextRunAt: now()->addMinutes(30),
    )->claimed(now()->subMinute(), 'claim-token')->create();

    expect($monitor->interval_minutes)->toBe(30)
        ->and($monitor->next_run_at)->toBeInstanceOf(Carbon::class)
        ->and($monitor->last_run_at)->toBeNull()
        ->and($monitor->claimed_at)->toBeInstanceOf(Carbon::class)
        ->and($monitor->claim_token)->toBe('claim-token');
});

it('supports monitor and check relationships', function () {
    $monitor = WebpageMonitor::factory()->contains()->create();
    $check = WebpageMonitorCheck::factory()->for($monitor, 'monitor')->create();

    expect($monitor->checks)->toHaveCount(1)
        ->and($monitor->checks->first()?->is($check))->toBeTrue()
        ->and($check->monitor->is($monitor))->toBeTrue();
});

it('supports monitor and check factories with string backed enums', function () {
    $monitor = WebpageMonitor::factory()->selector()->create();
    $check = WebpageMonitorCheck::factory()->for($monitor, 'monitor')->create([
        'change_state' => ChangeState::Baseline,
    ]);

    expect($monitor->type)->toBe(MonitorType::Selector)
        ->and($check->change_state)->toBe(ChangeState::Baseline);

    $this->assertDatabaseHas('webpage_monitors', [
        'id' => $monitor->id,
        'type' => MonitorType::Selector->value,
    ]);

    $this->assertDatabaseHas('webpage_monitor_checks', [
        'id' => $check->id,
        'change_state' => ChangeState::Baseline->value,
    ]);
});
