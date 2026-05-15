<div>
    <x-slot:title>Payout account | Nolbase</x-slot>
    <a href="{{ route('reseller.payouts.index') }}" class="text-sm text-neutral-500 hover:text-coollabs">&larr; Payouts</a>
    <h1 class="mt-2">Payout account</h1>
    <p class="mb-6 text-sm text-neutral-500">
        Where Nolbase sends your client earnings (minus the {{ config('paystack.marketplace_fee_pct', 10) }}% platform fee and Paystack processing). Nigerian NUBAN accounts only.
    </p>

    @error('banks')
        <div class="mb-4 rounded border border-error/30 bg-error/10 p-3 text-sm text-error">{{ $message }}</div>
    @enderror
    @error('save')
        <div class="mb-4 rounded border border-error/30 bg-error/10 p-3 text-sm text-error">{{ $message }}</div>
    @enderror

    <form wire:submit="save" class="space-y-4">
        <div>
            <label class="mb-1 block text-sm font-semibold">Bank</label>
            <select wire:model="bank_code" class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm">
                <option value="">Select bank…</option>
                @foreach ($banks as $b)
                    <option value="{{ data_get($b, 'code') }}">{{ data_get($b, 'name') }}</option>
                @endforeach
            </select>
            @error('bank_code') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-semibold">Account number</label>
            <div class="flex gap-2">
                <input type="text" wire:model="account_number" maxlength="10" placeholder="10 digits"
                       class="flex-1 rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm font-mono" />
                <button type="button" wire:click="resolve" wire:loading.attr="disabled" class="button">
                    <span wire:loading.remove wire:target="resolve">Verify</span>
                    <span wire:loading wire:target="resolve">Verifying…</span>
                </button>
            </div>
            @error('account_number') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
        </div>

        @if ($resolved_account_name)
            <div class="rounded-md border border-success/40 bg-success/10 p-3 text-sm">
                <span class="text-success">✓</span> Account holder: <span class="font-semibold">{{ $resolved_account_name }}</span>
            </div>
        @endif

        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('reseller.payouts.index') }}" class="button">Cancel</a>
            <button type="submit" wire:loading.attr="disabled" class="button bg-coollabs text-white"
                    @if (! $resolved_account_name) disabled @endif>
                <span wire:loading.remove wire:target="save">Save payout account</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </form>
</div>
