<?php

namespace RCI\MemberEngagement\Models;

use Illuminate\Database\Eloquent\Model;

class DailyMemberStat extends Model
{
    protected $table = 'member_engagement_daily_stats';

    protected $fillable = [
        'date',
        'corporation_id',
        'character_id',
        'logged_in',
        'mining_quantity',
        'mining_value',
        'pve_bounty_amount',
        'industry_tax_amount',
        'pvp_kills',
        'pvp_losses',
        'fleet_participation',
    ];

    protected $casts = [
        'date' => 'date',
        'logged_in' => 'boolean',
        'mining_quantity' => 'integer',
        'mining_value' => 'decimal:2',
        'pve_bounty_amount' => 'decimal:2',
        'industry_tax_amount' => 'decimal:2',
        'pvp_kills' => 'integer',
        'pvp_losses' => 'integer',
        'fleet_participation' => 'integer',
    ];
}
