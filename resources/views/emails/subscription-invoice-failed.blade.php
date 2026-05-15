<x-emails.layout>
Your last Nolbase subscription renewal failed.

Paystack couldn't charge your card. Please update your payment method at [your subscription page]({{ $subscriptionUrl ?? url('/subscription') }}) — we'll retry in a few days.

If the renewal continues to fail, your account will move to the Free plan automatically after a grace period. Existing servers and apps stay running; Pro-only features pause.
</x-emails.layout>
