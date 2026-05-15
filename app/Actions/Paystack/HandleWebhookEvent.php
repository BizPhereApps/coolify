<?php

namespace App\Actions\Paystack;

use App\Models\PaystackEvent;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleWebhookEvent
{
    use AsAction;

    public function handle(PaystackEvent $event): void
    {
        if ($event->isProcessed()) {
            return;
        }

        try {
            match ($event->event_type) {
                'subscription.create' => $this->onSubscriptionCreate($event),
                'subscription.disable',
                'subscription.not_renew' => $this->onSubscriptionDisable($event),
                'invoice.create' => $this->onInvoiceCreate($event),
                'invoice.payment_failed' => $this->onInvoicePaymentFailed($event),
                'charge.success' => $this->onChargeSuccess($event),
                default => Log::info("Unhandled Paystack event: {$event->event_type}", ['id' => $event->id]),
            };

            $event->update(['processed_at' => now(), 'error' => null]);
        } catch (\Throwable $e) {
            $event->update(['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function onSubscriptionCreate(PaystackEvent $event): void
    {
        $data = data_get($event->payload, 'data', []);
        $subscriptionCode = data_get($data, 'subscription_code');
        $customerCode = data_get($data, 'customer.customer_code');
        $emailToken = data_get($data, 'email_token');

        $subscription = $this->resolveByCustomer($customerCode);
        if (! $subscription) {
            return;
        }

        $subscription->update([
            'paystack_subscription_code' => $subscriptionCode,
            'paystack_customer_code' => $customerCode,
            'paystack_email_token' => $emailToken,
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_end' => $this->parseDate(data_get($data, 'next_payment_date')) ?? $subscription->current_period_end,
        ]);
    }

    private function onSubscriptionDisable(PaystackEvent $event): void
    {
        $code = data_get($event->payload, 'data.subscription_code');
        $subscription = Subscription::where('paystack_subscription_code', $code)->first();
        if (! $subscription) {
            return;
        }

        $subscription->update([
            'status' => Subscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancel_at_period_end' => false,
        ]);
    }

    private function onInvoiceCreate(PaystackEvent $event): void
    {
        // Informational only — Paystack is about to charge for renewal.
        Log::info('Paystack invoice.create', ['paystack_event_id' => $event->id]);
    }

    private function onInvoicePaymentFailed(PaystackEvent $event): void
    {
        $customerCode = data_get($event->payload, 'data.customer.customer_code');
        $subscription = $this->resolveByCustomer($customerCode);
        if (! $subscription) {
            return;
        }

        $subscription->update([
            'status' => Subscription::STATUS_PAST_DUE,
            'last_payment_failed_at' => now(),
        ]);

        $ownerEmail = $subscription->team?->members()
            ->wherePivot('role', 'owner')
            ->value('email');
        if (! $ownerEmail) {
            return;
        }

        try {
            \Illuminate\Support\Facades\Mail::send(
                'emails.subscription-invoice-failed',
                ['subscriptionUrl' => url('/subscription')],
                fn (\Symfony\Component\Mime\Email $m) => $m
                    ->to($ownerEmail)
                    ->subject('Your Nolbase subscription renewal failed'),
            );
        } catch (\Throwable $e) {
            Log::warning('subscription-invoice-failed email failed', [
                'team_id' => $subscription->team_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function onChargeSuccess(PaystackEvent $event): void
    {
        $customerCode = data_get($event->payload, 'data.customer.customer_code');
        $subscription = $this->resolveByCustomer($customerCode);
        if (! $subscription) {
            return;
        }

        if ($subscription->isPastDue()) {
            $subscription->update([
                'status' => Subscription::STATUS_ACTIVE,
                'last_payment_failed_at' => null,
            ]);
        }
    }

    private function resolveByCustomer(?string $customerCode): ?Subscription
    {
        if (! $customerCode) {
            return null;
        }

        return Subscription::where('paystack_customer_code', $customerCode)->first();
    }

    private function parseDate(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }
}
