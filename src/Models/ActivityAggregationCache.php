<?php

namespace RCI\MemberEngagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityAggregationCache extends Model
{
    protected $table = 'member_engagement_activity_aggregation_caches';

    protected $fillable = [
        'user_id',
        'aggregation_period',
        'activity_type',
        'period_start',
        'period_end',
        'total_value',
        'count',
        'average_value',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_value' => 'decimal:2',
        'average_value' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForPeriod(Builder $query, string $period): Builder
    {
        return $query->where('aggregation_period', $period);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('activity_type', $type);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('period_end', '>=', now()->date());
    }
}
