<div>
    <x-slot:title>Reseller | Nolbase</x-slot>
    <div class="flex items-baseline justify-between">
        <div>
            <h1>Reseller</h1>
            <div class="subtitle">Sell hosting slices of your servers to your own clients. Nolbase handles billing; you set the price.</div>
        </div>
        <a href="{{ route('reseller.offers.create') }}" class="button bg-coollabs text-white">New offer</a>
    </div>

    <section class="mt-6">
        <h2 class="mb-3">Hosting offers</h2>
        @if ($offers->isEmpty())
            <div class="rounded-md border border-dashed border-coolgray-200 p-8 text-center text-sm text-neutral-500">
                <p class="mb-3">You haven't created any hosting offers yet.</p>
                <a href="{{ route('reseller.offers.create') }}" class="text-coollabs underline">Create your first offer &rarr;</a>
            </div>
        @else
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($offers as $offer)
                    <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
                        <div class="mb-1 flex items-baseline justify-between">
                            <div class="font-semibold">{{ $offer->name }}</div>
                            <div class="text-sm">₦{{ number_format($offer->price_ngn_monthly) }}/mo</div>
                        </div>
                        <div class="text-xs text-neutral-500">
                            {{ $offer->server->name }} · {{ $offer->ram_mb }}MB RAM · {{ $offer->disk_gb }}GB disk · {{ $offer->max_apps }} app{{ $offer->max_apps === 1 ? '' : 's' }}
                        </div>
                        <div class="mt-2 text-xs">
                            <span class="rounded-full bg-coolgray-200 px-2 py-0.5 {{ $offer->is_active ? 'text-success' : 'text-neutral-500' }}">
                                {{ $offer->is_active ? 'Active' : 'Inactive' }}
                            </span>
                            <span class="ml-1 text-neutral-500">{{ $offer->sub_teams_count }} client(s)</span>
                        </div>
                        <div class="mt-3 flex gap-2 text-xs">
                            <a href="{{ route('reseller.offers.edit', $offer) }}" class="text-coollabs hover:underline">Edit</a>
                            <a href="{{ route('reseller.invitations.create', ['offer_id' => $offer->id]) }}" class="text-coollabs hover:underline">Invite client</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="mt-8">
        <div class="mb-3 flex items-baseline justify-between">
            <h2>Pending invitations</h2>
            <a href="{{ route('reseller.invitations.create') }}" class="text-sm text-coollabs hover:underline">Send invitation &rarr;</a>
        </div>
        @if ($pendingInvitations->isEmpty())
            <p class="text-sm text-neutral-500">No pending invitations.</p>
        @else
            <div class="overflow-hidden rounded-md border border-coolgray-200 bg-coolgray-100">
                <table class="w-full text-sm">
                    <thead class="border-b border-coolgray-200 text-xs uppercase">
                        <tr>
                            <th class="px-4 py-2 text-left">Client email</th>
                            <th class="px-4 py-2 text-left">Project</th>
                            <th class="px-4 py-2 text-left">Offer</th>
                            <th class="px-4 py-2 text-left">Expires</th>
                            <th class="px-4 py-2 text-left">Link</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendingInvitations as $inv)
                            <tr class="border-t border-coolgray-200/40">
                                <td class="px-4 py-2">{{ $inv->email }}</td>
                                <td class="px-4 py-2">{{ $inv->project_name }}</td>
                                <td class="px-4 py-2 text-xs text-neutral-500">{{ $inv->offer->name }}</td>
                                <td class="px-4 py-2 text-xs text-neutral-500">{{ $inv->expires_at->diffForHumans() }}</td>
                                <td class="px-4 py-2 font-mono text-xs">{{ url('/invitations/'.$inv->invitation_token) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-neutral-500">
                In Phase 6.2 these links will be emailed automatically. For now, copy and send manually.
            </p>
        @endif
    </section>
</div>
