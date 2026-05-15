<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequireNolbaseAdmin
{
    public function handle(Request $request, Closure $next, ?string $minimumRole = null)
    {
        if (! Auth::guard('nolbase')->check()) {
            return redirect()->route('nolbase.admin.login');
        }

        if ($minimumRole !== null) {
            $admin = Auth::guard('nolbase')->user();
            $rank = ['support' => 1, 'staff' => 2, 'superadmin' => 3];
            if (($rank[$admin->role] ?? 0) < ($rank[$minimumRole] ?? 99)) {
                abort(403, 'Insufficient privileges.');
            }
        }

        return $next($request);
    }
}
