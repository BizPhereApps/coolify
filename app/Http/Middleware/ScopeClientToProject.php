<?php

namespace App\Http\Middleware;

use App\Models\SubTeam;
use Closure;
use Illuminate\Http\Request;

/**
 * If the authenticated user is a Client (has at least one active SubTeam
 * row), shunt them to /client. They are not allowed to navigate the
 * regular tenant UI — they only see their assigned project.
 *
 * Sits in the same auth+verified group as the tenant dashboard. Runs
 * AFTER DecideWhatToDoWithUser so the suspension block and email
 * verification redirects still apply first.
 */
class ScopeClientToProject
{
    /**
     * Paths the Client is permitted to access (everything else 302s to /client).
     */
    public const ALLOWED_PREFIXES = [
        'client',
        'logout',
        'verify',
        'forgot-password',
        'password',
        'two-factor-challenge',
        'livewire/update',
        'livewire/upload-file',
        'livewire/preview-file',
        'payments/paystack/marketplace-callback',
        'legal/source',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $isClient = SubTeam::query()
            ->where('client_user_id', $user->id)
            ->whereNull('terminated_at')
            ->exists();

        if (! $isClient) {
            return $next($request);
        }

        $path = $request->path();
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return $next($request);
            }
        }

        return redirect('/client');
    }
}
