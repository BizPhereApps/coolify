@extends('layouts.base')
@section('body')
    <main class="min-h-screen bg-gray-50 dark:bg-base px-6 py-12">
        <div class="mx-auto max-w-3xl">
            <a href="/" class="text-sm text-neutral-500 hover:text-coollabs">&larr; Back</a>

            <h1 class="mt-6 mb-2">Privacy Policy</h1>
            <p class="text-sm text-neutral-500">Effective: TBD &middot; Last updated: TBD</p>

            <div class="mt-8 rounded-md border border-warning/40 bg-warning/10 p-4 text-sm text-warning">
                <strong>DRAFT — not legally binding.</strong>
                This page exists so the footer doesn't 404. Before accepting paying
                customers, replace this content with a real Privacy Policy reviewed
                by a Nigerian lawyer familiar with NDPR (Nigeria Data Protection
                Regulation) and CBN payment-aggregator guidelines.
            </div>

            <section class="prose dark:prose-invert mt-8 max-w-none">
                <h2>What we collect</h2>
                <p>To be drafted with counsel. At minimum: email, name, team identifiers, server IPs you attach to {{ config('nolbase.brand_name') }}, payment metadata returned by Paystack (we never store full card numbers), webhook event payloads from Paystack, and operational logs.</p>

                <h2>Why we collect it</h2>
                <p>To be drafted with counsel. Lawful basis under NDPR will typically be contract performance, legitimate interest in fraud prevention, and consent for marketing.</p>

                <h2>Who we share it with</h2>
                <p>To be drafted with counsel. Expected processors: Paystack (payments), ZeptoMail (transactional email), Hetzner (when you choose Nolbase-managed hosting), our hosting provider, and any third-party services you explicitly integrate into your deployments.</p>

                <h2>How long we retain it</h2>
                <p>To be drafted with counsel.</p>

                <h2>Your rights</h2>
                <p>To be drafted with counsel. NDPR grants rights to access, rectification, erasure, restriction of processing, data portability, and objection.</p>

                <h2>Contact</h2>
                <p>Email {{ config('nolbase.support_email') }} with any privacy questions, NDPR data-subject requests, or breach notifications.</p>
            </section>
        </div>

        <x-agpl-footer />
    </main>
@endsection
