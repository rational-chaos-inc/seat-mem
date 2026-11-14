<?php

namespace RCI\MemberEngagement\Models;

use Illuminate\Database\Eloquent\Model;

class CapitalMonthly extends Model
{
    protected $table = 'member_engagement_capital_monthly';

    protected $fillable = [
        'month',
        'group_id',
        'hulls',
    ];

    protected $casts = [
        'month' => 'date',
        'group_id' => 'integer',
        'hulls' => 'integer',
    ];
}
