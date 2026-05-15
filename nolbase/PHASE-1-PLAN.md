# Nolbase Phase 1 — Implementation Plan

**Goal:** A real human can sign up at the local dev URL, get a 14-day Pro trial automatically, pay via Paystack when the trial ends, receive ZeptoMail emails, and attach a BYO server to deploy on. Stripe is gone. AGPL footer is live.

**Estimated effort:** 2-3 weeks of focused work.

---

## Audit findings (what already exists)

Coolify's "Cloud SaaS" layer is more developed than I'd assumed. Phase 1 is **less greenfield than the PRD implied** — we're mostly **rip-and-replace the existing Stripe layer with a Paystack layer**, not building from scratch.

| Concern | Existing state | Phase 1 action |
|---|---|---|
| `Subscription` model | Exists at [app/Models/Subscription.php](app/Models/Subscription.php) — 81 lines, holds `stripe_*` fields, `belongsTo(Team)`, has `type()` + `billingInterval()` helpers | **Repurpose**: rename `stripe_*` → `paystack_*` columns, add `plan_id` FK, keep `team()` relation |
| Plans | **No `Plan` model exists.** Plans are config-driven via [config/subscription.php](config/subscription.php) (env vars + Stripe price IDs). | **Build new**: `app/Models/Plan.php` + migration + seeder |
| Stripe actions | 5 files in [app/Actions/Stripe/](app/Actions/Stripe/): Cancel, CancelAtPeriodEnd, Refund, Resume, UpdateQuantity | **Delete all 5**, replace with `app/Actions/Paystack/` |
| Stripe jobs | 4 jobs: `StripeProcessJob`, `SyncStripeSubscriptionsJob`, `VerifyStripeSubscriptionStatusJob`, `UpdateStripeCustomerEmailJob` | **Delete all 4**, replace with `PaystackWebhookProcessJob` + `EndExpiredTrialsJob` |
| Stripe webhook | [app/Http/Controllers/Webhook/Stripe.php](app/Http/Controllers/Webhook/Stripe.php), route at `POST /payments/stripe/events` ([routes/webhooks.php:21](routes/webhooks.php#L21)) | **Delete**, replace with `Webhook/Paystack.php` + `POST /payments/paystack/events` |
| `isStripe()` helper | [bootstrap/helpers/subscriptions.php:55](bootstrap/helpers/subscriptions.php#L55) | **Replace** with `isPaystack()`; update all call sites |
| `isCloud()` helper | [bootstrap/helpers/shared.php:590](bootstrap/helpers/shared.php#L590) | **Keep** — gates cloud-only code paths. Returns true when we're running as Nolbase. |
| Fortify registration | [config/fortify.php:136](config/fortify.php#L136) — `Features::registration()` ENABLED, `emailVerification()` COMMENTED OUT | **Enable email verification**; intercept register to require plan-pick |
| `CreateNewUser` Fortify action | [app/Actions/Fortify/CreateNewUser.php](app/Actions/Fortify/CreateNewUser.php) — 71 lines | **Extend** to also create Subscription with 14-day Pro trial |
| Auth Blade views | All exist in [resources/views/auth/](resources/views/auth/): login, register, etc. | **Update register** to include plan pick + ToS checkbox |
| Mail config | [config/mail.php](config/mail.php) — standard Laravel, default `array` driver in `.env.local` | **Add** SMTP block for ZeptoMail + env keys |
| Existing trial emails | 3 templates: [trial-ends-soon.blade.php](resources/views/emails/trial-ends-soon.blade.php), [trial-ended.blade.php](resources/views/emails/trial-ended.blade.php), [subscription-invoice-failed.blade.php](resources/views/emails/subscription-invoice-failed.blade.php) | **Rebrand**: swap Coolify → Nolbase, NGN, Paystack |
| Team `limits` | [app/Models/Team.php:151](app/Models/Team.php#L151) — hardcoded 999999 for self-hosted, else `custom_server_limit ?? 2` | **Replace** with plan-driven: `$team->subscription->plan->max_servers` |
| Team `subscription()` hasOne | [app/Models/Team.php:252](app/Models/Team.php#L252) | **Keep** as-is — relation works |
| Hetzner service | [app/Services/HetznerService.php](app/Services/HetznerService.php) — already takes per-tenant token | **Defer to Phase 5** — no Phase 1 work needed |
| Subscription migrations | 10+ migrations adding `stripe_*` columns piecemeal since 2023 | **Squash** into one Nolbase migration; drop the table and recreate (no production data) |
| `config/subscription.php` | Stripe-only | **Rewrite** for Paystack |
| Coolify cloud-specific commands | `Cloud/CloudFixSubscription`, `Cloud/SyncStripeSubscriptions` in [app/Console/Commands/](app/Console/Commands/) | **Delete** |

---

## Phase 1 deliverables (in order of build)

### Step 1 — Schema reset (DB only, no code yet)

We're dropping the entire `subscriptions` table and rebuilding clean. This is safe because:
- No real users yet (dev only).
- We can `migrate:fresh` cheaply.
- Cleaner than maintaining 10+ Stripe migrations + adding more Paystack migrations on top.

**New migrations to create:**

```
database/migrations/2026_05_15_000001_drop_old_subscriptions_table.php
database/migrations/2026_05_15_000002_create_plans_table.php
database/migrations/2026_05_15_000003_create_subscriptions_table.php   # Paystack-native
database/migrations/2026_05_15_000004_create_paystack_events_table.php
database/migrations/2026_05_15_000005_add_nolbase_status_to_teams.php
```

**Old Stripe migrations to delete** (they'll never run again now that we're squashing):

```
database/migrations/2023_07_13_115117_create_subscriptions_table.php
database/migrations/2023_08_22_071050_update_subscriptions_stripe.php
database/migrations/2023_08_22_071051_add_stripe_plan_to_subscriptions.php
database/migrations/2023_08_22_071054_add_stripe_reasons.php
database/migrations/2023_08_22_071059_add_stripe_trial_ended.php
database/migrations/2024_05_10_085215_make_stripe_comment_longer.php
database/migrations/2025_03_01_112617_add_stripe_past_due.php
database/migrations/2025_04_01_124212_stripe_comment_nullable.php
database/migrations/2026_02_26_163035_add_stripe_refunded_at_to_subscriptions_table.php
```

**Plan table schema:**

```php
id, code (free|pro|business), name, description,
price_ngn_monthly, price_ngn_annual nullable,
paystack_plan_code_monthly nullable, paystack_plan_code_annual nullable,
max_servers, max_apps, max_databases, max_team_members,
features (jsonb),
is_public bool, sort_order, timestamps
```

**Subscription table schema:**

```php
id, team_id (uniq), plan_id,
paystack_subscription_code nullable, paystack_customer_code nullable,
status (trialing|active|past_due|cancelled|incomplete),
period (monthly|annual),
trial_ends_at nullable,
current_period_start nullable, current_period_end nullable,
cancel_at_period_end bool default false,
last_payment_failed_at nullable, refunded_at nullable,
timestamps
```

**Plans seeder:** insert Free / Pro / Business with the values from PRD §11.

### Step 2 — Rip Stripe

Delete these files in one commit titled `chore: remove Coolify Cloud Stripe layer`:

```
app/Actions/Stripe/                                      # entire directory
app/Jobs/StripeProcessJob.php
app/Jobs/SyncStripeSubscriptionsJob.php
app/Jobs/VerifyStripeSubscriptionStatusJob.php
app/Jobs/UpdateStripeCustomerEmailJob.php
app/Jobs/SubscriptionInvoiceFailedJob.php                # rebuild as Paystack version
app/Http/Controllers/Webhook/Stripe.php
app/Console/Commands/Cloud/CloudFixSubscription.php
app/Console/Commands/Cloud/SyncStripeSubscriptions.php
app/Livewire/Subscription/Index.php                      # rebuild
app/Livewire/Subscription/PricingPlans.php               # rebuild
app/Livewire/Subscription/Actions.php                    # rebuild
resources/views/livewire/subscription/                   # entire directory, rebuild
```

Update these (remove Stripe references):

```
app/Models/Subscription.php                              # rewrite for Paystack
app/Models/Team.php                                      # remove stripe_* field refs
app/Models/User.php                                      # check for Stripe refs
app/Livewire/Admin/Index.php                             # remove Stripe display
app/Console/Commands/AdminDeleteUser.php                 # remove Stripe customer cleanup
app/Console/Commands/CleanupStuckedResources.php         # remove Stripe refs
app/Console/Commands/ScheduledJobDiagnostics.php
app/Console/Commands/RunScheduledJobsManually.php
app/Jobs/ScheduledJobManager.php                         # remove Stripe job scheduling
app/Jobs/ServerManagerJob.php
app/Jobs/CleanupOrphanedPreviewContainersJob.php
app/Actions/User/DeleteUserTeams.php
bootstrap/helpers/subscriptions.php                      # rewrite
config/subscription.php                                  # rewrite for Paystack
routes/webhooks.php                                      # remove Stripe route
routes/api.php                                           # remove Stripe refs
```

Remove from `.env.example`:
```
STRIPE_API_KEY=
STRIPE_WEBHOOK_SECRET=
STRIPE_EXCLUDED_PLANS=
STRIPE_PRICE_ID_DYNAMIC_MONTHLY=
STRIPE_PRICE_ID_DYNAMIC_YEARLY=
```

### Step 3 — Build Paystack layer

**New files:**

```
config/paystack.php                                      # API keys, base URL, fee config
app/Services/PaystackService.php                         # API wrapper (HTTP client)

app/Actions/Paystack/InitializeSubscription.php          # called when tenant upgrades
app/Actions/Paystack/VerifyTransaction.php               # called after Paystack redirect
app/Actions/Paystack/CancelSubscription.php
app/Actions/Paystack/RefundTransaction.php
app/Actions/Paystack/HandleWebhookEvent.php              # dispatches per-event-type

app/Models/Plan.php
app/Models/PaystackEvent.php                             # idempotency + audit

app/Http/Controllers/Webhook/Paystack.php                # POST /payments/paystack/events
app/Jobs/PaystackWebhookProcessJob.php                   # async event processing
app/Jobs/EndExpiredTrialsJob.php                         # cron at 00:05 daily

app/Livewire/Subscription/Index.php                      # tenant billing page
app/Livewire/Subscription/PricingPlans.php               # plan picker
app/Livewire/Subscription/Actions.php                    # upgrade/downgrade/cancel actions

resources/views/livewire/subscription/index.blade.php
resources/views/livewire/subscription/pricing-plans.blade.php
resources/views/livewire/subscription/actions.blade.php

database/seeders/PlanSeeder.php
```

**Paystack webhook events to handle in `HandleWebhookEvent`:**
- `subscription.create` → mark subscription `active`
- `subscription.disable` → mark `cancelled`
- `subscription.not_renew` → mark `cancel_at_period_end = true`
- `invoice.create` → log
- `invoice.payment_failed` → mark `past_due`, queue ZeptoMail dunning email
- `charge.success` → mark `active` if was `past_due`
- `transfer.success` / `transfer.failed` → log (used in Phase 6 for marketplace payouts)

**Plan FK enforcement:** `Team::serverLimit()` rewritten to read from `$team->subscription->plan->max_servers`. Falls back to Free plan limits if no subscription.

### Step 4 — Registration + trial wiring

**Files to update:**

```
config/fortify.php
  - Uncomment Features::emailVerification()

app/Actions/Fortify/CreateNewUser.php
  - After user + team creation, also create a Subscription with:
    - plan_id = Plan::where('code', 'pro')->first()->id
    - status = 'trialing'
    - trial_ends_at = now()->addDays(14)
    - period = 'monthly'
    - No paystack_subscription_code yet (added on first payment)

resources/views/auth/register.blade.php
  - Add: "By signing up you start a 14-day Pro trial — no card required."
  - Add: ToS + Privacy checkbox
  - (Plan picker NOT required on register — everyone starts Pro trial)

routes/web.php
  - Verify Fortify routes register correctly (existing logic mostly works)
  - Add: GET /pricing (public)
  - Add: GET /legal/source (AGPL compliance page)
```

**`EndExpiredTrialsJob`** runs daily, processes:
- Trials where `trial_ends_at < now()` AND `paystack_subscription_code IS NULL`
  → Downgrade to Free plan, send email "Trial ended — you're on the Free plan."
- Trials where `trial_ends_at < now()` AND `paystack_subscription_code IS NOT NULL`
  → Already converted (Paystack will charge). No action.

Schedule it in `app/Console/Kernel.php`:
```php
$schedule->job(new EndExpiredTrialsJob)->dailyAt('00:05');
```

### Step 5 — ZeptoMail

**Files:**

```
config/mail.php
  - Add 'zepto' mailer:
    'zepto' => [
        'transport' => 'smtp',
        'host' => env('ZEPTO_HOST', 'smtp.zeptomail.com'),
        'port' => env('ZEPTO_PORT', 587),
        'encryption' => 'tls',
        'username' => env('ZEPTO_USERNAME'),
        'password' => env('ZEPTO_PASSWORD'),
        'timeout' => null,
        'local_domain' => env('MAIL_EHLO_DOMAIN'),
    ]

.env (dev) — point default to mailpit (existing setup, unchanged)
.env.production — point default to 'zepto' once ZeptoMail credentials are obtained

resources/views/emails/
  - Rebrand existing trial-ends-soon, trial-ended, subscription-invoice-failed
  - Replace "Coolify" → "Nolbase", change colors to brand palette, USD → NGN
```

### Step 6 — AGPL compliance

**Files:**

```
routes/web.php
  - Route::get('/legal/source', fn() => view('legal.source'))->name('legal.source');

resources/views/legal/source.blade.php (new)
  - Explain AGPL, link to public source repo, show current commit SHA

resources/views/layouts/app.blade.php (existing footer area)
  - Add: <a href="/legal/source">Source code</a> link in footer

resources/views/layouts/base.blade.php
  - Add similar to base template if there's a public-facing footer there
```

### Step 7 — Tests (Pest 4)

**Test plan — minimum coverage for Phase 1:**

```
tests/Feature/Auth/RegistrationTest.php
  - it('creates user + team + 14-day Pro trial on registration')
  - it('sends ZeptoMail verification email on registration')
  - it('blocks deploy actions until email verified')

tests/Feature/Billing/PlanSeederTest.php
  - it('seeds Free / Pro / Business plans with correct quotas')

tests/Feature/Billing/TrialExpiryTest.php
  - it('downgrades to Free when trial ends without payment')
  - it('keeps Pro when trial ends with active Paystack subscription')

tests/Feature/Billing/PaystackWebhookTest.php
  - it('handles subscription.create webhook → marks active')
  - it('handles invoice.payment_failed → marks past_due')
  - it('rejects requests with invalid Paystack signature')
  - it('is idempotent on duplicate event delivery')

tests/Feature/Billing/QuotaEnforcementTest.php
  - it('blocks 4th app on Free plan')
  - it('allows unlimited apps on Pro')

tests/Feature/Legal/AgplFooterTest.php
  - it('shows source-code link in footer')
  - it('serves /legal/source page with current commit SHA')
```

---

## What's deliberately NOT in Phase 1

- Reseller / sub-tenancy (Phase 6)
- BYO cloud (Phase 5) — Hetzner already attaches via existing UI, that's enough for Phase 1
- Nolbase-managed hosting (Phase 7)
- Super-admin panel (Phase 3)
- Plan quota UI polish (Phase 2)
- Marketing site / pricing landing page beyond a minimal `/pricing` route
- Annual billing — Phase 1 is monthly only; annual added in Phase 2
- Custom domains for the control plane

---

## Risks / things I'll flag if they come up mid-build

1. **`isCloud()` is used in ~50 places.** Most should keep working — we just need to set the right env flag (probably `SELF_HOSTED=false`) for Nolbase mode. I'll grep + spot-check before declaring done.
2. **Team `limits` attribute is referenced widely.** Rewiring to plan-driven means touching every call site. I'll keep the method signature (`Team::serverLimit()`) so call sites are unchanged.
3. **Coolify might re-broadcast removed Stripe jobs** in `ScheduledJobManager`. I'll need to scrub that file carefully.
4. **`SUBSCRIPTION_PROVIDER` env var** drives which subscription provider is active. Setting it to `paystack` (and updating `isStripe()`/`isPaystack()` accordingly) flips the whole app over.
5. **Coolify upstream sync** will conflict — every time Coolify maintainers add a Stripe migration, we'll need to skip it. I'll document this in `nolbase/UPSTREAM-MERGE.md` as we go.
6. **Paystack subscription model** charges immediately on `create`. To honor "no card required for trial," we don't call Paystack's subscription endpoint until the trial ends (or the user upgrades early). Trial is purely Nolbase-tracked.

---

## Build sequence (the order I'll actually do this)

| Day | Work |
|---|---|
| 1 | Schema reset (Step 1): migrations + Plan model + Plan seeder + run `migrate:fresh --seed` |
| 2 | Rip Stripe (Step 2): one big commit deleting Stripe-only files; second commit cleaning up references; app should still boot |
| 3 | Paystack scaffolding (Step 3a): config + Service + Actions skeletons + Plan model wired to subscriptions |
| 4 | Paystack webhook (Step 3b): controller, route, Job, signature verification, event handlers |
| 5 | Subscription Livewire (Step 3c): Index, PricingPlans, Actions + Blade views |
| 6 | Registration + trial (Step 4): CreateNewUser extension + EndExpiredTrialsJob + scheduler |
| 7 | ZeptoMail (Step 5): config + email rebrand + manual test against ZeptoMail sandbox |
| 8 | AGPL footer + /legal/source (Step 6) |
| 9-10 | Pest tests (Step 7) + bugfixes |
| 11-12 | End-to-end manual test: real signup → real trial → real Paystack test charge → real BYO server attach → real deploy. Fix everything that breaks. |
| 13-14 | Buffer for unexpected issues, documentation, demo recording |

---

## Confirmation needed before I start

1. **Schema reset** — confirm we drop the existing `subscriptions` table and squash the Stripe migrations. (No production data; safe.)
2. **AGPL public repo URL** — what's the GitHub org/repo for the Nolbase fork? The footer link needs a real URL.
3. **Paystack test credentials** — do you have a Paystack dashboard / test API key handy, or should I scaffold with `.env.example` placeholders and you'll plug them in later?
4. **ZeptoMail credentials** — same question.
5. **Branch strategy** — work on a new `nolbase-phase-1` branch off `v4.x`, or commit directly to `v4.x`?

Once you confirm, I start with Step 1 (schema reset). Each step is a separate commit so we can review/revert per-step.
