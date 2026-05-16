<div class="space-y-4">
    @if (! $available)
        <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4 text-sm text-neutral-500">
            <p class="font-semibold">Nolbase-managed hosting is not yet enabled.</p>
            <p class="mt-1">Contact {{ config('nolbase.support_email') }} or wait for general availability. In the meantime, you can attach your own server via Hetzner / DigitalOcean / Vultr above.</p>
        </div>
    @else
        @if ($limit_reached)
            <div class="rounded-md border border-warning/40 bg-warning/10 p-3 text-sm text-warning">
                You've reached your plan's server limit. <a href="{{ route('subscription.pricing') }}" class="underline">Upgrade for more</a>.
            </div>
        @endif

        <div class="rounded-md border border-coollabs/40 bg-coollabs/10 p-4 text-sm">
            <p class="font-semibold">Estimated price</p>
            <p class="mt-1 text-2xl font-bold">₦{{ number_format($estimated_price_ngn) }}<span class="text-sm font-normal text-neutral-500"> /month</span></p>
            <p class="mt-1 text-xs text-neutral-500">
                Includes the underlying Hetzner infrastructure cost plus Nolbase's operational markup. Billed via Paystack alongside your subscription.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-semibold">Plan</label>
                <select wire:model.live="plan_slug" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm">
                    <option value="cax11">CAX11 — ARM 2vCPU, 4GB RAM (Starter)</option>
                    <option value="cax21">CAX21 — ARM 4vCPU, 8GB RAM</option>
                    <option value="cax31">CAX31 — ARM 8vCPU, 16GB RAM</option>
                    <option value="cax41">CAX41 — ARM 16vCPU, 32GB RAM</option>
                    <option value="ccx13">CCX13 — x86 2vCPU, 8GB RAM (dedicated)</option>
                    <option value="ccx23">CCX23 — x86 4vCPU, 16GB RAM (dedicated)</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold">Region</label>
                <select wire:model="location_slug" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm">
                    <option value="fsn1">Falkenstein, Germany (fsn1) — best for Africa</option>
                    <option value="nbg1">Nuremberg, Germany (nbg1)</option>
                    <option value="hel1">Helsinki, Finland (hel1)</option>
                    <option value="ash">Ashburn, USA (ash)</option>
                    <option value="hil">Hillsboro, USA (hil)</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-semibold">Server name (optional)</label>
                <input wire:model="name" type="text" placeholder="leave blank for auto-generated"
                       class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm" />
                @error('name') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
            </div>
        </div>

        @error('provision')
            <p class="rounded border border-error/30 bg-error/10 p-3 text-sm text-error">{{ $message }}</p>
        @enderror

        <div class="flex justify-end">
            <button wire:click="provision" wire:loading.attr="disabled" wire:confirm="Provision a Nolbase-managed server at ₦{{ number_format($estimated_price_ngn) }}/month?"
                    class="button bg-coollabs text-white"
                    @if ($limit_reached) disabled @endif>
                <span wire:loading.remove wire:target="provision">Provision server</span>
                <span wire:loading wire:target="provision">Provisioning… (60s)</span>
            </button>
        </div>
    @endif
</div>
