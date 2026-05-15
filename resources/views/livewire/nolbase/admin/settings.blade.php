<div class="mx-auto max-w-3xl px-6 py-8">
    <x-slot:title>Nolbase Admin · Settings</x-slot>

    <div class="mb-6 flex items-center justify-between">
        <h1>Settings</h1>
        <a href="{{ route('nolbase.admin.dashboard') }}" class="text-sm hover:text-coollabs">&larr; Dashboard</a>
    </div>

    @if ($errors->has('save'))
        <div class="mb-4 rounded border border-error/30 bg-error/10 p-3 text-sm text-error">
            {{ $errors->first('save') }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        @foreach ($definitions as $key => $meta)
            <div>
                <label for="{{ $key }}" class="block text-sm font-semibold">{{ $meta['label'] }}</label>
                <p class="mb-2 text-xs text-neutral-500">{{ $meta['description'] }}</p>
                <input id="{{ $key }}" type="text" wire:model="values.{{ $key }}"
                       placeholder="{{ $meta['placeholder'] ?? '' }}"
                       class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm font-mono" />
            </div>
        @endforeach

        <div class="flex justify-end">
            <x-forms.button type="submit" isHighlighted>Save settings</x-forms.button>
        </div>
    </form>
</div>
