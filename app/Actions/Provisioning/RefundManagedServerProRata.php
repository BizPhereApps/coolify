<?php

namespace App\Actions\Provisioning;

use App\Models\NolbaseManagedInvoice;
use App\Models\NolbaseManagedServer;
use App\Services\PaystackService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class RefundManagedServerProRata
{
    use AsAction;

    /**
     * When a managed server is decommissioned mid-month, refund the portion
     * the tenant has not yet consumed. Math:
     *
     *   refundable_line_amount = sum of line_items where managed_server_id matches
     *   remaining_days = days_in_billing_month - today.day + 1
     *   refund = round(line_amount * remaining_days / days_in_month)
     *
     * Refunds are applied against the latest successful invoice for the
     * server's team in the current calendar month. The refunded_ngn column
     * accumulates so partial-refund-then-delete-another-server is supported.
     * No-op when:
     *   - no successful invoice exists for the current month
     *   - the line item for this server is missing from the invoice
     *   - the remaining days would refund nothing
     *
     * @return array{refunded_ngn: int, invoice_id: ?int, reason: ?string}
     */
    public function handle(NolbaseManagedServer $managed, ?Carbon $now = null): array
    {
        $now ??= now();
        $billingMonth = $now->copy()->startOfMonth();

        $invoice = NolbaseManagedInvoice::query()
            ->where('team_id', $this->teamIdFor($managed))
            ->whereDate('billing_month', $billingMonth->toDateString())
            ->where('status', NolbaseManagedInvoice::STATUS_SUCCESS)
            ->whereNotNull('paystack_reference')
            ->first();

        if (! $invoice) {
            return ['refunded_ngn' => 0, 'invoice_id' => null, 'reason' => 'no_paid_invoice'];
        }

        $lineAmount = 0;
        foreach ((array) $invoice->line_items as $line) {
            if ((int) data_get($line, 'managed_server_id') === $managed->id) {
                $lineAmount = (int) data_get($line, 'price_ngn', 0);
                break;
            }
        }
        if ($lineAmount <= 0) {
            return ['refunded_ngn' => 0, 'invoice_id' => $invoice->id, 'reason' => 'server_not_on_invoice'];
        }

        $daysInMonth = $now->copy()->daysInMonth;
        $remainingDays = max(0, $daysInMonth - $now->day + 1);
        $refundAmount = (int) round($lineAmount * $remainingDays / $daysInMonth);

        // Cap so we never refund more than was charged minus what was already
        // refunded — protects against rounding-induced overdraft when multiple
        // servers on one invoice are deleted on the same day.
        $alreadyRefunded = (int) ($invoice->refunded_ngn ?? 0);
        $maxRefundable = max(0, $invoice->total_ngn - $alreadyRefunded);
        $refundAmount = min($refundAmount, $maxRefundable);

        if ($refundAmount <= 0) {
            return ['refunded_ngn' => 0, 'invoice_id' => $invoice->id, 'reason' => 'nothing_to_refund'];
        }

        try {
            PaystackService::fromConfig()->refundTransaction(
                $invoice->paystack_reference,
                $refundAmount,
            );
        } catch (\Throwable $e) {
            Log::warning('RefundManagedServerProRata: Paystack refund failed', [
                'invoice_id' => $invoice->id,
                'managed_id' => $managed->id,
                'amount' => $refundAmount,
                'error' => $e->getMessage(),
            ]);

            return ['refunded_ngn' => 0, 'invoice_id' => $invoice->id, 'reason' => 'paystack_error'];
        }

        $invoice->update([
            'refunded_ngn' => $alreadyRefunded + $refundAmount,
            'refunded_at' => now(),
        ]);

        return ['refunded_ngn' => $refundAmount, 'invoice_id' => $invoice->id, 'reason' => null];
    }

    private function teamIdFor(NolbaseManagedServer $managed): ?int
    {
        return optional($managed->server)->team_id;
    }
}
