<?php

namespace RCI\MemberEngagement\Models;

use Illuminate\Database\Eloquent\Model;

class EntityName extends Model
{
    protected $table = 'member_engagement_entity_names';

    protected $primaryKey = 'entity_id';

    public $incrementing = false;

    protected $fillable = [
        'entity_id',
        'name',
        'category',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];
}
