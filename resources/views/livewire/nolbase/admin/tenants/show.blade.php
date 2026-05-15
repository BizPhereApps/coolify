<div class="mx-auto max-w-5xl px-6 py-8">
    <x-slot:title>Nolbase Admin · {{ $team->name }}</x-slot>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="{{ route('nolbase.admin.tenants.index') }}" class="text-sm text-neutral-500 hover:text-coollabs">&larr; Tenants</a>
            <h1 class="mt-1">{{ $team->name }}</h1>
            <p class="text-xs text-neutral-500">Team #{{ $team->id }} · created {{ $team->created_at?->format('Y-m-d') }}</p>
        </div>
        <div class="flex gap-2">
            @if ($team->nolbase_status === 'suspended')
                <button wire:click="unsuspend" wire:confirm="Reactivate this tenant?" class="button">Unsuspend</button>
            @else
                <button wire:click="suspend" wire:confirm="Suspend this tenant? They won't be able to log in." class="button bg-warning text-black">Suspend</button>
            @endif
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
            <div class="mb-3 text-sm font-semibold">Subscription</div>
            @if ($team->subscription)
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-neutral-500">Plan</dt><dd>{{ $team->subscription->plan?->name }}</dd></div>
                    <div class="flex justify-between"><dt class="text-neutral-500">Status</dt><dd>{{ $team->subscription->status }}</dd></div>
                    <div class="flex justify-between"><dt class="text-neutral-500">Period</dt><dd>{{ $team->subscription->period }}</dd></div>
                    @if ($team->subscription->trial_ends_at)
                        <div class="flex justify-between"><dt class="text-neutral-500">Trial ends</dt><dd>{{ $team->subscription->trial_ends_at->format('Y-m-d') }}</dd></div>
                    @endif
                    @if ($team->subscription->current_period_end)
                        <div class="flex justify-between"><dt class="text-neutral-500">Renews</dt><dd>{{ $team->subscription->current_period_end->format('Y-m-d') }}</dd></div>
                    @endif
                    @if ($team->subscription->paystack_customer_code)
                        <div class="flex justify-between"><dt class="text-neutral-500">Paystack customer</dt><dd class="font-mono text-xs">{{ $team->subscription->paystack_customer_code }}</dd></div>
                    @endif
                </dl>
            @else
                <p class="text-sm text-neutral-500">No subscription.</p>
            @endif
        </div>

        <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
            <div class="mb-3 text-sm font-semibold">Usage</div>
            @foreach (['servers' => 'Servers', 'apps' => 'Applications', 'databases' => 'Databases', 'team_members' => 'Team members'] as $key => $label)
                @php $u = $usage[$key]; @endphp
                <div class="flex justify-between text-sm">
                    <span class="text-neutral-500">{{ $label }}</span>
                    <span>{{ $u['used'] }} / {{ $u['unlimited'] ? '∞' : $u['limit'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4 sm:col-span-2">
            <div class="mb-3 text-sm font-semibold">Members</div>
            <ul class="space-y-1 text-sm">
                @foreach ($team->members as $member)
                    <li class="flex justify-between">
                        <span>{{ $member->name }} <span class="text-neutral-500">({{ $member->email }})</span></span>
                        <span class="text-xs text-neutral-500">{{ $member->pivot->role }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
