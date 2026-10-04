<?php

return [
    'member-engagement' => [
        // Broadest permission needed to see the menu group at all - each
        // entry below is independently gated by its own 'permission' key
        // (checked via @canany in SeAT's sidebar view), so a plain member
        // only ever sees "My Activities" while a director sees all three.
        'permission' => ['member-engagement.view_own_activities', 'member-engagement.view_all_activities'],
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
            [
                'name' => 'Corporation View',
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
