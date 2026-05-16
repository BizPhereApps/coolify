@extends('layouts.base')
@section('body')
    <main class="min-h-screen bg-gray-50 dark:bg-base">
        <header class="border-b border-coolgray-200/40">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <a href="/" class="text-xl font-bold tracking-tight">{{ config('nolbase.brand_name') }}</a>
                <nav class="flex items-center gap-4 text-sm">
                    <a href="{{ route('marketing.pricing') }}" class="hover:text-coollabs">Pricing</a>
                    <a href="{{ nolbase_app_url('/login') }}" class="hover:text-coollabs">Sign in</a>
                    <a href="{{ nolbase_app_url('/register') }}" class="rounded-md bg-coollabs px-3 py-1.5 text-white hover:bg-coollabs-200">Start free trial</a>
                </nav>
            </div>
        </header>

        <section class="mx-auto max-w-5xl px-6 py-20 text-center">
            <h1 class="!text-5xl font-extrabold tracking-tight sm:!text-6xl">
                Self-hostable PaaS, built for Nigerian developers.
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg text-neutral-500 dark:text-neutral-400">
                Deploy your apps, databases, and services on servers you already own.
                Pay in Naira via Paystack. Resell hosting to your own clients.
                Open source under the hood.
            </p>
            <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a href="{{ nolbase_app_url('/register') }}"
                   class="rounded-md bg-coollabs px-6 py-3 text-base font-semibold text-white hover:bg-coollabs-200">
                    Start your 14-day Pro trial
                </a>
                <a href="{{ route('marketing.pricing') }}"
                   class="rounded-md border border-coolgray-200 px-6 py-3 text-base font-semibold hover:bg-coolgray-100">
                    See pricing
                </a>
            </div>
            <p class="mt-3 text-xs text-neutral-500">No card required to start the trial.</p>
        </section>

        <section class="mx-auto max-w-6xl px-6 pb-20">
            <h2 class="text-center !text-2xl font-bold">Three ways to host with {{ config('nolbase.brand_name') }}</h2>
            <div class="mt-10 grid gap-6 sm:grid-cols-3">
                <div class="rounded-lg border border-coolgray-200/40 bg-coolgray-100 p-6">
                    <h3 class="font-semibold">Bring your own server</h3>
                    <p class="mt-2 text-sm text-neutral-400">
                        Already have a Hetzner / DigitalOcean / Vultr box?
                        Attach it via SSH and start deploying in minutes.
                        Your server, your data, your control.
                    </p>
                </div>
                <div class="rounded-lg border border-coolgray-200/40 bg-coolgray-100 p-6">
                    <h3 class="font-semibold">Managed by {{ config('nolbase.brand_name') }}</h3>
                    <p class="mt-2 text-sm text-neutral-400">
                        Don't want to manage a server? Pick a plan and we provision
                        a dedicated Hetzner VPS for you. One Naira invoice covers
                        everything.
                    </p>
                </div>
                <div class="rounded-lg border border-coolgray-200/40 bg-coolgray-100 p-6">
                    <h3 class="font-semibold">Resell to your clients</h3>
                    <p class="mt-2 text-sm text-neutral-400">
                        Run a freelance shop or agency? Create hosting offers,
                        invite your clients, and we'll handle the recurring
                        payments and weekly payouts via Paystack Transfers.
                    </p>
                </div>
            </div>
        </section>

        <section class="border-t border-coolgray-200/40 bg-coolgray-100/40">
            <div class="mx-auto max-w-5xl px-6 py-16 text-center">
                <h2 class="!text-2xl font-bold">Open source under your feet</h2>
                <p class="mx-auto mt-4 max-w-2xl text-neutral-500 dark:text-neutral-400">
                    {{ config('nolbase.brand_name') }} is built on
                    <a href="https://github.com/coollabsio/coolify" class="text-coollabs underline" target="_blank" rel="noopener">Coolify</a>,
                    licensed under AGPLv3. Our complete source code, including local modifications, is public — so you always have an exit door.
                </p>
                <a href="{{ route('legal.source') }}" class="mt-6 inline-block text-coollabs underline">See the source &rarr;</a>
            </div>
        </section>

        <x-agpl-footer />
    </main>
@endsection
