<?php

namespace RCI\MemberEngagement\Models;

use Illuminate\Database\Eloquent\Model;

class CapitalDaily extends Model
{
    protected $table = 'member_engagement_capital_daily';

    protected $fillable = [
        'date',
        'group_id',
        'hulls',
    ];

    protected $casts = [
        'date' => 'date',
        'group_id' => 'integer',
        'hulls' => 'integer',
    ];
}
