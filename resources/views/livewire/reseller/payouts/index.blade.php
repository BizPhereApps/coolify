<div>
    <x-slot:title>Payouts | Nolbase</x-slot>
    <div class="flex items-baseline justify-between">
        <div>
            <h1>Payouts</h1>
            <div class="subtitle">Pending balance + payout history.</div>
        </div>
        <a href="{{ route('reseller.payouts.edit') }}" class="button">{{ $account ? 'Update' : 'Add' }} payout account</a>
    </div>

    <section class="mt-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
            <div class="text-xs uppercase text-neutral-500">Pending balance</div>
            <div class="mt-1 text-2xl font-bold">₦{{ number_format($pendingBalance) }}</div>
            <div class="mt-1 text-xs text-neutral-500">{{ $pendingChargesCount }} charge(s) awaiting payout</div>
            @if ($pendingBalance > 0 && $pendingBalance < $minPayout)
                <div class="mt-2 text-xs text-warning">
                    Below the ₦{{ number_format($minPayout) }} minimum. Will roll forward to the next run.
                </div>
            @endif
        </div>
        <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4 sm:col-span-2">
            <div class="text-xs uppercase text-neutral-500">Payout account</div>
            @if ($account && $account->isVerified())
                <div class="mt-1 text-sm">
                    <span class="font-semibold">{{ $account->account_name }}</span>
                    <span class="text-neutral-500"> · {{ $account->maskedAccountNumber() }}</span>
                </div>
                <div class="text-xs text-neutral-500">Verified {{ $account->verified_at->diffForHumans() }}</div>
            @else
                <p class="mt-1 text-sm text-neutral-500">
                    No verified payout account yet. <a href="{{ route('reseller.payouts.edit') }}" class="text-coollabs underline">Add one</a> so we can transfer your earnings.
                </p>
            @endif
        </div>
    </section>

    <section class="mt-8">
        <h2 class="mb-3">Recent payouts</h2>
        @if ($payouts->isEmpty())
            <p class="text-sm text-neutral-500">No payouts sent yet.</p>
        @else
            <div class="overflow-hidden rounded-md border border-coolgray-200 bg-coolgray-100">
                <table class="w-full text-sm">
                    <thead class="border-b border-coolgray-200 text-xs uppercase">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Amount</th>
                            <th class="px-4 py-2 text-left">Status</th>
                            <th class="px-4 py-2 text-left">Reference</th>
                            <th class="px-4 py-2 text-left">Charges covered</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payouts as $p)
                            <tr class="border-t border-coolgray-200/40">
                                <td class="px-4 py-2 text-xs text-neutral-500">{{ $p->occurred_at?->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-2">₦{{ number_format($p->amount_ngn) }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <span class="rounded-full bg-coolgray-200 px-2 py-0.5">{{ $p->status }}</span>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs text-neutral-500">{{ $p->paystack_reference }}</td>
                                <td class="px-4 py-2 text-xs text-neutral-500">
                                    {{ count(data_get($p->payload, 'charge_ids', [])) }} charge(s)
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="mt-8">
        <h2 class="mb-3">Recent client charges</h2>
        @if ($recentCharges->isEmpty())
            <p class="text-sm text-neutral-500">No client charges yet. Send an invitation to start.</p>
        @else
            <div class="overflow-hidden rounded-md border border-coolgray-200 bg-coolgray-100">
                <table class="w-full text-sm">
                    <thead class="border-b border-coolgray-200 text-xs uppercase">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-right">Charged</th>
                            <th class="px-4 py-2 text-right">Platform fee</th>
                            <th class="px-4 py-2 text-right">Your net</th>
                            <th class="px-4 py-2 text-left">Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentCharges as $c)
                            <tr class="border-t border-coolgray-200/40">
                                <td class="px-4 py-2 text-xs text-neutral-500">{{ $c->occurred_at?->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-2 text-right">₦{{ number_format($c->amount_ngn) }}</td>
                                <td class="px-4 py-2 text-right text-neutral-500">−₦{{ number_format($c->fee_ngn) }}</td>
                                <td class="px-4 py-2 text-right font-semibold text-success">₦{{ number_format($c->net_ngn) }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-neutral-500">{{ $c->paystack_reference }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
