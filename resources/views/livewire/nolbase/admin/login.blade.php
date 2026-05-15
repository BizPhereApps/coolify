<div>
    <x-slot:title>Nolbase Admin · Login</x-slot>
    <section class="flex min-h-screen items-center justify-center bg-gray-50 px-6 py-8 dark:bg-base">
        <div class="w-full max-w-sm space-y-6 rounded-lg border border-coolgray-200 bg-white p-8 shadow-sm dark:bg-coolgray-100">
            <div class="space-y-1 text-center">
                <h1 class="text-2xl font-bold">Nolbase Admin</h1>
                <p class="text-xs text-neutral-500">Staff-only panel.</p>
            </div>

            @if ($errors->any())
                <div class="rounded border border-error/30 bg-error/10 p-3 text-sm text-error">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form wire:submit="submit" class="space-y-4">
                <x-forms.input wire:model="email" type="email" name="email" label="Email" required />
                <x-forms.input wire:model="password" type="password" name="password" label="Password" required />
                <x-forms.button type="submit" isHighlighted class="w-full justify-center py-2.5">
                    Sign in
                </x-forms.button>
            </form>
        </div>
    </section>
</div>
