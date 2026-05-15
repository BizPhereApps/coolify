<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireResellerFeature
{
    public function handle(Request $request, Closure $next)
    {
        $team = currentTeam();
        if (! $team || ! $team->canResell()) {
            return redirect()->route('subscription.pricing')->withErrors([
                'plan' => 'The Reseller marketplace is available on Pro and Business plans. Upgrade to unlock it.',
            ]);
        }

        return $next($request);
    }
}
