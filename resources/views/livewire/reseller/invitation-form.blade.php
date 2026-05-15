<div>
    <x-slot:title>Invite a client | Nolbase</x-slot>
    <a href="{{ route('reseller.index') }}" class="text-sm text-neutral-500 hover:text-coollabs">&larr; Reseller</a>
    <h1 class="mt-2">Invite a client</h1>
    <p class="mb-6 text-sm text-neutral-500">
        Pick an offer and enter the client's email. They'll get an invitation link (manual copy for now; auto-email in Phase 6.2).
    </p>

    <form wire:submit="send" class="space-y-4">
        <div>
            <label class="mb-1 block text-sm font-semibold">Offer</label>
            <select wire:model="hosting_offer_id" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm">
                <option value="">Select an offer…</option>
                @foreach ($this->availableOffers as $o)
                    <option value="{{ $o->id }}">{{ $o->name }}</option>
                @endforeach
            </select>
            @error('hosting_offer_id') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-semibold">Client email</label>
            <input wire:model="email" type="email" placeholder="client@example.com" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm" />
            @error('email') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-semibold">Project name</label>
            <input wire:model="project_name" type="text" placeholder="e.g. Bola's Shop" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm" />
            @error('project_name') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('reseller.index') }}" class="button">Cancel</a>
            <button type="submit" class="button bg-coollabs text-white">Send invitation</button>
        </div>
    </form>
</div>
