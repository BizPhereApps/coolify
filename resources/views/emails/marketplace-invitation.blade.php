<x-emails.layout>
Hi,

**{{ $developerName }}** invited you to host **{{ $projectName }}** on Nolbase.

**Offer:** {{ $offerName }}
**Price:** ₦{{ number_format($priceMonthly) }}/month

[Accept &amp; set up your hosting]({{ $acceptUrl }})

This invitation expires in {{ $expiresInDays }} days. If you weren't expecting this, you can safely ignore it.

— Nolbase
</x-emails.layout>
