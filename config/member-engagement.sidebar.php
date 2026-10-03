<?php

return [
    'member-engagement' => [
        'permission' => 'view_own_activities',
        'name' => 'Member Engagement Module',
        'icon' => 'fas fa-chart-bar',
        'route_segment' => 'member-engagement',
        'entries' => [
            [
                'name' => 'My Activities',
                'icon' => 'fas fa-chart-line',
                'route' => 'member-engagement.dashboard',
                'permission' => 'view_own_activities',
            ],
        ],
    ],
    'member-engagement-director' => [
        'permission' => 'view_all_activities',
        'name' => 'Member Engagement Module (Director)',
        'icon' => 'fas fa-crown',
        'route_segment' => 'member-engagement-director',
        'entries' => [
            [
                'name' => 'Corporation Dashboard',
                'icon' => 'fas fa-chart-area',
                'route' => 'member-engagement.director.index',
                'permission' => 'view_all_activities',
            ],
            [
                'name' => 'League Tables',
                'icon' => 'fas fa-trophy',
                'route' => 'member-engagement.league-tables',
                'permission' => 'view_all_activities',
            ],
        ],
    ],
];
