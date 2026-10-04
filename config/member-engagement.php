<?php

return [
    'enabled' => env('MEMBER_ENGAGEMENT_ENABLED', true),

    'aggregation_cache_ttl' => env('MEMBER_ENGAGEMENT_CACHE_TTL', 0),

    'time_windows' => [
        'day' => 1,
        'week' => 7,
        'month' => 30,
        'quarter' => 90,
        'year' => 365,
    ],

    'activity_types' => [
        'mining',
        'pvp_kill',
        'pvp_loss',
        'pve_bounty_tax',
        'industry_tax',
    ],
];
