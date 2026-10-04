<?php

namespace RCI\MemberEngagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController
{
    public function member(Request $request)
    {
        $user = Auth::user();

        if (!$user->can('member-engagement.view_own_activities')) {
            abort(403, 'Unauthorized');
        }

        // Data display disabled for now - collection/aggregation keeps
        // running in the background; see DirectorController for the feed.
        return view('member-engagement::dashboard.member');
    }
}
