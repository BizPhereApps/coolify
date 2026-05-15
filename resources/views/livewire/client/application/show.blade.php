<div>
    <x-slot:title>{{ $application->name }} | Nolbase</x-slot>
    <section class="min-h-screen bg-gray-50 px-6 py-12 dark:bg-base">
        <div class="mx-auto max-w-3xl">
            <a href="{{ route('client.dashboard') }}" class="text-sm text-neutral-500 hover:text-coollabs">&larr; Your project</a>

            <div class="mt-4 flex items-baseline justify-between">
                <div>
                    <h1>{{ $application->name }}</h1>
                    @if ($application->fqdn)
                        <a href="{{ $application->fqdn }}" target="_blank" rel="noopener" class="text-sm text-coollabs hover:underline">{{ $application->fqdn }}</a>
                    @endif
                </div>
                <button wire:click="deploy" wire:loading.attr="disabled" class="button bg-coollabs text-white">
                    <span wire:loading.remove wire:target="deploy">Redeploy</span>
                    <span wire:loading wire:target="deploy">Queuing…</span>
                </button>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
                    <div class="text-xs uppercase text-neutral-500">Status</div>
                    <div class="mt-1 font-semibold">{{ $application->status ?? 'unknown' }}</div>
                </div>
                <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
                    <div class="text-xs uppercase text-neutral-500">Repository</div>
                    <div class="mt-1 truncate font-mono text-xs">{{ $application->git_repository ?? '—' }}</div>
                    @if ($application->git_branch)
                        <div class="text-xs text-neutral-500">branch: {{ $application->git_branch }}</div>
                    @endif
                </div>
            </div>

            <section class="mt-8">
                <h2 class="mb-3">Custom domain</h2>
                @if (! $this->customDomainAllowed)
                    <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4 text-sm text-neutral-500">
                        Custom domains aren't included in your hosting plan. Contact {{ $subTeam->parentTeam->name }} if you'd like to add one.
                    </div>
                @else
                    <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
                        <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-end">
                            <div class="flex-1">
                                <label class="mb-1 block text-xs uppercase text-neutral-500">Domain</label>
                                <input type="text" wire:model="customDomain" placeholder="bolasshop.com"
                                       class="w-full rounded border border-coolgray-200 bg-coolgray-200 px-3 py-2 text-sm font-mono" />
                            </div>
                            <button wire:click="saveCustomDomain" class="button bg-coollabs text-white">Save domain</button>
                        </div>
                        @error('customDomain') <p class="text-xs text-error">{{ $message }}</p> @enderror

                        @if ($this->serverIp)
                            <div class="mt-3 rounded border border-warning/30 bg-warning/10 p-3 text-sm">
                                <p class="mb-1 font-semibold text-warning">DNS setup</p>
                                <p class="text-xs">Before this domain works, point its DNS at your hosting server:</p>
                                <table class="mt-2 w-full text-xs">
                                    <tr class="border-b border-warning/20">
                                        <td class="py-1 pr-2 text-neutral-500">Record</td>
                                        <td class="py-1 font-mono">A</td>
                                    </tr>
                                    <tr class="border-b border-warning/20">
                                        <td class="py-1 pr-2 text-neutral-500">Host</td>
                                        <td class="py-1 font-mono">{{ $customDomain ?: '@' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 pr-2 text-neutral-500">Value</td>
                                        <td class="py-1 font-mono">{{ $this->serverIp }}</td>
                                    </tr>
                                </table>
                                <p class="mt-2 text-xs text-neutral-500">
                                    DNS propagation usually takes 5-30 minutes. Click <strong>Redeploy</strong> after pointing DNS — Traefik will issue a Let's Encrypt certificate automatically.
                                </p>
                            </div>
                        @endif
                    </div>
                @endif
            </section>

            <section class="mt-8">
                <h2 class="mb-3">Environment variables</h2>
                @php
                    $envs = $application->environment_variables->where('resourceable_type', \App\Models\Application::class);
                @endphp

                @if ($envs->isEmpty())
                    <p class="text-sm text-neutral-500">No environment variables yet.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($envs as $e)
                            <div class="flex items-center gap-2 rounded-md border border-coolgray-200 bg-coolgray-100 p-3">
                                <div class="w-1/3 font-mono text-sm text-neutral-300">{{ $e->key }}</div>
                                <input type="text" wire:model="envEdits.{{ $e->id }}"
                                       class="flex-1 rounded border border-coolgray-200 bg-coolgray-200 px-2 py-1 text-sm font-mono" />
                                <button wire:click="saveEnv({{ $e->id }})" class="button text-xs">Save</button>
                                <button wire:click="deleteEnv({{ $e->id }})" wire:confirm="Delete {{ $e->key }}?"
                                        class="button text-xs bg-error/20 text-error">Delete</button>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-4 rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
                    <div class="mb-2 text-sm font-semibold">Add new</div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input type="text" wire:model="newEnvKey" placeholder="UPPERCASE_KEY"
                               class="w-1/3 rounded border border-coolgray-200 bg-coolgray-200 px-2 py-1 text-sm font-mono" />
                        <input type="text" wire:model="newEnvValue" placeholder="value"
                               class="flex-1 rounded border border-coolgray-200 bg-coolgray-200 px-2 py-1 text-sm font-mono" />
                        <button wire:click="addEnv" class="button bg-coollabs text-white">Add</button>
                    </div>
                    @error('newEnvKey') <p class="mt-2 text-xs text-error">{{ $message }}</p> @enderror
                    @error('newEnvValue') <p class="mt-2 text-xs text-error">{{ $message }}</p> @enderror
                </div>
                <p class="mt-2 text-xs text-neutral-500">
                    Changes take effect on the next deployment. Click <strong>Redeploy</strong> above to apply.
                </p>
            </section>
        </div>
    </section>
</div>
