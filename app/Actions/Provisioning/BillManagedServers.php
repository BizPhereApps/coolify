<?php

namespace App\Actions\Provisioning;

use App\Models\NolbaseManagedInvoice;
use App\Models\NolbaseManagedServer;
use App\Models\Team;
use App\Services\PaystackService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class BillManagedServers
{
    use AsAction;

    /**
     * For every Team with at least one ACTIVE NolbaseManagedServer:
     *   - sum the price_ngn_monthly of those servers
     *   - charge the team's saved Paystack authorization for the total
     *   - record a NolbaseManagedInvoice row with line items + status
     *
     * Safe to re-run within the same billing_month: the unique index on
     * (team_id, billing_month) means duplicate runs return the existing
     * row without re-charging.
     *
     * @return array{billed: int, failed: int, skipped: int, total_ngn: int}
     */
    public function handle(?Carbon $forMonth = null): array
    {
        $forMonth = ($forMonth ?? now())->copy()->startOfMonth();

        $teamIds = NolbaseManagedServer::query()
            ->active()
            ->pluck('server_id');
        $teams = Team::query()
            ->whereHas('servers', fn ($q) => $q->whereIn('id', $teamIds))
            ->with(['subscription', 'members'])
            ->get();

        $billed = 0;
        $failed = 0;
        $skipped = 0;
        $totalNgn = 0;
        $paystack = null;

        foreach ($teams as $team) {
            $servers = NolbaseManagedServer::active()
                ->whereIn('server_id', $team->servers()->pluck('id'))
                ->get();
            if ($servers->isEmpty()) {
                $skipped++;
                continue;
            }

            // Idempotency: existing successful invoice for this month is a no-op.
            $existing = NolbaseManagedInvoice::query()
                ->where('team_id', $team->id)
                ->whereDate('billing_month', $forMonth->toDateString())
                ->first();
            if ($existing && $existing->isPaid()) {
                $skipped++;
                continue;
            }

            $lineItems = $servers->map(fn ($s) => [
                'managed_server_id' => $s->id,
                'server_id' => $s->server_id,
                'plan_slug' => $s->plan_slug,
                'price_ngn' => $s->price_ngn_monthly,
            ])->all();
            $total = (int) $servers->sum('price_ngn_monthly');

            $authCode = $team->subscription?->paystack_authorization_code;
            $billingEmail = $team->members()
                ->orderBy('team_user.role', 'desc') // owner first
                ->value('email');

            if (! $authCode || ! $billingEmail) {
                // Tenant hasn't completed Paystack-on-file setup yet. Record
                // the invoice as pending; super-admin can chase manually.
                NolbaseManagedInvoice::updateOrCreate(
                    ['team_id' => $team->id, 'billing_month' => $forMonth->toDateString()],
                    [
                        'total_ngn' => $total, 'line_items' => $lineItems,
                        'status' => NolbaseManagedInvoice::STATUS_PENDING,
                        'failure_reason' => 'No paystack_authorization_code on subscription — tenant has no card on file.',
                    ],
                );
                $skipped++;
                continue;
            }

            $reference = 'nb_mgd_'.$team->id.'_'.$forMonth->format('Ym');
            $paystack ??= PaystackService::fromConfig();

            try {
                $response = $paystack->chargeAuthorization(
                    authorizationCode: $authCode,
                    email: $billingEmail,
                    amountNgn: $total,
                    reference: $reference,
                    metadata: [
                        'kind' => 'nolbase_managed_monthly',
                        'team_id' => $team->id,
                        'billing_month' => $forMonth->toDateString(),
                    ],
                );

                $paystackStatus = data_get($response, 'status');
                $isSuccess = $paystackStatus === 'success';

                NolbaseManagedInvoice::updateOrCreate(
                    ['team_id' => $team->id, 'billing_month' => $forMonth->toDateString()],
                    [
                        'total_ngn' => $total, 'line_items' => $lineItems,
                        'paystack_reference' => $reference,
                        'status' => $isSuccess ? NolbaseManagedInvoice::STATUS_SUCCESS : NolbaseManagedInvoice::STATUS_FAILED,
                        'failure_reason' => $isSuccess ? null : data_get($response, 'gateway_response', 'Charge declined.'),
                        'billed_at' => $isSuccess ? now() : null,
                    ],
                );

                if ($isSuccess) {
                    $billed++;
                    $totalNgn += $total;
                } else {
                    $failed++;
                    // Mark all of this team's managed servers past_due. A
                    // separate decommission grace job (Phase 7.3) can then
                    // suspend after N days.
                    NolbaseManagedServer::query()
                        ->whereIn('id', $servers->pluck('id'))
                        ->update(['billing_status' => NolbaseManagedServer::BILLING_PAST_DUE]);
                }
            } catch (\Throwable $e) {
                Log::error('BillManagedServers: charge_authorization threw', [
                    'team_id' => $team->id, 'error' => $e->getMessage(),
                ]);
                NolbaseManagedInvoice::updateOrCreate(
                    ['team_id' => $team->id, 'billing_month' => $forMonth->toDateString()],
                    [
                        'total_ngn' => $total, 'line_items' => $lineItems,
                        'paystack_reference' => $reference,
                        'status' => NolbaseManagedInvoice::STATUS_FAILED,
                        'failure_reason' => $e->getMessage(),
                    ],
                );
                $failed++;
            }
        }

        return compact('billed', 'failed', 'skipped', 'totalNgn') + ['total_ngn' => $totalNgn];
    }
}
