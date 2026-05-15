<footer class="mt-12 border-t border-coolgray-200/40 py-4 text-xs text-neutral-500 dark:text-neutral-500">
    <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-2 px-4 sm:flex-row sm:px-6">
        <div>
            &copy; {{ now()->year }} {{ config('nolbase.brand_name') }}.
            Built on
            <a href="https://github.com/coollabsio/coolify" class="underline hover:text-coollabs" target="_blank" rel="noopener">Coolify</a>
            (AGPLv3).
        </div>
        <div class="flex items-center gap-4">
            <a href="{{ route('legal.source') }}" class="hover:text-coollabs">Source code</a>
            <a href="https://nolbase.com/legal/privacy" class="hover:text-coollabs">Privacy</a>
            <a href="https://nolbase.com/legal/terms" class="hover:text-coollabs">Terms</a>
        </div>
    </div>
</footer>
