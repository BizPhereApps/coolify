<div>
    <x-slot:title>Your project | Nolbase</x-slot>
    <section class="min-h-screen bg-gray-50 px-6 py-12 dark:bg-base">
        <div class="mx-auto max-w-3xl">
            <div class="flex items-baseline justify-between">
                <h1>{{ $subTeam->project?->name ?? 'Your project' }}</h1>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button class="text-sm text-neutral-500 hover:text-coollabs">Sign out</button>
                </form>
            </div>
            <p class="mt-1 text-sm text-neutral-500">
                Hosted by {{ $subTeam->parentTeam->name }} · {{ $subTeam->offer->name }}
            </p>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
                    <div class="text-xs uppercase text-neutral-500">Subscription</div>
                    @if ($subTeam->subscription)
                        <div class="mt-1 text-sm">
                            <span class="font-semibold">{{ ucfirst($subTeam->subscription->status) }}</span>
                            @if ($subTeam->subscription->current_period_end)
                                · renews {{ $subTeam->subscription->current_period_end->format('Y-m-d') }}
                            @endif
                        </div>
                    @else
                        <div class="mt-1 text-sm text-neutral-500">No active subscription</div>
                    @endif
                </div>
                <div class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4">
                    <div class="text-xs uppercase text-neutral-500">Allocation</div>
                    <ul class="mt-1 space-y-1 text-sm">
                        <li>{{ $subTeam->offer->ram_mb }} MB RAM</li>
                        <li>{{ $subTeam->offer->disk_gb }} GB disk</li>
                        <li>{{ $subTeam->offer->max_apps }} application{{ $subTeam->offer->max_apps === 1 ? '' : 's' }}</li>
                    </ul>
                </div>
            </div>

            <section class="mt-8">
                <h2 class="mb-3">Applications</h2>
                @if ($this->applications->isEmpty())
                    <div class="rounded-md border border-dashed border-coolgray-200 p-6 text-center text-sm text-neutral-500">
                        <p>No applications yet — your developer ({{ $subTeam->parentTeam->name }}) will set up your code shortly.</p>
                    </div>
                @else
                    <div class="grid gap-3">
                        @foreach ($this->applications as $app)
                            <a href="{{ route('client.application.show', $app->uuid) }}"
                               class="rounded-md border border-coolgray-200 bg-coolgray-100 p-4 hover:bg-coolgray-200 transition">
                                <div class="flex items-baseline justify-between">
                                    <div>
                                        <div class="font-semibold">{{ $app->name }}</div>
                                        @if ($app->fqdn)
                                            <div class="text-xs text-coollabs">{{ $app->fqdn }}</div>
                                        @endif
                                    </div>
                                    <div class="text-xs">
                                        @php
                                            $status = strtolower($app->status ?? 'unknown');
                                            $color = match (true) {
                                                str_contains($status, 'running') => 'text-success',
                                                str_contains($status, 'exited'), str_contains($status, 'failed') => 'text-error',
                                                str_contains($status, 'restarting'), str_contains($status, 'starting') => 'text-warning',
                                                default => 'text-neutral-500',
                                            };
                                        @endphp
                                        <span class="rounded-full bg-coolgray-200 px-2 py-0.5 {{ $color }}">{{ $app->status ?? 'unknown' }}</span>
                                    </div>
                                </div>
                                @if ($app->git_repository)
                                    <div class="mt-2 text-xs text-neutral-500 font-mono truncate">{{ $app->git_repository }}</div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            <p class="mt-6 text-center text-xs text-neutral-500">
                Need help? Contact {{ $subTeam->parentTeam->name }} or <a href="mailto:{{ config('nolbase.support_email') }}" class="underline">{{ config('nolbase.support_email') }}</a>.
            </p>
        </div>
    </section>
</div>
