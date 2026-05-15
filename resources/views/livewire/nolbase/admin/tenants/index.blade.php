<div class="mx-auto max-w-7xl px-6 py-8">
    <x-slot:title>Nolbase Admin · Tenants</x-slot>

    <div class="mb-6 flex items-center justify-between">
        <h1>Tenants</h1>
        <a href="{{ route('nolbase.admin.dashboard') }}" class="text-sm hover:text-coollabs">&larr; Dashboard</a>
    </div>

    <div class="mb-4 flex gap-2">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by team, email, Paystack code"
               class="flex-1 rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm" />
        <select wire:model.live="statusFilter" class="rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="suspended">Suspended</option>
            <option value="closed">Closed</option>
        </select>
    </div>

    <div class="overflow-hidden rounded-md border border-coolgray-200 bg-coolgray-100">
        <table class="w-full text-sm">
            <thead class="border-b border-coolgray-200 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Team</th>
                    <th class="px-4 py-3 text-left">Plan</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Nolbase status</th>
                    <th class="px-4 py-3 text-right">Members</th>
                    <th class="px-4 py-3 text-right">Created</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tenants as $tenant)
                    <tr class="border-t border-coolgray-200/40 hover:bg-coolgray-200/40">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $tenant->name }}</div>
                            <div class="text-xs text-neutral-500">#{{ $tenant->id }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $tenant->subscription?->plan?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-coolgray-200 px-2 py-0.5 text-xs">
                                {{ $tenant->subscription?->status ?? 'none' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($tenant->nolbase_status === 'suspended')
                                <span class="rounded-full bg-warning/20 px-2 py-0.5 text-xs text-warning">suspended</span>
                            @elseif ($tenant->nolbase_status === 'closed')
                                <span class="rounded-full bg-error/20 px-2 py-0.5 text-xs text-error">closed</span>
                            @else
                                <span class="rounded-full bg-success/20 px-2 py-0.5 text-xs text-success">active</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">{{ $tenant->members_count }}</td>
                        <td class="px-4 py-3 text-right text-xs text-neutral-500">{{ $tenant->created_at?->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('nolbase.admin.tenants.show', $tenant) }}" class="text-coollabs hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-neutral-500">No tenants found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $tenants->links() }}</div>
</div>
