<div class="mx-auto max-w-5xl px-6 py-8">
    <x-slot:title>Nolbase Admin · {{ $team->name }}</x-slot>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="{{ route('nolbase.admin.tenants.index') }}" class="text-sm text-neutral-500 hover:text-coollabs">&larr; Tenants</a>
            <h1 class="mt-1">{{ $team->name }}</h1>
            <p class="text-xs text-neutral-500">Team #{{ $team->id }} · created {{ $team->created_at?->format('Y-m-d') }}</p>
        </div>
        @if (auth('nolbase')->user()?->canSuspendTenants())
            <div class="flex gap-2">
                @if ($team->nolbase_status === 'suspended')
                    <button wire:click="unsuspend" wire:confirm="Reactivate this tenant?" class="button">Unsuspend</button>
                @else
                    <button wire:click="suspend" wire:confirm="Suspend this tenant? They won't be able to log in." class="button bg-warning text-black">Suspend</button>
                @endif
            </div>
        @endif
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

        <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
            <div class="mb-3 text-sm font-semibold">Servers</div>
            @forelse ($team->servers as $server)
                <div class="flex justify-between text-sm">
                    <span>{{ $server->name }}</span>
                    <span class="font-mono text-xs text-neutral-500">{{ $server->ip }}</span>
                </div>
            @empty
                <p class="text-sm text-neutral-500">No servers attached.</p>
            @endforelse
        </div>

        <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
            <div class="mb-3 text-sm font-semibold">Recent applications</div>
            @forelse ($this->recentApps as $app)
                <div class="flex justify-between text-sm">
                    <span>{{ $app->name }}</span>
                    <span class="text-xs text-neutral-500">{{ $app->created_at?->diffForHumans() }}</span>
                </div>
            @empty
                <p class="text-sm text-neutral-500">No applications yet.</p>
            @endforelse
        </div>

        @if (auth('nolbase')->user()?->canSuspendTenants() && $team->subscription?->paystack_customer_code)
            <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4 sm:col-span-2">
                <div class="mb-3 text-sm font-semibold">Refund</div>
                @if ($errors->has('refund'))
                    <p class="mb-2 text-sm text-error">{{ $errors->first('refund') }}</p>
                @endif
                <form wire:submit="refund" class="flex gap-2">
                    <input type="text" wire:model="refundReference" placeholder="Paystack transaction reference"
                           class="flex-1 rounded border border-coolgray-200 bg-coolgray-200 px-3 py-2 text-sm font-mono" />
                    <button type="submit" wire:confirm="Refund this Paystack transaction?" class="button bg-error text-white">Refund</button>
                </form>
                <p class="mt-2 text-xs text-neutral-500">Issues a full refund at Paystack and audit-logs the action. Find the reference on the customer's transaction history.</p>
            </div>
        @endif
    </div>
</div>
