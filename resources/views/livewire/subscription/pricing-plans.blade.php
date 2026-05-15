<div>
    <div class="mb-6 flex items-center justify-center gap-2">
        <button wire:click="setPeriod('monthly')"
                class="rounded-md px-3 py-1.5 text-sm {{ $period === 'monthly' ? 'bg-coollabs text-white' : 'bg-coolgray-200 text-neutral-400' }}">
            Monthly
        </button>
        <button wire:click="setPeriod('annual')"
                class="rounded-md px-3 py-1.5 text-sm {{ $period === 'annual' ? 'bg-coollabs text-white' : 'bg-coolgray-200 text-neutral-400' }}">
            Annual <span class="ml-1 text-xs text-success">2 months free</span>
        </button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($this->plans as $plan)
            @php
                $price = $period === 'annual' ? $plan->price_ngn_annual : $plan->price_ngn_monthly;
                $isCurrent = $this->currentPlanCode === $plan->code;
            @endphp
            <div class="flex flex-col rounded-md border @if ($plan->code === 'pro') border-coollabs @else border-coolgray-200 @endif bg-coolgray-100 p-6">
                <div class="mb-4">
                    <h3 class="mb-1">{{ $plan->name }}</h3>
                    <p class="text-sm text-neutral-400">{{ $plan->description }}</p>
                </div>

                <div class="mb-6">
                    @if ($price === null && $plan->code !== 'free')
                        <div class="text-sm text-neutral-500">Annual not available</div>
                    @else
                        <div class="text-3xl font-bold">
                            ₦{{ number_format($price ?? 0) }}
                        </div>
                        <div class="text-xs text-neutral-500">
                            @if ($plan->code === 'free')
                                forever
                            @else
                                per {{ $period === 'annual' ? 'year' : 'month' }}
                            @endif
                        </div>
                    @endif
                </div>

                <ul class="mb-6 space-y-2 text-sm text-neutral-400">
                    <li>{{ $plan->max_servers === 0 ? 'Unlimited' : $plan->max_servers }} server{{ $plan->max_servers === 1 ? '' : 's' }}</li>
                    <li>{{ $plan->max_apps === 0 ? 'Unlimited' : $plan->max_apps }} app{{ $plan->max_apps === 1 ? '' : 's' }}</li>
                    <li>{{ $plan->max_databases === 0 ? 'Unlimited' : $plan->max_databases }} database{{ $plan->max_databases === 1 ? '' : 's' }}</li>
                    <li>{{ $plan->max_team_members === 0 ? 'Unlimited' : $plan->max_team_members }} team member{{ $plan->max_team_members === 1 ? '' : 's' }}</li>
                    @foreach (($plan->features ?? []) as $feature)
                        <li class="text-success">✓ {{ str_replace('_', ' ', $feature) }}</li>
                    @endforeach
                </ul>

                <div class="mt-auto">
                    @if ($isCurrent)
                        <button class="button w-full" disabled>Current plan</button>
                    @elseif ($plan->code === 'free')
                        <button class="button w-full" disabled>Default</button>
                    @elseif ($period === 'annual' && $price === null)
                        <button class="button w-full" disabled>Switch to monthly</button>
                    @else
                        <button wire:click="choose({{ $plan->id }})" class="button w-full bg-coollabs text-white">
                            Choose {{ $plan->name }}
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
