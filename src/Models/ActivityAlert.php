<?php

namespace RCI\MemberEngagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityAlert extends Model
{
    protected $table = 'member_engagement_activity_alerts';

    protected $fillable = [
        'user_id',
        'alert_type',
        'conditions',
        'notification_method',
        'is_active',
        'last_triggered_at',
    ];

    protected $casts = [
        'conditions' => 'json',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('alert_type', $type);
    }

    public function markAsTriggered(): void
    {
        $this->update(['last_triggered_at' => now()]);
    }

    public function getActivityTypeFilter(): array
    {
        return $this->conditions['activity_types'] ?? [];
    }

    public function getValueThreshold(): ?float
    {
        return $this->conditions['value_threshold'] ?? null;
    }

    public function getTimeWindow(): ?string
    {
        return $this->conditions['time_window'] ?? null;
    }
}
