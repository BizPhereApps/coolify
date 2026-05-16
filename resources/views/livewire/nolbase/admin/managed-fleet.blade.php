<div class="mx-auto max-w-7xl px-6 py-8">
    <x-slot:title>Nolbase Admin · Managed Fleet</x-slot>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1>Managed Fleet</h1>
            <p class="text-sm text-neutral-500">Nolbase-managed servers across all tenants</p>
        </div>
        <nav class="flex gap-3 text-sm">
            <a href="{{ route('nolbase.admin.dashboard') }}" class="hover:text-coollabs">Dashboard</a>
            <a href="{{ route('nolbase.admin.tenants.index') }}" class="hover:text-coollabs">Tenants</a>
            <a href="{{ route('nolbase.admin.audit') }}" class="hover:text-coollabs">Audit log</a>
        </nav>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
            <div class="text-xs uppercase text-neutral-500">Servers (active)</div>
            <div class="mt-1 text-2xl font-bold">{{ number_format($activeServers) }} / {{ number_format($totalServers) }}</div>
            <div class="mt-1 text-xs text-neutral-500">{{ $pastDueServers }} past due · {{ $suspendedServers }} suspended</div>
        </div>
        <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
            <div class="text-xs uppercase text-neutral-500">Monthly revenue</div>
            <div class="mt-1 text-2xl font-bold">₦{{ number_format($totalMonthlyRevenueNgn) }}</div>
            <div class="mt-1 text-xs text-neutral-500">From active servers only</div>
        </div>
        <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
            <div class="text-xs uppercase text-neutral-500">Monthly cost basis</div>
            <div class="mt-1 text-2xl font-bold">₦{{ number_format($totalMonthlyCostNgn) }}</div>
            <div class="mt-1 text-xs text-neutral-500">What we pay Hetzner</div>
        </div>
        <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
            <div class="text-xs uppercase text-neutral-500">Monthly margin</div>
            <div class="mt-1 text-2xl font-bold {{ $totalMonthlyMarginNgn > 0 ? 'text-success' : 'text-warning' }}">
                ₦{{ number_format($totalMonthlyMarginNgn) }}
            </div>
            <div class="mt-1 text-xs text-neutral-500">
                @if ($totalMonthlyRevenueNgn > 0)
                    {{ number_format($totalMonthlyMarginNgn / $totalMonthlyRevenueNgn * 100, 1) }}% gross
                @else
                    —
                @endif
            </div>
        </div>
    </div>

    <div class="mt-6 overflow-x-auto rounded-md border border-coolgray-200 bg-coolgray-100">
        <table class="w-full text-sm">
            <thead class="bg-coolgray-200 text-left text-xs uppercase text-neutral-500">
                <tr>
                    <th class="px-4 py-3">Server</th>
                    <th class="px-4 py-3">Team</th>
                    <th class="px-4 py-3">Plan</th>
                    <th class="px-4 py-3 text-right">Cost</th>
                    <th class="px-4 py-3 text-right">Price</th>
                    <th class="px-4 py-3 text-right">Margin</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">This-month invoice</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $m = $row['managed'];
                        $inv = $row['invoice'];
                    @endphp
                    <tr class="border-t border-coolgray-200">
                        <td class="px-4 py-3 font-mono text-xs">{{ optional($m->server)->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ optional(optional($m->server)->team)->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $m->plan_slug ?? '—' }}@if ($m->location_slug) <span class="text-xs text-neutral-500">/{{ $m->location_slug }}</span>@endif</td>
                        <td class="px-4 py-3 text-right">₦{{ number_format($m->cost_basis_ngn_monthly ?? 0) }}</td>
                        <td class="px-4 py-3 text-right">₦{{ number_format($m->price_ngn_monthly ?? 0) }}</td>
                        <td class="px-4 py-3 text-right {{ $row['margin_ngn'] > 0 ? 'text-success' : 'text-warning' }}">
                            ₦{{ number_format($row['margin_ngn']) }}
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $color = match ($m->billing_status) {
                                    'active' => 'text-success',
                                    'past_due' => 'text-warning',
                                    'suspended' => 'text-error',
                                    default => 'text-neutral-500',
                                };
                            @endphp
                            <span class="{{ $color }}">{{ str_replace('_', ' ', $m->billing_status) }}</span>
                            @if ($m->past_due_since)
                                <div class="text-xs text-neutral-500">since {{ $m->past_due_since->diffForHumans() }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($inv)
                                <span class="text-xs uppercase {{ $inv->status === 'success' ? 'text-success' : ($inv->status === 'failed' ? 'text-warning' : 'text-neutral-500') }}">
                                    {{ $inv->status }}
                                </span>
                                <div class="text-xs text-neutral-500">₦{{ number_format($inv->total_ngn) }}</div>
                            @else
                                <span class="text-xs text-neutral-500">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-neutral-500">No managed servers provisioned yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
