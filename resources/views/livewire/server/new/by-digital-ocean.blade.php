<div class="space-y-6">
    @if ($referral_url)
        <div class="rounded-md border border-coollabs/40 bg-coollabs/10 p-4 text-sm">
            <p class="mb-2 font-semibold">New to DigitalOcean?</p>
            <p class="mb-3 text-neutral-400">
                Sign up using our partner link and get free credit to try DO — you'll be billed directly by DigitalOcean for any usage. Your card stays with DO; Nolbase only charges your Pro/Business plan.
            </p>
            <a href="{{ $referral_url }}" target="_blank" rel="noopener"
               class="inline-block rounded bg-coollabs px-3 py-1.5 text-xs font-semibold text-white hover:bg-coollabs-200">
                Open DigitalOcean signup &rarr;
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
            <p class="mb-2">You don't have a DigitalOcean API token added yet.</p>
            <a href="/security/cloud-provider-tokens" class="text-coollabs underline">Add a DigitalOcean token first &rarr;</a>
        </div>
    @else
        <div>
            <label class="mb-1 block text-sm font-semibold">Token</label>
            <select wire:change="selectToken($event.target.value)" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm">
                <option value="">Select a DigitalOcean token…</option>
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
                    <select wire:model="region_slug" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm">
                        @foreach ($regions as $r)
                            @if (data_get($r, 'available') !== false)
                                <option value="{{ data_get($r, 'slug') }}">{{ data_get($r, 'name') }} ({{ data_get($r, 'slug') }})</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold">Size</label>
                    <select wire:model="size_slug" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm">
                        @foreach ($sizes as $s)
                            @if (data_get($s, 'available') !== false)
                                <option value="{{ data_get($s, 'slug') }}">
                                    {{ data_get($s, 'slug') }} — {{ data_get($s, 'memory') }}MB, {{ data_get($s, 'vcpus') }}vCPU — ${{ number_format(data_get($s, 'price_monthly', 0), 2) }}/mo
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-semibold">Image</label>
                    <select wire:model="image_slug" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm">
                        @foreach ($images as $img)
                            <option value="{{ data_get($img, 'slug') }}">
                                {{ data_get($img, 'distribution') }} — {{ data_get($img, 'name') }}
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
                    <span wire:loading.remove wire:target="provision">Provision droplet</span>
                    <span wire:loading wire:target="provision">Provisioning…</span>
                </button>
            </div>
        @endif
    @endif
</div>
