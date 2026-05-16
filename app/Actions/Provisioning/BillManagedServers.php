<?php

namespace App\Actions\Provisioning;

use App\Models\NolbaseManagedInvoice;
use App\Models\NolbaseManagedServer;
use App\Models\Team;
use App\Services\NolbaseAlert;
use App\Services\PaystackService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Mime\Email;

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
            ->billable()
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
            $servers = NolbaseManagedServer::billable()
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
                $this->upsertInvoice($team->id, $forMonth, [
                    'total_ngn' => $total, 'line_items' => $lineItems,
                    'status' => NolbaseManagedInvoice::STATUS_PENDING,
                    'failure_reason' => 'No paystack_authorization_code on subscription — tenant has no card on file.',
                ]);
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

                $this->upsertInvoice($team->id, $forMonth, [
                    'total_ngn' => $total, 'line_items' => $lineItems,
                    'paystack_reference' => $reference,
                    'status' => $isSuccess ? NolbaseManagedInvoice::STATUS_SUCCESS : NolbaseManagedInvoice::STATUS_FAILED,
                    'failure_reason' => $isSuccess ? null : data_get($response, 'gateway_response', 'Charge declined.'),
                    'billed_at' => $isSuccess ? now() : null,
                ]);

                if ($isSuccess) {
                    $billed++;
                    $totalNgn += $total;
                    // Clear any prior past_due_since stamps now that the team
                    // is current again. Status stays whatever it is (active
                    // for those that hadn't tripped; suspended servers must be
                    // resurrected manually by an admin since the Hetzner
                    // droplet was powered off).
                    NolbaseManagedServer::query()
                        ->whereIn('id', $servers->pluck('id'))
                        ->where('billing_status', NolbaseManagedServer::BILLING_PAST_DUE)
                        ->update([
                            'billing_status' => NolbaseManagedServer::BILLING_ACTIVE,
                            'past_due_since' => null,
                        ]);
                } else {
                    $failed++;
                    // Mark all of this team's managed servers past_due and
                    // stamp past_due_since on first transition so the
                    // SuspendPastDueManagedServersJob grace clock starts.
                    NolbaseManagedServer::query()
                        ->whereIn('id', $servers->pluck('id'))
                        ->where('billing_status', '!=', NolbaseManagedServer::BILLING_PAST_DUE)
                        ->update([
                            'billing_status' => NolbaseManagedServer::BILLING_PAST_DUE,
                            'past_due_since' => now(),
                        ]);

                    $this->notifyBillingFailed($team, $billingEmail, $total, data_get($response, 'gateway_response'));
                }
            } catch (\Throwable $e) {
                Log::error('BillManagedServers: charge_authorization threw', [
                    'team_id' => $team->id, 'error' => $e->getMessage(),
                ]);
                $this->upsertInvoice($team->id, $forMonth, [
                    'total_ngn' => $total, 'line_items' => $lineItems,
                    'paystack_reference' => $reference,
                    'status' => NolbaseManagedInvoice::STATUS_FAILED,
                    'failure_reason' => $e->getMessage(),
                ]);
                NolbaseManagedServer::query()
                    ->whereIn('id', $servers->pluck('id'))
                    ->where('billing_status', '!=', NolbaseManagedServer::BILLING_PAST_DUE)
                    ->update([
                        'billing_status' => NolbaseManagedServer::BILLING_PAST_DUE,
                        'past_due_since' => now(),
                    ]);
                $this->notifyBillingFailed($team, $billingEmail, $total, $e->getMessage());
                $failed++;
            }
        }

        return compact('billed', 'failed', 'skipped', 'totalNgn') + ['total_ngn' => $totalNgn];
    }

    /**
     * Equivalent of updateOrCreate keyed on (team_id, billing_month), but uses
     * whereDate for the lookup so the stored date-with-time value matches the
     * date-only lookup string. The plain updateOrCreate fails here because the
     * Eloquent `date` cast persists `Y-m-d H:i:s` while the lookup string is
     * `Y-m-d` — see Phase 7.3 fix.
     */
    private function upsertInvoice(int $teamId, Carbon $forMonth, array $attributes): NolbaseManagedInvoice
    {
        $invoice = NolbaseManagedInvoice::query()
            ->where('team_id', $teamId)
            ->whereDate('billing_month', $forMonth->toDateString())
            ->first()
            ?? new NolbaseManagedInvoice([
                'team_id' => $teamId,
                'billing_month' => $forMonth->copy(),
            ]);
        $invoice->fill($attributes + [
            'team_id' => $teamId,
            'billing_month' => $forMonth->copy(),
        ])->save();

        return $invoice;
    }

    private function notifyBillingFailed(Team $team, string $billingEmail, int $totalNgn, ?string $reason): void
    {
        try {
            Mail::send(
                'emails.managed-billing-failed',
                [
                    'teamName' => $team->name,
                    'totalNgn' => $totalNgn,
                    'reason' => $reason ?: 'Charge declined.',
                    'subscriptionUrl' => url('/subscription'),
                ],
                fn (Email $m) => $m->to($billingEmail)
                    ->subject('Action required: your Nolbase monthly hosting bill failed'),
            );
        } catch (\Throwable $e) {
            Log::warning('managed-billing-failed email send failed', [
                'team_id' => $team->id, 'error' => $e->getMessage(),
            ]);
        }

        NolbaseAlert::send(
            title: 'Managed-server billing failed',
            message: 'Charge of ₦'.number_format($totalNgn)." for team {$team->name} failed. Tenant has been emailed; servers flipped to past_due.",
            level: NolbaseAlert::LEVEL_WARN,
            context: [
                'team_id' => $team->id,
                'amount_ngn' => $totalNgn,
                'reason' => $reason,
            ],
        );
    }
}
