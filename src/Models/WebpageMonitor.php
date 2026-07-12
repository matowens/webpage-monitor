<?php

namespace Rivetworks\WebpageMonitor\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Rivetworks\WebpageMonitor\Database\Factories\WebpageMonitorFactory;
use Rivetworks\WebpageMonitor\Enums\MonitorType;

/**
 * Persistent definition of a saved webpage monitor.
 */
class WebpageMonitor extends Model
{
    /** @use HasFactory<WebpageMonitorFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'type',
        'target',
        'is_active',
        'interval_minutes',
        'next_run_at',
        'last_run_at',
        'claimed_at',
        'claim_token',
    ];

    /**
     * Cast stored monitor values into their domain-friendly runtime types.
     */
    protected function casts(): array
    {
        return [
            'type' => MonitorType::class,
            'is_active' => 'boolean',
            'interval_minutes' => 'integer',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    /**
     * Get the recorded manual checks for this saved monitor.
     */
    public function checks(): HasMany
    {
        return $this->hasMany(WebpageMonitorCheck::class);
    }

    /**
     * Create monitor model instances with the package factory outside the host app namespace.
     */
    protected static function newFactory(): WebpageMonitorFactory
    {
        return WebpageMonitorFactory::new();
    }
}
