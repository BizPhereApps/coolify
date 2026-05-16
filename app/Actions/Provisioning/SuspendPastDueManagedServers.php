<?php

namespace App\Actions\Provisioning;

use App\Models\NolbaseManagedServer;
use App\Models\NolbaseSetting;
use App\Services\HetznerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Mime\Email;

class SuspendPastDueManagedServers
{
    use AsAction;

    /**
     * For every managed server whose past_due_since is older than the
     * configured grace window:
     *   - power down the underlying Hetzner droplet (so we stop paying for
     *     compute; storage stays so the customer can recover by paying)
     *   - flip billing_status to suspended, stamp suspended_at
     *   - notify the team owner with a "your hosting has been suspended" email
     *
     * Safe to re-run: only acts on past_due rows, and the status flip is
     * idempotent. Hetzner errors are logged but do not block the status flip
     * — the bill stops accruing regardless.
     *
     * @return array{suspended: int, examined: int}
     */
    public function handle(?int $graceDays = null): array
    {
        $graceDays ??= (int) (NolbaseSetting::read('managed_past_due_grace_days') ?: 7);
        $cutoff = Carbon::now()->subDays($graceDays);

        $candidates = NolbaseManagedServer::query()
            ->where('billing_status', NolbaseManagedServer::BILLING_PAST_DUE)
            ->whereNotNull('past_due_since')
            ->where('past_due_since', '<=', $cutoff)
            ->with(['server.team.members'])
            ->get();

        $suspended = 0;
        $hetznerToken = NolbaseSetting::read('nolbase_hetzner_api_token') ?: env('NOLBASE_HETZNER_API_TOKEN');

        foreach ($candidates as $managed) {
            if ($managed->provider === 'hetzner' && $managed->provider_resource_id && $hetznerToken) {
                try {
                    (new HetznerService($hetznerToken))->powerOffServer((int) $managed->provider_resource_id);
                } catch (\Throwable $e) {
                    Log::warning('SuspendPastDueManagedServers: Hetzner powerOff failed', [
                        'managed_id' => $managed->id,
                        'hetzner_id' => $managed->provider_resource_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $managed->update([
                'billing_status' => NolbaseManagedServer::BILLING_SUSPENDED,
                'suspended_at' => now(),
            ]);
            $suspended++;

            $this->notifySuspended($managed);
        }

        return ['suspended' => $suspended, 'examined' => $candidates->count()];
    }

    private function notifySuspended(NolbaseManagedServer $managed): void
    {
        $team = $managed->server?->team;
        if (! $team) {
            return;
        }

        $ownerEmail = $team->members()
            ->wherePivot('role', 'owner')
            ->value('email');
        if (! $ownerEmail) {
            return;
        }

        try {
            Mail::send(
                'emails.managed-suspended',
                [
                    'teamName' => $team->name,
                    'serverName' => $managed->server->name ?? 'your managed server',
                    'subscriptionUrl' => url('/subscription'),
                ],
                fn (Email $m) => $m->to($ownerEmail)
                    ->subject('Your Nolbase managed server has been suspended'),
            );
        } catch (\Throwable $e) {
            Log::warning('managed-suspended email send failed', [
                'managed_id' => $managed->id, 'error' => $e->getMessage(),
            ]);
        }
    }
}
