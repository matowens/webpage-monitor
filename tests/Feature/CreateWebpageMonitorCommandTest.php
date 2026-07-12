<?php

use Illuminate\Support\Carbon;
use Rivetworks\WebpageMonitor\Enums\MonitorType;
use Rivetworks\WebpageMonitor\Models\WebpageMonitor;

it('creates an availability monitor', function () {
    $this->artisan('webpage-monitor:create', [
        'name' => 'Availability Monitor',
        'url' => 'https://example.com',
    ])
        ->expectsOutputToContain('Monitor ID: ')
        ->expectsOutput('Name: Availability Monitor')
        ->expectsOutput('URL: https://example.com')
        ->expectsOutput('Type: availability')
        ->assertExitCode(0);

    $monitor = WebpageMonitor::query()->sole();

    expect($monitor->type)->toBe(MonitorType::Availability)
        ->and($monitor->target)->toBeNull()
        ->and($monitor->interval_minutes)->toBeNull()
        ->and($monitor->next_run_at)->toBeNull()
        ->and($monitor->last_run_at)->toBeNull();
});

it('creates a scheduled monitor when every is supplied', function () {
    Carbon::setTestNow('2026-07-12 10:00:00');

    $this->artisan('webpage-monitor:create', [
        'name' => 'Scheduled Monitor',
        'url' => 'https://example.com',
        '--every' => '15',
    ])
        ->expectsOutput('Type: availability')
        ->assertExitCode(0);

    $monitor = WebpageMonitor::query()->sole();

    expect($monitor->interval_minutes)->toBe(15)
        ->and($monitor->next_run_at?->toDateTimeString())->toBe('2026-07-12 10:15:00')
        ->and($monitor->last_run_at)->toBeNull();
});

it('creates a contains monitor', function () {
    $this->artisan('webpage-monitor:create', [
        'name' => 'Contains Monitor',
        'url' => 'https://example.com',
        '--contains' => 'Example Domain',
    ])
        ->expectsOutput('Type: contains')
        ->expectsOutput('Target: Example Domain')
        ->assertExitCode(0);

    $monitor = WebpageMonitor::query()->sole();

    expect($monitor->type)->toBe(MonitorType::Contains)
        ->and($monitor->target)->toBe('Example Domain');
});

it('creates a selector monitor', function () {
    $this->artisan('webpage-monitor:create', [
        'name' => 'Selector Monitor',
        'url' => 'https://example.com',
        '--selector' => 'h1',
    ])
        ->expectsOutput('Type: selector')
        ->expectsOutput('Target: h1')
        ->assertExitCode(0);

    $monitor = WebpageMonitor::query()->sole();

    expect($monitor->type)->toBe(MonitorType::Selector)
        ->and($monitor->target)->toBe('h1');
});

it('rejects contains and selector together when creating a monitor', function () {
    $this->artisan('webpage-monitor:create', [
        'name' => 'Invalid Monitor',
        'url' => 'https://example.com',
        '--contains' => 'Example Domain',
        '--selector' => 'h1',
    ])
        ->expectsOutput('The --contains and --selector options cannot be used together.')
        ->assertExitCode(1);

    expect(WebpageMonitor::query()->count())->toBe(0);
});

it('rejects an empty target when creating a contains monitor', function () {
    $this->artisan('webpage-monitor:create', [
        'name' => 'Invalid Contains Monitor',
        'url' => 'https://example.com',
        '--contains' => '   ',
    ])
        ->expectsOutput('The --contains value must be a non-empty string.')
        ->assertExitCode(1);

    expect(WebpageMonitor::query()->count())->toBe(0);
});

it('rejects an invalid url when creating a monitor', function () {
    $this->artisan('webpage-monitor:create', [
        'name' => 'Invalid URL Monitor',
        'url' => 'example.com',
    ])
        ->expectsOutput('The URL must be a valid absolute HTTP or HTTPS URL.')
        ->assertExitCode(1);

    expect(WebpageMonitor::query()->count())->toBe(0);
});

it('rejects a zero every interval', function () {
    $this->artisan('webpage-monitor:create', [
        'name' => 'Invalid Interval Monitor',
        'url' => 'https://example.com',
        '--every' => '0',
    ])
        ->expectsOutput('The --every value must be a positive integer.')
        ->assertExitCode(1);
});

it('rejects a negative every interval', function () {
    $this->artisan('webpage-monitor:create', [
        'name' => 'Invalid Interval Monitor',
        'url' => 'https://example.com',
        '--every' => '-5',
    ])
        ->expectsOutput('The --every value must be a positive integer.')
        ->assertExitCode(1);
});

it('rejects a decimal every interval', function () {
    $this->artisan('webpage-monitor:create', [
        'name' => 'Invalid Interval Monitor',
        'url' => 'https://example.com',
        '--every' => '1.5',
    ])
        ->expectsOutput('The --every value must be a positive integer.')
        ->assertExitCode(1);
});

it('rejects an empty every interval', function () {
    $this->artisan('webpage-monitor:create', [
        'name' => 'Invalid Interval Monitor',
        'url' => 'https://example.com',
        '--every' => '',
    ])
        ->expectsOutput('The --every value must be a positive integer.')
        ->assertExitCode(1);
});

it('rejects a nonnumeric every interval', function () {
    $this->artisan('webpage-monitor:create', [
        'name' => 'Invalid Interval Monitor',
        'url' => 'https://example.com',
        '--every' => 'hourly',
    ])
        ->expectsOutput('The --every value must be a positive integer.')
        ->assertExitCode(1);
});
