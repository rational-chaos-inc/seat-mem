<?php

return [
    'member-engagement' => [
        'permission' => 'member-engagement.view_own_activities',
        'name' => 'Member Engagement Module',
        'icon' => 'fas fa-chart-bar',
        'route_segment' => 'member-engagement',
        'entries' => [
            [
                'name' => 'My Activities',
                'icon' => 'fas fa-chart-line',
                'route' => 'member-engagement.dashboard',
                'permission' => 'member-engagement.view_own_activities',
            ],
        ],
    ],
    'member-engagement-director' => [
        'permission' => 'member-engagement.view_all_activities',
        'name' => 'Member Engagement Module (Director)',
        'icon' => 'fas fa-crown',
        'route_segment' => 'member-engagement-director',
        'entries' => [
            [
                'name' => 'Corporation Dashboard',
                'icon' => 'fas fa-chart-area',
                'route' => 'member-engagement.director.index',
                'permission' => 'member-engagement.view_all_activities',
            ],
            [
                'name' => 'Settings',
                'icon' => 'fas fa-cog',
                'route' => 'member-engagement.settings',
                'permission' => 'member-engagement.view_all_activities',
            ],
        ],
    ],
];
