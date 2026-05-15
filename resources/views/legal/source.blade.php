@extends('layouts.base')
@section('body')
    <main class="min-h-screen bg-gray-50 dark:bg-base px-6 py-12">
        <div class="mx-auto max-w-3xl">
            <a href="/" class="text-sm text-neutral-500 hover:text-coollabs">&larr; Back</a>

            <h1 class="mt-6 mb-2">Source code</h1>
            <p class="text-neutral-600 dark:text-neutral-400 mb-8">
                {{ config('nolbase.brand_name') }} is built on
                <a href="https://github.com/coollabsio/coolify" class="text-coollabs underline" target="_blank" rel="noopener">Coolify</a>,
                which is licensed under the
                <a href="https://www.gnu.org/licenses/agpl-3.0.en.html" class="text-coollabs underline" target="_blank" rel="noopener">GNU Affero General Public License v3</a> (AGPLv3).
            </p>

            <div class="space-y-6">
                <section class="rounded-md border border-coolgray-200 bg-coolgray-100 p-6">
                    <h2 class="mb-2">Get the source</h2>
                    <p class="text-sm text-neutral-400 mb-4">
                        The AGPL grants every user of this hosted service the right to receive the complete corresponding source code of the running version. {{ config('nolbase.brand_name') }} publishes its full source code, including local modifications:
                    </p>
                    <a href="{{ config('nolbase.source_repo_url') }}"
                       class="inline-flex items-center gap-2 rounded-md bg-coollabs px-4 py-2 text-sm font-semibold text-white hover:bg-coollabs-200"
                       target="_blank" rel="noopener">
                        Open the {{ config('nolbase.brand_name') }} repository
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                </section>

                <section class="rounded-md border border-coolgray-200 bg-coolgray-100 p-6">
                    <h2 class="mb-2">Your rights under AGPLv3</h2>
                    <ul class="space-y-2 text-sm text-neutral-400 list-disc list-inside">
                        <li><strong class="text-neutral-200">Use</strong> the software for any purpose.</li>
                        <li><strong class="text-neutral-200">Study</strong> how the software works and adapt it to your needs.</li>
                        <li><strong class="text-neutral-200">Redistribute</strong> copies to help others.</li>
                        <li><strong class="text-neutral-200">Modify</strong> the software and release your modifications — under the same AGPLv3 terms.</li>
                        <li>If you run a modified version as a public service, you must offer your modified source to its users — exactly as we do here.</li>
                    </ul>
                </section>

                <section class="rounded-md border border-coolgray-200 bg-coolgray-100 p-6">
                    <h2 class="mb-2">Upstream contributions</h2>
                    <p class="text-sm text-neutral-400">
                        Where it makes sense, {{ config('nolbase.brand_name') }} contributes non-strategic bug fixes back to the upstream Coolify project. Strategic and Nolbase-specific features remain in the fork — also publicly available under AGPL.
                    </p>
                </section>
            </div>
        </div>

        <x-agpl-footer />
    </main>
@endsection
