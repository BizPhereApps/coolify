<x-emails.layout>
Hi {{ $teamName }} team,

We were unable to charge your monthly bill of ₦{{ number_format($totalNgn) }} for your Nolbase-managed hosting.

Reason from your bank: {{ $reason }}

Your servers and apps are still running, but they will be **automatically suspended** if the bill remains unpaid. Please update your payment method or top up your card and we'll retry on the next billing cycle.

[Manage payment method]({{ $subscriptionUrl }})

If you've already resolved this, you can ignore this email — the next billing run will pick it up.
</x-emails.layout>
