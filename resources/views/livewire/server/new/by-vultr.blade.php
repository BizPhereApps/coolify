<div class="space-y-6">
    @if ($referral_url)
        <div class="rounded-md border border-coollabs/40 bg-coollabs/10 p-4 text-sm">
            <p class="mb-2 font-semibold">New to Vultr?</p>
            <p class="mb-3 text-neutral-400">
                Sign up using our referral and get free credit to try Vultr — you'll be billed directly by Vultr for usage. Nolbase only charges your Pro/Business plan.
            </p>
            <a href="{{ $referral_url }}" target="_blank" rel="noopener"
               class="inline-block rounded bg-coollabs px-3 py-1.5 text-xs font-semibold text-white hover:bg-coollabs-200">
                Open Vultr signup &rarr;
            </a>
        </div>
    @endif

    @if ($limit_reached)
        <div class="rounded-md border border-warning/40 bg-warning/10 p-3 text-sm text-warning">
            You've reached your plan's server limit. <a href="{{ route('subscription.pricing') }}" class="underline">Upgrade for more</a>.
        </div>
    @endif

    @if ($available_tokens->isEmpty())
        <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4 text-sm">
            <p class="mb-2">You don't have a Vultr API token added yet.</p>
            <a href="/security/cloud-provider-tokens" class="text-coollabs underline">Add a Vultr token first &rarr;</a>
        </div>
    @else
        <div>
            <label class="mb-1 block text-sm font-semibold">Token</label>
            <select wire:change="selectToken($event.target.value)" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm">
                <option value="">Select a Vultr token…</option>
                @foreach ($available_tokens as $t)
                    <option value="{{ $t->id }}" @if ($selected_token_id === $t->id) selected @endif>{{ $t->name }}</option>
                @endforeach
            </select>
            @error('selected_token_id') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
        </div>

        @if ($selected_token_id)
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-semibold">Name</label>
                    <input type="text" wire:model="name" placeholder="e.g. nolbase-prod-01"
                           class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm" />
                    @error('name') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold">SSH key</label>
                    <select wire:model="private_key_id" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm">
                        @foreach ($private_keys as $pk)
                            <option value="{{ $pk->id }}">{{ $pk->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold">Region</label>
                    <select wire:model="region_id" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm">
                        @foreach ($regions as $r)
                            <option value="{{ data_get($r, 'id') }}">{{ data_get($r, 'city') }}, {{ data_get($r, 'country') }} ({{ data_get($r, 'id') }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold">Plan</label>
                    <select wire:model="plan_id" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm">
                        @foreach ($plans as $p)
                            <option value="{{ data_get($p, 'id') }}">
                                {{ data_get($p, 'id') }} — {{ data_get($p, 'ram') }}MB, {{ data_get($p, 'vcpu_count') }}vCPU — ${{ number_format(data_get($p, 'monthly_cost', 0), 2) }}/mo
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-semibold">Operating system</label>
                    <select wire:model="os_id" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm">
                        @foreach ($os as $o)
                            <option value="{{ data_get($o, 'id') }}">
                                {{ data_get($o, 'name') }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            @error('provision') <p class="text-sm text-error">{{ $message }}</p> @enderror

            <div class="flex justify-end">
                <button wire:click="provision" wire:loading.attr="disabled"
                        class="button bg-coollabs text-white"
                        @if ($limit_reached) disabled @endif>
                    <span wire:loading.remove wire:target="provision">Provision instance</span>
                    <span wire:loading wire:target="provision">Provisioning…</span>
                </button>
            </div>
        @endif
    @endif
</div>
