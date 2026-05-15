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

            <div class="mt-6 rounded-md border border-dashed border-coolgray-200 p-6 text-center text-sm text-neutral-500">
                <p>Your hosting is set up. Your developer is preparing your application.</p>
                <p class="mt-2 text-xs">Deploy controls + logs land in Phase 6.2.2.</p>
            </div>

            <p class="mt-6 text-center text-xs text-neutral-500">
                Need help? Contact {{ $subTeam->parentTeam->name }} or <a href="mailto:{{ config('nolbase.support_email') }}" class="underline">{{ config('nolbase.support_email') }}</a>.
            </p>
        </div>
    </section>
</div>
