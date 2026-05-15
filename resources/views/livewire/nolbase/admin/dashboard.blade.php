<div class="mx-auto max-w-7xl px-6 py-8">
    <x-slot:title>Nolbase Admin · Dashboard</x-slot>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1>Nolbase Admin</h1>
            <p class="text-sm text-neutral-500">Signed in as {{ auth('nolbase')->user()->name }} ({{ auth('nolbase')->user()->role }})</p>
        </div>
        <nav class="flex gap-3 text-sm">
            <a href="{{ route('nolbase.admin.tenants.index') }}" class="hover:text-coollabs">Tenants</a>
            <a href="{{ route('nolbase.admin.audit') }}" class="hover:text-coollabs">Audit log</a>
            @if (auth('nolbase')->user()->isStaff() || auth('nolbase')->user()->isSuperadmin())
                <a href="{{ route('nolbase.admin.settings') }}" class="hover:text-coollabs">Settings</a>
            @endif
            <form method="POST" action="{{ route('nolbase.admin.logout') }}" class="inline">@csrf<button class="hover:text-coollabs">Sign out</button></form>
        </nav>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
            <div class="text-xs uppercase text-neutral-500">MRR</div>
            <div class="mt-1 text-2xl font-bold">₦{{ number_format($mrr) }}</div>
            <div class="mt-1 text-xs text-neutral-500">ARR: ₦{{ number_format($arr) }}</div>
        </div>
        <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
            <div class="text-xs uppercase text-neutral-500">Active subscriptions</div>
            <div class="mt-1 text-2xl font-bold">{{ number_format($activeSubscriptions) }}</div>
        </div>
        <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
            <div class="text-xs uppercase text-neutral-500">Trialing</div>
            <div class="mt-1 text-2xl font-bold">{{ number_format($trialingSubscriptions) }}</div>
        </div>
        <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
            <div class="text-xs uppercase text-neutral-500">Past due</div>
            <div class="mt-1 text-2xl font-bold {{ $pastDueSubscriptions > 0 ? 'text-warning' : '' }}">{{ number_format($pastDueSubscriptions) }}</div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
            <div class="mb-3 text-sm font-semibold">Teams</div>
            <div class="flex items-baseline justify-between"><span class="text-neutral-500">Total</span><span class="font-semibold">{{ number_format($totalTeams) }}</span></div>
            <div class="flex items-baseline justify-between"><span class="text-neutral-500">New in last 30 days</span><span class="font-semibold">{{ number_format($newTeams30d) }}</span></div>
        </div>
        <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
            <div class="mb-3 text-sm font-semibold">Active plan distribution</div>
            @forelse ($planDistribution as $code => $count)
                <div class="flex items-baseline justify-between">
                    <span class="text-neutral-500 uppercase">{{ $code }}</span>
                    <span class="font-semibold">{{ number_format($count) }}</span>
                </div>
            @empty
                <p class="text-sm text-neutral-500">No active subscriptions yet.</p>
            @endforelse
        </div>
    </div>
</div>
