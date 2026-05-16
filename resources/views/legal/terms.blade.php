@extends('layouts.base')
@section('body')
    <main class="min-h-screen bg-gray-50 dark:bg-base px-6 py-12">
        <div class="mx-auto max-w-3xl">
            <a href="/" class="text-sm text-neutral-500 hover:text-coollabs">&larr; Back</a>

            <h1 class="mt-6 mb-2">Terms of Service</h1>
            <p class="text-sm text-neutral-500">Effective: TBD &middot; Last updated: TBD</p>

            <div class="mt-8 rounded-md border border-warning/40 bg-warning/10 p-4 text-sm text-warning">
                <strong>DRAFT — not legally binding.</strong>
                This page exists so the footer doesn't 404. Before taking real
                Naira from a paying customer, replace this content with a real
                Terms of Service reviewed by a Nigerian lawyer who has read CBN
                payment-aggregator guidance — especially around the
                Client&nbsp;&rarr;&nbsp;Developer marketplace flow where {{ config('nolbase.brand_name') }} mediates funds.
            </div>

            <section class="prose dark:prose-invert mt-8 max-w-none">
                <h2>What {{ config('nolbase.brand_name') }} does</h2>
                <p>To be drafted. Core promise: orchestrate the deployment of your applications onto servers — either ones you bring or ones we provision for you. Optionally, mediate marketplace transactions between you (as a Developer) and your own end-clients.</p>

                <h2>Marketplace role</h2>
                <p>To be drafted with counsel. When you opt into the reseller marketplace, {{ config('nolbase.brand_name') }} collects payment from your Client via Paystack, retains a platform fee, and pays the remainder to your nominated bank account via Paystack Transfers. The exact characterization (payment aggregator, technical facilitator, escrow agent, etc.) is the central legal question to resolve before launch.</p>

                <h2>Fees</h2>
                <p>Subscription fees are quoted on the pricing page in Naira and charged monthly or annually via Paystack. Marketplace platform fee: 10% of each Client payment, deducted before payout (configurable per agreement).</p>

                <h2>Refunds &amp; cancellations</h2>
                <p>To be drafted with counsel. Default policy: pro-rata refund of any unused portion when you cancel a Nolbase-managed server mid-cycle. Subscription cancellations take effect at the end of the current paid period.</p>

                <h2>Acceptable use</h2>
                <p>To be drafted. No illegal content, no abuse of compute for unauthorised crypto-mining, no spam-generating workloads, no use that violates Hetzner's or any other infrastructure provider's terms.</p>

                <h2>Suspension &amp; termination</h2>
                <p>To be drafted. Grounds: non-payment (after a grace window), acceptable-use violations, or material breach of these terms. {{ config('nolbase.brand_name') }} reserves the right to suspend access to compute while preserving your data.</p>

                <h2>Liability</h2>
                <p>To be drafted with counsel.</p>

                <h2>Governing law</h2>
                <p>Federal Republic of Nigeria. Disputes will be resolved through the courts of TBD jurisdiction.</p>

                <h2>Contact</h2>
                <p>Questions or notices: {{ config('nolbase.support_email') }}.</p>
            </section>
        </div>

        <x-agpl-footer />
    </main>
@endsection
