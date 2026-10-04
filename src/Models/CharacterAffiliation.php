<?php

namespace RCI\MemberEngagement\Models;

use Illuminate\Database\Eloquent\Model;

class CharacterAffiliation extends Model
{
    protected $table = 'member_engagement_character_affiliations';

    protected $primaryKey = 'character_id';

    public $incrementing = false;

    protected $fillable = [
        'character_id',
        'corporation_id',
        'alliance_id',
        'faction_id',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];
}
