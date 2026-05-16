<x-emails.layout>
Hi {{ $teamName }} team,

Your Nolbase-managed server **{{ $serverName }}** has been suspended after an extended period of unpaid charges.

**What this means:**
- The underlying virtual machine has been powered off — your apps are not reachable right now.
- Your data is preserved. We have not deleted anything.

**To restore service:** add or update your payment method and contact support so we can power the server back on.

[Manage payment method]({{ $subscriptionUrl }})

If the server stays suspended for an extended period it may be decommissioned and the data permanently lost. Reach out if you need more time.
</x-emails.layout>
