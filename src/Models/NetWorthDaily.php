<?php

namespace RCI\MemberEngagement\Models;

use Illuminate\Database\Eloquent\Model;

class NetWorthDaily extends Model
{
    protected $table = 'member_engagement_net_worth_daily';

    protected $fillable = [
        'date',
        'character_id',
        'corporation_id',
        'asset_value',
        'wallet_balance',
        'plex_value',
        'regions',
    ];

    protected $casts = [
        'date' => 'date',
        'asset_value' => 'decimal:2',
        'wallet_balance' => 'decimal:2',
        'plex_value' => 'decimal:2',
        'regions' => 'array',
    ];
}
