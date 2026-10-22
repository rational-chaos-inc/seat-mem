<?php

namespace RCI\MemberEngagement\Models;

use Illuminate\Database\Eloquent\Model;

class NetWorthMonthly extends Model
{
    protected $table = 'member_engagement_net_worth_monthly';

    protected $fillable = [
        'month',
        'character_id',
        'corporation_id',
        'asset_value',
        'wallet_balance',
        'regions',
    ];

    protected $casts = [
        'month' => 'date',
        'asset_value' => 'decimal:2',
        'wallet_balance' => 'decimal:2',
        'regions' => 'array',
    ];
}
