<?php

namespace Rivetworks\WebpageMonitor\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Rivetworks\WebpageMonitor\Database\Factories\WebpageMonitorCheckFactory;
use Rivetworks\WebpageMonitor\Enums\ChangeState;

/**
 * Stored outcome of one manual run of a saved webpage monitor.
 */
class WebpageMonitorCheck extends Model
{
    /** @use HasFactory<WebpageMonitorCheckFactory> */
    use HasFactory;

    protected $fillable = [
        'webpage_monitor_id',
        'reachable',
        'http_status',
        'duration_milliseconds',
        'body_bytes',
        'failure_message',
        'assertion_passed',
        'match_count',
        'selected_content',
        'content_hash',
        'change_state',
        'checked_at',
    ];

    /**
     * Cast stored check values into their domain-friendly runtime types.
     */
    protected function casts(): array
    {
        return [
            'reachable' => 'boolean',
            'assertion_passed' => 'boolean',
            'change_state' => ChangeState::class,
            'checked_at' => 'datetime',
        ];
    }

    /**
     * Get the saved monitor definition that produced this check row.
     */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(WebpageMonitor::class, 'webpage_monitor_id');
    }

    /**
     * Create check model instances with the package factory outside the host app namespace.
     */
    protected static function newFactory(): WebpageMonitorCheckFactory
    {
        return WebpageMonitorCheckFactory::new();
    }
}
