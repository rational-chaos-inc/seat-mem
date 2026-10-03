<?php

namespace RCI\MemberEngagement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckActivityPermission
{
    public function handle(Request $request, Closure $next, string $permission = 'view_own_activities')
    {
        $user = Auth::user();

        if (!$user) {
            return redirect('/auth/login');
        }

        if (!$user->can($permission)) {
            abort(403, "You do not have permission to {$permission}");
        }

        return $next($request);
    }
}
