<div>
    <x-slot:title>New application | Nolbase</x-slot>
    <section class="min-h-screen bg-gray-50 px-6 py-12 dark:bg-base">
        <div class="mx-auto max-w-3xl">
            <a href="{{ route('client.dashboard') }}" class="text-sm text-neutral-500 hover:text-coollabs">&larr; Your project</a>
            <h1 class="mt-2">New application</h1>
            <p class="mb-6 text-sm text-neutral-500">
                Connect a Git repository. Nolbase will clone it, build with {{ $build_pack }}, and run it on
                {{ $subTeam->offer->server->name }}.
            </p>

            @if ($this->isOverQuota())
                <div class="rounded-md border border-warning/40 bg-warning/10 p-4 text-sm">
                    <p class="font-semibold text-warning">Application limit reached</p>
                    <p class="mt-1 text-neutral-400">
                        Your hosting plan ({{ $subTeam->offer->name }}) allows up to {{ $appsLimit }}
                        application{{ $appsLimit === 1 ? '' : 's' }}. You currently have {{ $appsUsed }}.
                    </p>
                    <p class="mt-2 text-xs">Contact {{ $subTeam->parentTeam->name }} if you need a higher limit.</p>
                    <a href="{{ route('client.dashboard') }}" class="mt-3 inline-block text-coollabs underline">Back to dashboard</a>
                </div>
            @else
                <form wire:submit="submit" class="space-y-4">
                    @error('quota')
                        <div class="rounded border border-error/30 bg-error/10 p-3 text-sm text-error">{{ $message }}</div>
                    @enderror

                    <div>
                        <label class="mb-1 block text-sm font-semibold">Name</label>
                        <input wire:model="name" type="text" placeholder="bola-shop-api"
                               class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm" />
                        @error('name') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold">Git repository</label>
                        <input wire:model="git_repository" type="text"
                               placeholder="https://github.com/bola/shop.git"
                               class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm font-mono" />
                        @error('git_repository') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-neutral-500">
                            Public repos work out of the box. For private repos, contact your developer to set up GitHub access.
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-semibold">Branch</label>
                            <input wire:model="git_branch" type="text"
                                   class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm font-mono" />
                            @error('git_branch') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-semibold">Port</label>
                            <input wire:model="ports_exposes" type="text" placeholder="3000"
                                   class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm font-mono" />
                            @error('ports_exposes') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-semibold">Build pack</label>
                            <select wire:model="build_pack"
                                    class="w-full rounded border border-coolgray-200 bg-coolgray-100 px-3 py-2 text-sm">
                                <option value="nixpacks">Nixpacks (auto-detect)</option>
                                <option value="dockerfile">Dockerfile</option>
                                <option value="static">Static site</option>
                                <option value="dockerimage">Docker image</option>
                            </select>
                            @error('build_pack') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <div class="text-xs text-neutral-500">
                            Using {{ $appsUsed }} / {{ $appsLimit === 0 ? '∞' : $appsLimit }} app slots.
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('client.dashboard') }}" class="button">Cancel</a>
                            <button type="submit" class="button bg-coollabs text-white">Create application</button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </section>
</div>
