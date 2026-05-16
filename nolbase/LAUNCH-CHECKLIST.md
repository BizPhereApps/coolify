# Nolbase Launch Checklist

**Status:** Draft v2
**Owner:** putup9ja@gmail.com
**Last updated:** 2026-05-16

This document is what you work through to take the current `nolbase-phase-1`
branch from green tests to a real customer paying real Naira. It's
opinionated — every line is something I'd actually do, not generic
hand-waving. Skip nothing in §1 and §8.

---

## Current launch status (2026-05-16)

| Block | State | Owner |
|---|---|---|
| §1.1 Domain `nolbase.io` + `app.nolbase.io` DNS + TLS | **DONE** | — |
| §1.2 Production server | DONE | — |
| §1.3 Source repo `BizPhereApps/coolify` public | needs confirmation | you |
| §1.4 Legal pages live on marketing site | TODO | you |
| §2.1 Paystack business verification + Transfers approved | **DONE** | — |
| §2.2 Create 4 plan codes in Paystack + map onto plans table | TODO | you (see new artisan command below) |
| §2.3 Webhook endpoint registered in Paystack dashboard | TODO | you |
| §3 ZeptoMail: domain verified + SPF/DKIM/DMARC live | **DONE** | — |
| §5 Production `.env` filled in | TODO | you |
| §7 Drills A-G with real test cards | TODO | you |
| §8 Nigerian fintech lawyer conversation | **TODO ← only true blocker** | you + lawyer |
| §9 Backups, monitoring, alerting wiring | partial (code-side done; uptime monitor TODO) | you |

In other words: the engineering is done, plus operational §1, §2.1, §3.
What's left is creating the 4 plan codes, filling `.env`, the lawyer
conversation, and the deploy + drills.

---

## TL;DR

```
1.  Buy domain → set DNS → TLS cert                    (§1)
2.  Get Paystack live + ZeptoMail credentials           (§2, §3, §4)
3.  Production .env filled with real values              (§5)
4.  Public AGPL fork URL set                             (§6)
5.  Talk to a Nigerian fintech lawyer before launch      (§8)  ← non-skippable
6.  Operational drill: do every flow end-to-end          (§7)
7.  Wire up monitoring + backups before first signup     (§9)
8.  Document day-1, week-1, month-1 ops cadence          (§10)
```

### Automated verification

The repository ships a preflight command that automates every check in
§4 (env vars), §5 (smoke), §6 (AGPL), and the parts of §7 that don't
need a real customer interaction:

```bash
docker exec coolify php artisan nolbase:preflight              # human output
docker exec coolify php artisan nolbase:preflight --strict     # treat warnings as failures
docker exec coolify php artisan nolbase:preflight --json       # machine-readable
```

Run it after each deploy. **Exit code 0 = green, non-zero = blockers.**
The command checks 15 distinct items including: APP_ENV/APP_DEBUG, DB
connectivity, multi-tenant mode, Telescope disabled, AGPL source URL
non-placeholder, /legal/source route registered, Paystack secrets
present + LIVE in production, webhook route registered, mail from
address non-placeholder, mailer not 'log' in production, all 3 plans
seeded, Pro/Business plans have Paystack codes, at least one
superadmin exists, marketplace fee % is sane.

---

## §1. Pre-flight one-time setup

### 1.1 Domain & DNS

- [ ] Buy `nolbase.io` (or the chosen production domain) if not already owned.
- [ ] Decide subdomain split:
  - `nolbase.io` — marketing site (out of scope here)
  - `app.nolbase.io` — the actual Nolbase control plane (this codebase)
  - `webhooks.nolbase.io` (optional) — Paystack webhook target, separated so you can rate-limit / firewall it independently
  - `mail.nolbase.io` — ZeptoMail sending subdomain (better for deliverability than the apex)
- [ ] A-record `app.nolbase.io` → production server IP.
- [ ] AAAA-record if IPv6 is in scope (Hetzner gives you v6 free).
- [ ] Let's Encrypt cert via Traefik (Coolify handles this automatically once the domain resolves to the server).

### 1.2 Server

- [ ] One production Hetzner server (or equivalent), minimum CCX13 or comparable: 2 vCPU, 8GB RAM, 80GB NVMe. ARM (CAX21) is cheaper if your build doesn't need x86-only deps.
- [ ] Install Coolify on it via the standard install script (this is the same Coolify control-plane you're customising; you self-host your own fork).
- [ ] Set the server's hostname, harden SSH (key-only, no root login), enable UFW (allow 22, 80, 443, 6001/6002 for Soketi if external clients need it).

### 1.3 Source repository

- [ ] Make the GitHub fork `github.com/BizPhereApps/coolify` (or rename to `BizPhereApps/nolbase`) **public**. This is your AGPLv3 obligation — see §6.
- [ ] Set the source URL you'll show in the footer to the public repo's main branch.

### 1.4 Legal pages

- [ ] Privacy policy at `nolbase.io/legal/privacy` (or wherever your marketing site lives).
- [ ] Terms of Service at `nolbase.io/legal/terms`.
- [ ] These are linked from `<x-agpl-footer />`. Both should pre-date taking real money.
- [ ] Make sure the ToS covers: marketplace mediator role, fee structure, refund policy, Developer-leaves-Nolbase scenario, suspension grounds, dispute resolution forum, governing law (Nigeria).

---

## §2. Paystack dashboard configuration

### 2.1 Business verification (do this WEEKS before launch)

- [ ] Sign up at paystack.com if not already.
- [ ] Complete business verification: CAC certificate, BVN, bank statement, utility bill. **This can take 5-10 business days**, not hours. Don't leave it for the launch week.
- [ ] Enable **NGN currency** for both Charge (collecting from Clients) and Transfer (paying out to Developers).
- [ ] Request **Transfers** permission — by default Paystack requires you to apply for this. Their team reviews business model. Tell them you're running a marketplace; have your KYC docs ready.

### 2.2 Plans (Nolbase's own subscription plans, Phase 1)

You can create these via Paystack dashboard OR via API. The plan codes go in
`plans.paystack_plan_code_monthly` / `_annual`.

- [ ] Create **Pro Monthly**: ₦15,000 / month, NGN, plan code → save.
- [ ] Create **Pro Annual**: ₦150,000 / year, NGN, plan code → save.
- [ ] Create **Business Monthly**: ₦50,000 / month → save.
- [ ] Create **Business Annual**: ₦500,000 / year → save.
- [ ] Map them onto the local `plans` table using the dedicated command (validates the `PLN_` prefix, idempotent re-runs):
  ```bash
  docker exec coolify php artisan nolbase:plans:set-paystack-codes \
    --pro-monthly=PLN_xxx \
    --pro-annual=PLN_yyy \
    --business-monthly=PLN_zzz \
    --business-annual=PLN_www
  ```
  Run with no flags to enter the codes interactively.

### 2.3 Webhook endpoint

- [ ] Paystack dashboard → Settings → API Keys & Webhooks
- [ ] Webhook URL: `https://app.nolbase.io/webhooks/payments/paystack/events`
- [ ] Copy the webhook secret → put in `PAYSTACK_WEBHOOK_SECRET` in production `.env`
- [ ] **Test the webhook** before launch: Paystack dashboard → Send test webhook → verify a `paystack_events` row lands and `processed_at` becomes non-null.

### 2.4 Live API keys

- [ ] Copy live `sk_live_...` (secret) and `pk_live_...` (public) keys.
- [ ] Set `PAYSTACK_SECRET_KEY` (server-side only, **never** commit), `PAYSTACK_PUBLIC_KEY` in `.env`.
- [ ] Confirm `PAYSTACK_BASE_URL=https://api.paystack.co` (no override).

### 2.5 Marketplace settings (for §8 lawyer conversation)

- [ ] Decide platform fee percentage. Default in code is 10%. Adjustable via `NOLBASE_MARKETPLACE_FEE_PCT` env or `/nolbase/admin/settings` once live.
- [ ] Decide payout cadence. Default code: weekly Monday 09:00 Africa/Lagos. Adjustable in `app/Console/Kernel.php`.
- [ ] Decide minimum payout. Default: ₦5,000 (`RunPayoutBatch::MIN_PAYOUT_NGN`). Below this rolls to next run.

---

## §3. ZeptoMail (transactional email) setup

### 3.1 Account + domain verification

- [ ] Sign up at zeptomail.com (Zoho).
- [ ] Add your sending subdomain: `mail.nolbase.io` (recommended) or `nolbase.io`.
- [ ] ZeptoMail will give you DNS records to add. Add them to your DNS provider.

### 3.2 SPF / DKIM / DMARC (non-optional for deliverability)

- [ ] **SPF**: TXT record on `mail.nolbase.io` (or apex): `v=spf1 include:zeptomail.com ~all`
- [ ] **DKIM**: ZeptoMail issues a public key TXT record at a `<selector>._domainkey.mail.nolbase.io` path. Add exactly what they give you.
- [ ] **DMARC**: TXT on `_dmarc.nolbase.io`: `v=DMARC1; p=quarantine; rua=mailto:dmarc@nolbase.io; pct=100`. Start with `p=none` for first 2 weeks while you monitor reports, then move to `p=quarantine`, eventually `p=reject` once clean.
- [ ] Wait for ZeptoMail dashboard to confirm domain is verified (usually 5-30 min after DNS propagates).

### 3.3 SMTP credentials

- [ ] In ZeptoMail dashboard, create a Mail Send token. They give you SMTP host, port, username, password.
- [ ] Production `.env`:
  ```
  MAIL_MAILER=zepto
  ZEPTO_HOST=smtp.zeptomail.com
  ZEPTO_PORT=587
  ZEPTO_USERNAME=<from dashboard>
  ZEPTO_PASSWORD=<from dashboard>
  MAIL_FROM_ADDRESS=noreply@mail.nolbase.io
  MAIL_FROM_NAME="Nolbase"
  ```

### 3.4 Test before going live

- [ ] Sign yourself up at `app.nolbase.io/register` with a personal email. Confirm:
  - Verification email arrives
  - Subject + sender look right (no "via mail.zeptomail.com" — that means DKIM isn't aligned)
  - Click verify link, it works
- [ ] Send yourself a test marketplace invitation. Confirm same.
- [ ] Use mail-tester.com to verify: aim for 10/10. Below 8/10 = something is wrong with your DNS records.

---

## §4. Production `.env` reference

Below is every variable that matters. Anything left as a placeholder = a real
failure waiting to happen.

```bash
# ── Core ─────────────────────────────────────────────────────────────
APP_NAME=Nolbase
APP_ENV=production
APP_KEY=base64:<generate via `php artisan key:generate --show`>
APP_DEBUG=false
APP_URL=https://app.nolbase.io
APP_PORT=8000

# Multi-tenant cloud mode (NOT self-hosted)
SELF_HOSTED=false

# ── Database (Postgres) ─────────────────────────────────────────────
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=coolify
DB_USERNAME=coolify
DB_PASSWORD=<strong random>

# ── Redis ───────────────────────────────────────────────────────────
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=<strong random>

# ── Subscriptions (Paystack) ────────────────────────────────────────
SUBSCRIPTION_PROVIDER=paystack
PAYSTACK_PUBLIC_KEY=pk_live_<...>
PAYSTACK_SECRET_KEY=sk_live_<...>           # NEVER commit. Server-side only.
PAYSTACK_WEBHOOK_SECRET=<from dashboard>
# PAYSTACK_BASE_URL defaults to https://api.paystack.co — don't override in prod.
PAYSTACK_CALLBACK_URL=/payments/paystack/callback
NOLBASE_MARKETPLACE_FEE_PCT=10

# ── Mail (ZeptoMail) ────────────────────────────────────────────────
MAIL_MAILER=zepto
ZEPTO_HOST=smtp.zeptomail.com
ZEPTO_PORT=587
ZEPTO_USERNAME=<from dashboard>
ZEPTO_PASSWORD=<from dashboard>
MAIL_FROM_ADDRESS=noreply@mail.nolbase.io
MAIL_FROM_NAME="Nolbase"
MAIL_EHLO_DOMAIN=mail.nolbase.io

# ── Realtime (Soketi) ───────────────────────────────────────────────
PUSHER_HOST=app.nolbase.io
PUSHER_PORT=6001
PUSHER_SCHEME=https
PUSHER_APP_ID=<random>
PUSHER_APP_KEY=<random>
PUSHER_APP_SECRET=<random>
SOKETI_HOST=0.0.0.0

# ── Nolbase brand + AGPL ────────────────────────────────────────────
NOLBASE_BRAND_NAME=Nolbase
NOLBASE_SUPPORT_EMAIL=support@nolbase.io
NOLBASE_SOURCE_REPO_URL=https://github.com/BizPhereApps/nolbase

# ── Optional: Sentry / error tracking ───────────────────────────────
# SENTRY_LARAVEL_DSN=
# SENTRY_TRACES_SAMPLE_RATE=0.1
```

**Verify before deploy:**
```bash
docker exec coolify php artisan config:cache
docker exec coolify php artisan nolbase:preflight --strict
# Exit 0 = green. Any non-zero = fix before going live.
```

---

## §5. First production deploy

### 5.1 Smoke checks immediately after deploy

- [ ] `curl https://app.nolbase.io/api/health` → 200 OK (trivial liveness)
- [ ] `curl https://app.nolbase.io/api/nolbase/health` → 200 with `"status":"healthy"`. Component-level: DB + Redis. Wire this into your uptime monitor.
- [ ] `curl 'https://app.nolbase.io/api/nolbase/health?deep=1'` → also probes Paystack reachability (do not poll this from monitoring — once per deploy is enough)
- [ ] `curl -I https://app.nolbase.io/login` → 200, valid TLS
- [ ] `curl -I https://app.nolbase.io/legal/source` → 200, page shows your real public repo URL
- [ ] `curl -I https://app.nolbase.io/register` → 200
- [ ] Sign up a real test user (your personal email). Receive trial email.
- [ ] `docker exec coolify php artisan tinker --execute 'echo App\Models\Plan::count();'` → 3

### 5.2 Seed the first Nolbase super-admin

```bash
docker exec -it coolify php artisan nolbase:admin:create
# Enter your name, email, role=superadmin, strong password (12+ chars,
#   upper/lower/digit/symbol)
```

- [ ] Log in at `https://app.nolbase.io/nolbase/admin/login`
- [ ] Confirm Dashboard loads, MRR=₦0, no tenants yet
- [ ] Visit `/nolbase/admin/settings` and **set the real source-repo URL there too** (writes to `nolbase_settings`, takes precedence over the env default)

### 5.3 Disable Telescope / Debugbar in production

- [ ] `TELESCOPE_ENABLED=false` (default) — leaving Telescope on in production leaks query payloads.
- [ ] `APP_DEBUG=false` — no Whoops error pages in production.
- [ ] Verify by visiting a known-broken URL — you should see a generic 500, not a stack trace.

---

## §6. AGPL compliance verification

You agreed to AGPLv3 in the PRD. Walk these before announcing publicly:

- [ ] Source repo is **public** (not "private with read-only access").
- [ ] Latest production commit SHA is in the public repo's main/v4.x branch.
- [ ] Footer "Source code" link on every page resolves to that public repo.
- [ ] `/legal/source` page displays correctly with the **real** repo URL (not `REPLACE-ME`).
- [ ] You have a way to publish modified source on every production release. Easiest: just `git push origin nolbase-phase-1` (or whatever your prod branch is named) every time you deploy.
- [ ] No proprietary patches kept outside the public repo. AGPL §13: source you run = source you publish.

---

## §7. Pre-launch end-to-end operational drill

Do every one of these in a test session, with a test bank account if possible.
**Do this before sending any real Developer the signup URL.**

### Drill A: Tenant signup → trial → upgrade

1. [ ] Visit `https://app.nolbase.io/register`, sign up with a fresh email.
2. [ ] Receive verification email (check spam folder; if there, your DKIM is broken).
3. [ ] Click verify, land in dashboard.
4. [ ] Confirm a Subscription row exists with `status=trialing` and `trial_ends_at=+14d`.
5. [ ] Click "Subscription" → "Change plan" → choose Pro Monthly.
6. [ ] Land on Paystack hosted checkout. Use a **real test card** that Paystack supports for live mode (4084 0840 8408 4081 if your account is still in test mode; for live mode use your own card with a small amount).
7. [ ] Get redirected back to `/subscription`. Status flips to `active`.
8. [ ] Paystack dashboard → Webhooks → confirm `charge.success` event was delivered.
9. [ ] `nolbase_admin/audit` should show the registration event.

### Drill B: BYO server attach

10. [ ] Attach an existing server via SSH (your test Hetzner box).
11. [ ] Coolify connects, sets up Docker, server appears Healthy.

### Drill C: Marketplace flow (the new feature)

12. [ ] `/reseller` → New offer → fill in fields → save.
13. [ ] Send invitation to a different email you control (your phone email, say).
14. [ ] Receive marketplace invitation email. Click link.
15. [ ] On `/invitations/{token}` page, click Accept & pay.
16. [ ] Pay via Paystack hosted checkout.
17. [ ] Auto-redirected to `/client`. SubTeam visible.
18. [ ] **Money check:**
    - `marketplace_transactions` table now has a TYPE_CHARGE row + TYPE_FEE row.
    - Switch back to the Developer account, visit `/reseller/payouts` → pending balance shows the net.
19. [ ] Run `php artisan tinker --execute '(new App\Jobs\RunPayoutBatchJob)->handle();'`
    - Paystack must have your account verified + transfer permission enabled (§2).
    - A TYPE_PAYOUT row appears. Paystack dashboard shows the transfer.

### Drill D: Suspended team block

20. [ ] As superadmin, suspend the test tenant from `/nolbase/admin/tenants/{id}`.
21. [ ] Try to log in as that tenant → blocked with "Account suspended" message.
22. [ ] Unsuspend → login works again.

### Drill E: Trial expiry

23. [ ] Pick a test trialing subscription and backdate its `trial_ends_at`:
    ```bash
    docker exec coolify php artisan tinker --execute '
      App\Models\Subscription::find(<id>)->update(["trial_ends_at" => now()->subDay()]);
    '
    ```
24. [ ] Run the job manually: `docker exec coolify php artisan tinker --execute '(new App\Jobs\EndExpiredTrialsJob)->handle();'`
25. [ ] Subscription now on Free plan. Owner receives `trial-ended` email.

### Drill F: AGPL footer

26. [ ] Visit `/login`, `/register`, `/legal/source`, `/`. Confirm footer present on each.

### Drill G: Webhook signature rejection

27. [ ] `curl -X POST https://app.nolbase.io/webhooks/payments/paystack/events -d '{}'`
    → must return 401. If 200, your webhook secret is misconfigured.

If any of A-G fails, **don't launch.** Fix it first.

---

## §8. Nigerian regulatory / tax — DO NOT SKIP

This section is the genuinely scary one. **You're now mediating money between
Clients and Developers, taking a platform fee, and sending payouts.** Nigerian
regulators consider this payment-aggregator activity. Before launch:

### 8.1 Talk to a fintech lawyer in Lagos

Not a generic corporate lawyer — someone who knows CBN guidelines. Topics to
walk through:

- [ ] **Payment aggregator licensing.** CBN's PSP licensing tiers (Switching, PSSP, PA, PTSP). Mediating Client → Developer payments may put you in PA territory. Paystack themselves are PA-licensed, but your role as platform-on-top-of-Paystack is a separate analysis. Counsel should clarify whether you're (a) operating under Paystack's PA license as a sub-merchant (most likely for v1), or (b) need your own license.
- [ ] **VAT (7.5%).** Are you required to collect VAT on the platform fee? On the gross transaction? The answer depends on whether you're treated as the principal merchant or as an agent. Affects pricing.
- [ ] **Withholding tax on payouts.** Section 81 of Companies Income Tax Act may require you to withhold WHT (5%) on Developer payouts and remit to FIRS. The Developer would then claim it as a credit.
- [ ] **Reporting obligations.** Once you cross volume thresholds, FIRS / CBN want monthly/quarterly returns.
- [ ] **CAC + corporate structure.** If you haven't already, register a Limited Liability Company at CAC. Operating personally as a payment mediator is a bad idea.
- [ ] **AML / KYC.** Even riding on Paystack's KYC, you collect bank account details for Developer payouts. CBN AML/CFT guidelines require record-keeping and suspicious-transaction reporting.

Budget ₦200k–₦500k for the consultation + setup. **Don't take the first real
Client payment until counsel has signed off.**

### 8.2 Operational paperwork to have ready

- [ ] CAC certificate of incorporation
- [ ] TIN (Tax Identification Number)
- [ ] FIRS VAT registration if applicable
- [ ] Memorandum & Articles of Association (Paystack will want these for upgraded merchant status)
- [ ] Beneficial-owner disclosure (BOI / CAC) — anyone with >5% ownership
- [ ] Privacy policy compliant with NDPR (Nigeria Data Protection Regulation)

### 8.3 Recordkeeping

- [ ] Marketplace transactions are already logged in `marketplace_transactions` (this codebase). Confirm they're being backed up (§9).
- [ ] Retain records for at least **5 years** per CITA. Make sure your Postgres backup retention meets that.

---

## §9. Backups, monitoring, alerting

Wire these up **before** the first signup, not after.

### 9.1 Database backups

- [ ] `pg_dump` daily, retained 30 days locally + 12 months off-site (S3 / Backblaze / Wasabi).
- [ ] Encryption: enable `pgcrypto` at-rest if you go beyond plain volume encryption.
- [ ] **Restore drill**: actually restore the latest backup into a scratch database once a week. A backup you've never restored is not a backup.

### 9.2 Application monitoring

- [ ] Error tracking — Sentry or Bugsnag. Free tier is fine to start. Set `SENTRY_LARAVEL_DSN` in `.env`.
- [ ] Uptime monitor — Better Stack, UptimeRobot, etc.
    - Liveness probe → `/api/health` every 60s (trivially returns 200 if PHP is alive)
    - Readiness/component probe → `/api/nolbase/health` every 60s. Alert on `status:"degraded"` or HTTP 503. This catches DB/Redis outages that `/api/health` will miss.
- [ ] Status page — `status.nolbase.io` (optional but recommended once you have paying customers).

### 9.3 Business metrics dashboard

- [ ] `/nolbase/admin` already shows MRR, ARR, plan distribution, past-due count.
- [ ] Bookmark it. Glance daily.

### 9.4 Logs

- [ ] `storage/logs/laravel.log` rotates how often? Default is `daily` — confirm and set retention.
- [ ] Pipe to a managed service (Loki, Datadog, BetterStack) once volume justifies.
- [ ] **Never** log Paystack secrets or webhook bodies — the existing code only logs event IDs and types. Audit your custom additions.

### 9.5 Alerts (Slack / Discord / email)

The repository ships an operator-facing alert notifier that posts to
any Slack-compatible incoming webhook (Discord accepts the same payload
via `/slack` suffix). Set:

```bash
NOLBASE_ALERTS_WEBHOOK_URL=https://hooks.slack.com/services/T../B../...
# or store in the DB so it's rotatable without a redeploy:
docker exec coolify php artisan tinker --execute '
  App\Models\NolbaseSetting::write("nolbase_alerts_webhook_url", "https://...");
'
```

Already wired:

- [x] Webhook signature failures → WARN alert with caller IP + event
- [x] Managed-billing failures → WARN alert with team + amount + reason
- [x] Managed-server suspensions (grace expired) → CRITICAL alert
- [ ] Paystack webhook 5xx responses (not yet wired — `HandleWebhookEvent` throws are only logged)
- [ ] DB connection drops (catch from `/api/nolbase/health` polling, not yet in-process)
- [ ] Payout batch failures (`RunPayoutBatchJob` logs `skipped` count — set up an alert if `paid=0 && pending_balance > 0`)
- [ ] Health check failures
- [ ] Trial-to-paid conversion rate drops below X% (longer-term)

---

## §10. Recurring ops cadence

### Daily (5 minutes)

- [ ] Check `/nolbase/admin` dashboard: MRR, past-due count, trialing count
- [ ] Check `storage/logs/laravel.log` for `ERROR` lines from the previous 24h
- [ ] Verify scheduled jobs ran: `EndExpiredTrialsJob`, `SendTrialEndingSoonRemindersJob`
- [ ] Skim Paystack dashboard for failed transactions

### Weekly (30 minutes)

- [ ] Monday: confirm `RunPayoutBatchJob` ran successfully at 09:00 Africa/Lagos
- [ ] Cross-check: Paystack dashboard transfer count = `MarketplaceTransaction::where('type', 'payout')->whereDate('occurred_at', $today)->count()`
- [ ] Review audit log at `/nolbase/admin/audit` — any unusual super-admin actions
- [ ] Run a fresh DB restore drill (§9.1)

### Monthly

- [ ] Review plan distribution. Adjust pricing if needed.
- [ ] Review payout fees (you absorb a Paystack transfer fee on each Developer payout — track real margin).
- [ ] VAT/WHT remittance (after lawyer guidance in §8.1)
- [ ] Re-verify SPF/DKIM/DMARC at mail-tester.com — DNS can drift if you change providers
- [ ] Re-pull the AGPL public repo, verify it matches what's running in production

### Quarterly

- [ ] Pen-test light pass: try the OWASP Top 10 against `app.nolbase.io`. Specifically: cross-tenant access, sub-team scoping (a Client should NOT be able to navigate to another Client's project URL), webhook signature bypass, password reset, OAuth callback hijack.
- [ ] Review upstream Coolify commits. Cherry-pick critical security fixes; document anything you skipped and why.
- [ ] Renew Paystack business-status if they require it.

---

## §11. Incident response basics

When (not if) something goes wrong:

### Paystack webhook backlog

- Symptom: charges aren't reflected in subscriptions
- Diagnosis: `SELECT * FROM paystack_events WHERE processed_at IS NULL ORDER BY created_at;`
- Action: `docker exec coolify php artisan queue:restart && php artisan horizon` to drain. If a specific event is stuck on `error`, the row's `error` column will tell you why.

### A tenant says "I paid but my account shows trial"

- Diagnosis 1: Did the webhook arrive? Check `paystack_events` for their `paystack_customer_code`.
- Diagnosis 2: Did `VerifyTransaction` run? Check the `Subscription.paystack_subscription_code` — if null, the callback didn't fire (typically because they paid but closed the tab before redirect).
- Action: as superadmin, manually re-run verify:
  ```php
  App\Actions\Paystack\VerifyTransaction::run('<reference from Paystack dashboard>');
  ```

### A payout failed but money left Paystack

- Diagnosis: `MarketplaceTransaction` row will have `status='pending'` indefinitely if Paystack accepted but never confirmed
- Cross-reference Paystack dashboard → Transfers → the reference
- Action: hand-update `marketplace_transactions.status='success'` once Paystack confirms. Audit-log the manual edit.

### Suspected breach

- Immediately rotate: `APP_KEY`, `PAYSTACK_SECRET_KEY`, `PAYSTACK_WEBHOOK_SECRET`, `ZEPTO_PASSWORD`, DB passwords
- Force-logout all sessions: `docker exec coolify php artisan session:flush` (or truncate the `sessions` table)
- Review `nolbase_admin_audit` for the last 30 days
- Notify affected users within 72 hours per NDPR
- File CBN/NITDA breach report if PII or payment data exposed

---

## §12. What's intentionally NOT in this checklist

The following are real concerns but out of scope for v1 launch:

- **Nolbase-managed hosting (Phase 7).** Cost markup, VPS lifecycle, Paystack invoice line items. Skip until BYO is proven.
- **SSO (SAML/OIDC).** Business plan feature. Defer until first enterprise asks.
- **White-label Client portal.** Phase 8.
- **Multi-region / geo-distributed deployments.** Single-region Lagos/Frankfurt is fine for v1.
- **Self-serve refund UI for Clients.** Currently only super-admin can refund.
- **Custom domain UI for Clients.** Coolify's existing custom domain handles routing; the Client just needs a UI surface. Phase 6.2.5.
- **Real-time deployment log streaming for Clients.** Soketi already in stack, just needs Client-scoped subscription. Phase 6.2.6.

When you've got 50+ paying tenants and your hair is on fire, come back to this
section and pick the next one.

---

## §13. Sign-off

Before you announce publicly or run any marketing:

- [ ] §1, §2, §3, §4 fully done (no `<placeholder>` values left)
- [ ] §5 deploy smoke checks all pass
- [ ] §6 AGPL footer verified end-to-end
- [ ] §7 full operational drill A through G executed without manual fixes
- [ ] §8 lawyer has signed off (in writing, save the email)
- [ ] §9 backups + monitoring active for at least 7 days before launch
- [ ] First real customer is someone you know personally (canary tenant)
- [ ] You have a written incident runbook for the three scenarios in §11

When all 8 boxes check, you're cleared to take real money.

— End of checklist.
