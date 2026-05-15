<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DecideWhatToDoWithUser
{
    public function handle(Request $request, Closure $next): Response
    {
        // Nolbase: suspended teams are denied access entirely. Log them out
        // and send them back to login with a notice. Pre-auth requests pass
        // through unchanged.
        //
        // currentTeam() is the method that reads from session — distinct from
        // the transient $user->currentTeam attribute that's only set after
        // refreshSession() runs (which happens later in this middleware).
        $user = auth()?->user();
        if ($user) {
            $team = $user->currentTeam() ?? $user->teams()->first();
            if ($team && $team->nolbase_status === 'suspended' && ! $request->routeIs('login')) {
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => 'Your account has been suspended. Contact '.config('nolbase.support_email').'.',
                ]);
            }
        }

        if (auth()?->user()?->teams?->count() === 0) {
            $currentTeam = auth()->user()?->recreate_personal_team();
            refreshSession($currentTeam);
        }
        if (auth()?->user()?->currentTeam()) {
            refreshSession(auth()->user()->currentTeam());
        } elseif (auth()?->user()?->teams?->count() > 0) {
            // User's session team is invalid (e.g., removed from team), switch to first available team
            refreshSession(auth()->user()->teams->first());
        }
        if (! auth()->user() || ! isCloud()) {
            if (! isCloud() && showBoarding() && ! in_array($request->path(), allowedPathsForBoardingAccounts())) {
                return redirect()->route('onboarding');
            }

            return $next($request);
        }
        // Instance admins can access settings and admin routes regardless of subscription
        if (isInstanceAdmin() && ($request->routeIs('settings.*') || $request->path() === 'admin')) {
            return $next($request);
        }
        if (! auth()->user()->hasVerifiedEmail()) {
            if ($request->path() === 'verify' || in_array($request->path(), allowedPathsForInvalidAccounts()) || $request->routeIs('verify.verify')) {
                return $next($request);
            }

            return redirect()->route('verify.email');
        }
        if (! isSubscriptionActive() && ! isSubscriptionOnGracePeriod()) {
            if (! in_array($request->path(), allowedPathsForUnsubscribedAccounts())) {
                if (Str::startsWith($request->path(), 'invitations')) {
                    return $next($request);
                }

                return redirect()->route('subscription.pricing');
            }
        }
        if (showBoarding() && ! in_array($request->path(), allowedPathsForBoardingAccounts())) {
            if (Str::startsWith($request->path(), 'invitations')) {
                return $next($request);
            }

            return redirect()->route('onboarding');
        }
        if (auth()->user()->hasVerifiedEmail() && $request->path() === 'verify') {
            return redirect(RouteServiceProvider::HOME);
        }
        if (isSubscriptionActive() && $request->routeIs('subscription.pricing')) {
            return redirect(RouteServiceProvider::HOME);
        }

        return $next($request);
    }
}
