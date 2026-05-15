<div class="mx-auto max-w-7xl px-6 py-8">
    <x-slot:title>Nolbase Admin · Audit Log</x-slot>

    <div class="mb-6 flex items-center justify-between">
        <h1>Audit Log</h1>
        <a href="{{ route('nolbase.admin.dashboard') }}" class="text-sm hover:text-coollabs">&larr; Dashboard</a>
    </div>

    <div class="mb-4 flex gap-2">
        <select wire:model.live="actionFilter" class="rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm">
            <option value="">All actions</option>
            @foreach ($actions as $a)
                <option value="{{ $a }}">{{ $a }}</option>
            @endforeach
        </select>
        <select wire:model.live="adminFilter" class="rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm">
            <option value="">All admins</option>
            @foreach ($admins as $a)
                <option value="{{ $a->id }}">{{ $a->name }} ({{ $a->email }})</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-hidden rounded-md border border-coolgray-200 bg-coolgray-100">
        <table class="w-full text-sm">
            <thead class="border-b border-coolgray-200 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">When</th>
                    <th class="px-4 py-3 text-left">Admin</th>
                    <th class="px-4 py-3 text-left">Action</th>
                    <th class="px-4 py-3 text-left">Target</th>
                    <th class="px-4 py-3 text-left">Payload</th>
                    <th class="px-4 py-3 text-left">IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $e)
                    <tr class="border-t border-coolgray-200/40">
                        <td class="px-4 py-3 text-xs text-neutral-500">{{ $e->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td class="px-4 py-3">{{ $e->admin?->name ?? '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $e->action }}</td>
                        <td class="px-4 py-3 text-xs text-neutral-500">
                            @if ($e->target_type)
                                {{ class_basename($e->target_type) }}#{{ $e->target_id }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-neutral-500"><pre class="overflow-x-auto">@json($e->payload)</pre></td>
                        <td class="px-4 py-3 text-xs text-neutral-500">{{ $e->ip ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-neutral-500">No audit entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $entries->links() }}</div>
</div>
