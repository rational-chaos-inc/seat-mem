<?php

namespace RCI\MemberEngagement\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single global row - these settings apply to every main character and
 * their linked alts, regardless of which corporation they belong to.
 */
class MemberEngagementSetting extends Model
{
    protected $table = 'member_engagement_settings';

    protected $fillable = [
        'login_visibility',
        'mining_visibility',
        'pve_bounty_visibility',
        'industry_tax_visibility',
        'pvp_visibility',
        'fleet_participation_visibility',
        'mining_weight',
        'pve_bounty_weight',
        'industry_tax_weight',
        'pvp_weight',
        'fleet_participation_weight',
        'fleet_participation_min_size',
        'last_sync_at',
        'last_aggregate_at',
    ];

    protected $casts = [
        'mining_weight' => 'decimal:2',
        'pve_bounty_weight' => 'decimal:2',
        'industry_tax_weight' => 'decimal:2',
        'pvp_weight' => 'decimal:2',
        'fleet_participation_weight' => 'decimal:2',
        'fleet_participation_min_size' => 'integer',
        'last_sync_at' => 'datetime',
        'last_aggregate_at' => 'datetime',
    ];

    public static function current(): self
    {
        return static::firstOrCreate([], [
            'login_visibility' => 'directors',
            'mining_visibility' => 'directors',
            'pve_bounty_visibility' => 'directors',
            'industry_tax_visibility' => 'directors',
            'pvp_visibility' => 'directors',
            'fleet_participation_visibility' => 'directors',
            'mining_weight' => 1.0,
            'pve_bounty_weight' => 1.0,
            'industry_tax_weight' => 1.0,
            'pvp_weight' => 1.0,
            'fleet_participation_weight' => 1.0,
            'fleet_participation_min_size' => 5,
        ]);
    }
}
