<?php

namespace RCI\MemberEngagement\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyMemberStat extends Model
{
    protected $table = 'member_engagement_monthly_stats';

    protected $fillable = [
        'month',
        'corporation_id',
        'character_id',
        'mining_quantity',
        'pve_bounty_amount',
        'industry_tax_amount',
        'pvp_kills',
        'pvp_losses',
        'fleet_participation',
        'mining_tax_amount',
        'isk_destroyed',
        'isk_lost',
    ];

    protected $casts = [
        'month' => 'date',
        'mining_quantity' => 'integer',
        'pve_bounty_amount' => 'decimal:2',
        'industry_tax_amount' => 'decimal:2',
        'pvp_kills' => 'integer',
        'pvp_losses' => 'integer',
        'fleet_participation' => 'integer',
        'mining_tax_amount' => 'decimal:2',
        'isk_destroyed' => 'decimal:2',
        'isk_lost' => 'decimal:2',
    ];
}
