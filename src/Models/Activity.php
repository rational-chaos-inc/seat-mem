<?php

namespace RCI\MemberEngagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Activity extends Model
{
    protected $table = 'member_engagement_activities';

    protected $fillable = [
        'activity_timestamp',
        'user_id',
        'character_id',
        'corporation_id',
        'alliance_id',
        'activity_type',
        'source_id',
        'metadata',
    ];

    protected $casts = [
        'activity_timestamp' => 'datetime',
        'metadata' => 'json',
    ];

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForCharacter(Builder $query, int $characterId): Builder
    {
        return $query->where('character_id', $characterId);
    }

    public function scopeForCharacters(Builder $query, array $characterIds): Builder
    {
        return $query->whereIn('character_id', $characterIds);
    }

    public function scopeOfType(Builder $query, string|array $types): Builder
    {
        if (is_array($types)) {
            return $query->whereIn('activity_type', $types);
        }
        return $query->where('activity_type', $types);
    }

    public function scopeInPeriod(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('activity_timestamp', [$start, $end]);
    }

    public function scopeInCorporation(Builder $query, int $corporationId): Builder
    {
        return $query->where('corporation_id', $corporationId);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('activity_timestamp', '>=', Carbon::now()->subDays($days));
    }

    public function getValueFromMetadata(): ?float
    {
        return $this->metadata['value'] ?? null;
    }

    public function getQuantityFromMetadata(): ?float
    {
        return $this->metadata['quantity'] ?? null;
    }

    public function getItemNameFromMetadata(): ?string
    {
        return $this->metadata['item_name'] ?? null;
    }
}
