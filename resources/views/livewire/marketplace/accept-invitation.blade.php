<div>
    <x-slot:title>Invitation | Nolbase</x-slot>
    <section class="min-h-screen bg-gray-50 px-6 py-12 dark:bg-base">
        <div class="mx-auto max-w-2xl">
            <a href="/" class="text-sm text-neutral-500 hover:text-coollabs">&larr; Nolbase</a>

            <div class="mt-6 rounded-lg border border-coolgray-200 bg-white p-8 shadow-sm dark:bg-coolgray-100">
                <p class="text-xs uppercase text-neutral-500">Hosting invitation</p>
                <h1 class="mt-2">{{ $invitation->developerTeam->name }} invited you to host</h1>
                <p class="mt-1 text-lg font-semibold text-coollabs">{{ $invitation->project_name }}</p>

                <div class="my-6 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-md bg-coolgray-100 dark:bg-coolgray-200 p-4">
                        <div class="text-xs uppercase text-neutral-500">Offer</div>
                        <div class="mt-1 font-semibold">{{ $invitation->offer->name }}</div>
                        @if ($invitation->offer->description)
                            <p class="mt-1 text-xs text-neutral-500">{{ $invitation->offer->description }}</p>
                        @endif
                    </div>
                    <div class="rounded-md bg-coolgray-100 dark:bg-coolgray-200 p-4">
                        <div class="text-xs uppercase text-neutral-500">Allocation</div>
                        <ul class="mt-1 space-y-1 text-sm">
                            <li>{{ $invitation->offer->ram_mb }} MB RAM</li>
                            <li>{{ $invitation->offer->disk_gb }} GB disk</li>
                            <li>{{ $invitation->offer->max_apps }} application{{ $invitation->offer->max_apps === 1 ? '' : 's' }}</li>
                            @if ($invitation->offer->max_databases > 0)
                                <li>{{ $invitation->offer->max_databases }} database{{ $invitation->offer->max_databases === 1 ? '' : 's' }}</li>
                            @endif
                            @if ($invitation->offer->allow_custom_domain)
                                <li>Custom domain allowed</li>
                            @endif
                        </ul>
                    </div>
                </div>

                @if ($invitation->isPending())
                    <div class="mb-6">
                        <div class="mb-2 flex items-center gap-2">
                            <button wire:click="setPeriod('monthly')"
                                    class="rounded-md px-3 py-1.5 text-sm {{ $period === 'monthly' ? 'bg-coollabs text-white' : 'bg-coolgray-200 text-neutral-400' }}">
                                Monthly
                            </button>
                            @if ($invitation->offer->price_ngn_annual)
                                <button wire:click="setPeriod('annual')"
                                        class="rounded-md px-3 py-1.5 text-sm {{ $period === 'annual' ? 'bg-coollabs text-white' : 'bg-coolgray-200 text-neutral-400' }}">
                                    Annual
                                </button>
                            @endif
                        </div>
                        <div class="rounded-md bg-coolgray-100 dark:bg-coolgray-200 p-4">
                            <div class="flex items-baseline justify-between">
                                <span class="text-sm text-neutral-500">Price</span>
                                <span class="text-3xl font-bold">
                                    ₦{{ number_format($period === 'annual' ? $invitation->offer->price_ngn_annual : $invitation->offer->price_ngn_monthly) }}
                                </span>
                            </div>
                            <div class="mt-1 text-right text-xs text-neutral-500">per {{ $period === 'annual' ? 'year' : 'month' }}</div>
                        </div>
                        <p class="mt-2 text-xs text-neutral-500">
                            Billed by {{ $invitation->developerTeam->name }}. Payment is processed securely by Paystack. You can cancel anytime.
                        </p>
                    </div>

                    @error('pay')
                        <p class="mb-4 rounded border border-error/30 bg-error/10 p-3 text-sm text-error">{{ $message }}</p>
                    @enderror

                    <button wire:click="pay" wire:loading.attr="disabled"
                            class="button w-full justify-center bg-coollabs py-3 text-white">
                        <span wire:loading.remove wire:target="pay">Accept &amp; pay with Paystack</span>
                        <span wire:loading wire:target="pay">Redirecting to Paystack…</span>
                    </button>

                    <p class="mt-3 text-center text-xs text-neutral-500">
                        Invitation sent to <span class="font-mono">{{ $invitation->email }}</span> ·
                        Expires {{ $invitation->expires_at->diffForHumans() }}
                    </p>
                @else
                    <div class="rounded-md border border-warning/40 bg-warning/10 p-4 text-sm text-warning">
                        @if ($invitation->accepted_at)
                            This invitation was already accepted on {{ $invitation->accepted_at->format('Y-m-d') }}.
                        @elseif ($invitation->cancelled_at)
                            This invitation was cancelled.
                        @else
                            This invitation has expired. Ask your developer to send a new one.
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>
