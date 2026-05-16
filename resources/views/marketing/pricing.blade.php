@extends('layouts.base')
@section('body')
    @livewireScripts
    <main class="min-h-screen bg-gray-50 dark:bg-base">
        <header class="border-b border-coolgray-200/40">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <a href="/" class="text-xl font-bold tracking-tight">{{ config('nolbase.brand_name') }}</a>
                <nav class="flex items-center gap-4 text-sm">
                    @auth
                        <a href="{{ nolbase_app_url('/dashboard') }}" class="hover:text-coollabs">Dashboard</a>
                    @else
                        <a href="{{ nolbase_app_url('/login') }}" class="hover:text-coollabs">Sign in</a>
                        <a href="{{ nolbase_app_url('/register') }}" class="rounded-md bg-coollabs px-3 py-1.5 text-white hover:bg-coollabs-200">Start free trial</a>
                    @endauth
                </nav>
            </div>
        </header>

        <section class="mx-auto max-w-6xl px-6 py-16">
            <div class="text-center">
                <h1 class="!text-5xl font-extrabold tracking-tight">Pricing</h1>
                <p class="mt-4 text-lg text-neutral-500 dark:text-neutral-400">
                    Self-hostable PaaS for Nigerian developers. Pay in Naira, scale your apps, resell hosting to your own clients.
                </p>
                <p class="mt-2 text-sm text-neutral-500">
                    Every new account gets a 14-day Pro trial — no card required.
                </p>
            </div>

            <div class="mt-12">
                <livewire:subscription.pricing-plans />
            </div>

            <section class="mt-20 grid gap-6 sm:grid-cols-3">
                <div class="rounded-lg border border-coolgray-200/40 bg-coolgray-100 p-6">
                    <h3 class="font-semibold">Bring your own server</h3>
                    <p class="mt-2 text-sm text-neutral-400">
                        Attach a Hetzner / DigitalOcean / Vultr server via SSH. Nolbase handles the orchestration.
                    </p>
                </div>
                <div class="rounded-lg border border-coolgray-200/40 bg-coolgray-100 p-6">
                    <h3 class="font-semibold">Resell to your clients</h3>
                    <p class="mt-2 text-sm text-neutral-400">
                        Create hosting offers, invite clients, get paid weekly via Paystack Transfers. Pro &amp; Business plans.
                    </p>
                </div>
                <div class="rounded-lg border border-coolgray-200/40 bg-coolgray-100 p-6">
                    <h3 class="font-semibold">Open source under your feet</h3>
                    <p class="mt-2 text-sm text-neutral-400">
                        AGPLv3. Source is public, export your data anytime.
                        <a href="{{ route('legal.source') }}" class="text-coollabs underline">See the source</a>.
                    </p>
                </div>
            </section>
        </section>

        <x-agpl-footer />
    </main>
    @parent
@endsection
