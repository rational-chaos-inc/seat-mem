<?php

namespace RCI\MemberEngagement\Models;

use Illuminate\Database\Eloquent\Model;

class MemberEngagementSetting extends Model
{
    protected $table = 'member_engagement_settings';

    protected $fillable = [
        'corporation_id',
        'user_id',
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
    ];

    protected $casts = [
        'mining_weight' => 'decimal:2',
        'pve_bounty_weight' => 'decimal:2',
        'industry_tax_weight' => 'decimal:2',
        'pvp_weight' => 'decimal:2',
        'fleet_participation_weight' => 'decimal:2',
        'fleet_participation_min_size' => 'integer',
    ];
}
