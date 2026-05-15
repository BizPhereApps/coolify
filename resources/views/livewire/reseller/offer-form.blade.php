<div>
    <x-slot:title>{{ $offer ? 'Edit' : 'New' }} hosting offer | Nolbase</x-slot>
    <a href="{{ route('reseller.index') }}" class="text-sm text-neutral-500 hover:text-coollabs">&larr; Reseller</a>
    <h1 class="mt-2">{{ $offer ? 'Edit offer' : 'New hosting offer' }}</h1>

    <form wire:submit="save" class="mt-6 space-y-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-semibold">Name</label>
                <input wire:model="name" type="text" placeholder="e.g. Starter Hosting" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm" />
                @error('name') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold">Server</label>
                <select wire:model="server_id" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm">
                    <option value="">Select…</option>
                    @foreach ($this->availableServers as $s)
                        <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->ip }})</option>
                    @endforeach
                </select>
                @error('server_id') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-semibold">Description (shown to the Client)</label>
                <textarea wire:model="description" rows="2" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm"></textarea>
            </div>
        </div>

        <fieldset>
            <legend class="mb-2 text-sm font-semibold">Allocation</legend>
            <div class="grid gap-4 sm:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs">RAM (MB)</label>
                    <input wire:model="ram_mb" type="number" min="64" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs">Disk (GB)</label>
                    <input wire:model="disk_gb" type="number" min="1" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs">Max apps</label>
                    <input wire:model="max_apps" type="number" min="1" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs">Max databases</label>
                    <input wire:model="max_databases" type="number" min="0" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm" />
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <input wire:model="allow_custom_domain" type="checkbox" id="custom_domain" />
                <label for="custom_domain" class="text-sm">Allow Client to use a custom domain</label>
            </div>
        </fieldset>

        <fieldset>
            <legend class="mb-2 text-sm font-semibold">Pricing (NGN)</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs">Monthly price (₦)</label>
                    <input wire:model="price_ngn_monthly" type="number" min="0" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs">Annual price (₦) — optional</label>
                    <input wire:model="price_ngn_annual" type="number" min="0" placeholder="Leave blank for monthly-only" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm" />
                </div>
            </div>
            <p class="mt-2 text-xs text-neutral-500">
                Nolbase keeps a {{ config('paystack.marketplace_fee_pct', 10) }}% platform fee. Paystack's processing fee (~1.5% capped at ₦2,000) is passed through transparently. Your payout per ₦{{ number_format($price_ngn_monthly) }} sale is roughly
                ₦{{ number_format(max(0, (int) round($price_ngn_monthly * (1 - (config('paystack.marketplace_fee_pct', 10) / 100)) - min(2000, $price_ngn_monthly * 0.015)))) }}/mo.
            </p>
        </fieldset>

        <div class="flex items-center gap-2">
            <input wire:model="is_active" type="checkbox" id="active" />
            <label for="active" class="text-sm">Active (available for new invitations)</label>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('reseller.index') }}" class="button">Cancel</a>
            <button type="submit" class="button bg-coollabs text-white">{{ $offer ? 'Save changes' : 'Create offer' }}</button>
        </div>
    </form>
</div>
