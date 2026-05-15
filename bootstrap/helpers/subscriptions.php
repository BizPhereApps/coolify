<?php

use App\Models\Team;

function isSubscriptionActive(): bool
{
    return once(function () {
        if (! isCloud()) {
            return false;
        }
        $team = currentTeam();
        if (! $team) {
            return false;
        }
        // Root team (id=0) doesn't require subscription
        if ($team->id === 0) {
            return true;
        }

        return $team->subscription?->isActive() === true;
    });
}

function isSubscriptionOnGracePeriod(): bool
{
    return once(function () {
        $team = currentTeam();
        if (! $team) {
            return false;
        }

        return (bool) $team->subscription?->cancel_at_period_end;
    });
}

function subscriptionProvider(): ?string
{
    return config('subscription.provider');
}

function isPaystack(): bool
{
    return config('subscription.provider') === 'paystack';
}

function allowedPathsForUnsubscribedAccounts(): array
{
    return [
        'subscription/new',
        'subscription',
        'pricing',
        'login',
        'logout',
        'force-password-reset',
        'two-factor-challenge',
        'livewire/update',
        'admin',
        'payments/paystack/callback',
        'payments/paystack/events',
        'legal/source',
    ];
}

function allowedPathsForBoardingAccounts(): array
{
    return [
        ...allowedPathsForUnsubscribedAccounts(),
        'onboarding',
        'livewire/update',
    ];
}

function allowedPathsForInvalidAccounts(): array
{
    return [
        'logout',
        'verify',
        'force-password-reset',
        'two-factor-challenge',
        'livewire/update',
    ];
}
