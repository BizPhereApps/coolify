<div>
    <x-slot:title>Subscription | Nolbase</x-slot>
    <h1>Subscription</h1>
    <div class="subtitle">Manage your plan and billing.</div>

    @if (! $subscription)
        <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-6">
            <h2 class="mb-2">No subscription</h2>
            <p class="mb-4 text-neutral-400">You don't have an active subscription. Pick a plan to get started.</p>
            <a href="{{ route('subscription.pricing') }}" class="button">View plans</a>
        </div>
    @else
            <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-6">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="mb-1">{{ $plan?->name ?? 'Unknown plan' }}</h2>
                        <p class="text-neutral-400">
                            @if ($subscription->isOnTrial())
                                Trial — ends {{ $subscription->trial_ends_at?->diffForHumans() }}
                            @elseif ($subscription->isPastDue())
                                <span class="text-warning">Past due — please update your payment method.</span>
                            @elseif ($subscription->isCancelled())
                                Cancelled {{ $subscription->cancelled_at?->diffForHumans() }}
                            @elseif ($subscription->cancel_at_period_end)
                                Cancelling at {{ $subscription->current_period_end?->format('Y-m-d') }}
                            @else
                                Active — renews {{ $subscription->current_period_end?->format('Y-m-d') }}
                            @endif
                        </p>
                    </div>
                    <div class="text-right">
                        @if ($plan)
                            <div class="text-2xl font-bold">
                                ₦{{ number_format($subscription->period === \App\Models\Subscription::PERIOD_ANNUAL ? $plan->price_ngn_annual : $plan->price_ngn_monthly) }}
                            </div>
                            <div class="text-xs text-neutral-500">per {{ $subscription->billingInterval() === 'yearly' ? 'year' : 'month' }}</div>
                        @endif
                    </div>
                </div>

            <div class="mt-6 flex gap-2">
                <a href="{{ route('subscription.pricing') }}" class="button">Change plan</a>
                <livewire:subscription.actions :subscription="$subscription" />
            </div>
        </div>
    @endif
</div>
