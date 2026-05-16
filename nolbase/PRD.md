# Nolbase — Product Requirements Document

**Status:** Draft v2
**Owner:** putup9ja@gmail.com
**Last updated:** 2026-05-15
**Built on:** Fork of [Coolify](https://github.com/coollabsio/coolify) (AGPLv3)

> **What changed in v2:** Added the **reseller / sub-tenancy** pillar. Nolbase tenants (developers/agencies) can now sell hosting slices of their own server to their own end-clients, with Nolbase mediating the recurring payment via Paystack and taking a platform fee. Generic "Nolbase shared hosting" (multi-tenant on Nolbase-owned servers) is **removed** — replaced by the reseller model where the developer carries the ops burden on their own server.

---

## 1. Executive Summary

Nolbase is a **multi-tenant Platform-as-a-Service with a built-in reseller layer**. It lets developers and small teams deploy applications, databases, and services to **their own servers or cloud accounts** through a hosted control plane, without managing the underlying infrastructure orchestration themselves — and additionally lets them **resell hosting slices to their own end-clients** through Nolbase's marketplace billing.

It is a hosted offering built on top of an AGPL fork of Coolify. Customers sign up at `nolbase.io`, pick a plan, pay via **Paystack** (NGN), and start deploying. They can:
- Attach an existing server via SSH.
- Paste a cloud-provider API token (DO, Hetzner, Vultr) so Nolbase can provision VPS instances **on their cloud account**.
- Pay Nolbase for **fully-managed** dedicated infrastructure.
- **Resell** capacity on their own server to their own clients (Pro+ feature) — Nolbase mediates payment, takes a platform fee (default 10%), pays the developer the remainder.

**Why this exists:** Coolify is excellent self-hosted PaaS software but requires customers to install it themselves on a server they own. Nolbase removes that step — sign up, pay, deploy — while preserving the open-source ethos of letting customers eventually export everything and self-host if they want. **And** Nolbase turns the platform into a revenue tool for independent developers/agencies by letting them onboard, host, and bill their own clients without building a billing system or a control-plane themselves.

**Why now:** The African developer market (Nolbase's initial focus) needs Heroku-like ergonomics with local-currency billing and local support. Existing global PaaS providers don't accept Paystack and have poor Africa presence.

---

## 2. Background & Motivation

### 2.1 The problem

- **Self-hosted PaaS is too much work for most teams.** Installing Coolify, securing the host, maintaining the orchestrator, watching for updates — none of this is the developer's actual job.
- **Global PaaS providers don't accept local payment methods.** Heroku, Vercel, Render require USD cards; Paystack-native billing doesn't exist in this space.
- **"Hosted by us" + "but you can leave anytime" is rare.** Most managed platforms create vendor lock-in. Coolify's no-lock-in property is a real differentiator we want to preserve in the hosted product.

### 2.2 The opportunity

Combine:
- Coolify's deployment engine (Git-to-deploy, Docker support, database management, Traefik proxy, multi-resource per server).
- A hosted control plane (no installation step).
- Local payment rails (Paystack).
- A bring-your-own-cloud model so most tenants don't sit on Nolbase's balance sheet.

### 2.3 Competitive landscape

| Player | Position | How Nolbase differs |
|---|---|---|
| `app.coolify.io` (Coolify Cloud) | Same idea, USD-only, no Africa presence | NGN, Paystack, local support, BYO-cloud emphasis |
| Heroku / Render / Fly.io | Global PaaS, you don't own infra, USD | Tenant keeps cloud account ownership, NGN |
| Render | Closed managed PaaS | Open-source under the hood, no lock-in |
| Vercel / Netlify | Frontend-first, serverless | Backend & DB-first; full Docker support |

### 2.4 License foundation

Coolify is **AGPLv3**. Nolbase complies by:
- Maintaining a public source-available repository of the Nolbase fork.
- Linking to that repository from the Nolbase footer and `/legal/source` page.
- Contributing non-trade-secret bug fixes upstream where reasonable.

The AGPL choice is **load-bearing**: it means we cannot close-source the platform code. Proprietary integrations (Paystack billing logic, Nolbase admin tooling) are part of the same source-available repo — the AGPL doesn't allow a private fork running publicly.

---

## 3. Vision & Goals

### 3.1 Vision

> The fastest path from "I have an idea" to "it's running in production on infrastructure I own."

### 3.2 12-month goals

- **G1:** 500 paying tenants by month 12.
- **G2:** MRR of ₦7.5M by month 12 (avg ₦15k per tenant).
- **G3:** <10% monthly logo churn.
- **G4:** Net-zero infrastructure cost — i.e. Nolbase-managed hosting is profitable at the unit level (markup covers ops + support).
- **G5:** P99 deployment success rate >95% (excluding tenant-code errors).

### 3.3 Non-goals (out of scope for v1)

- Serverless / function hosting. Nolbase is container/VM-oriented.
- A marketplace of third-party add-ons / services beyond what Coolify already templates.
- Multi-region failover or geo-distributed deployments. Single-region per tenant.
- Custom domains as a first-party DNS service. We use Traefik (existing); tenant points DNS at their server.
- A mobile app. Web-only at launch.
- A free Nolbase-managed tier. Free plan is BYO-server only.

---

## 4. Users & Personas

### 4.1 Persona: Tunde — the solo developer

- Building a SaaS side-project in Lagos.
- Already has a Hetzner / DigitalOcean account or a cheap VPS.
- Wants to deploy a Laravel + Postgres + Redis stack without writing Docker Compose.
- Pays ₦15k/mo on his Pro card without thinking twice.

**Primary use:** BYO existing server, deploys 2-5 apps + 1-2 databases.

### 4.2 Persona: Adaeze — the agency lead (reseller)

- Runs a small dev agency serving Nigerian SMBs.
- Manages deployments for 8-10 client projects.
- Buys one solid DO droplet via Nolbase's referrer link and **resells slices to her clients**.
- Creates "₦10k/mo Starter," "₦25k/mo Business" offers; invites a client; client pays Adaeze via Nolbase; Nolbase takes 10%, transfers the rest to Adaeze monthly.
- Each client signs into Nolbase and sees **only their own project** — none of Adaeze's other clients.

**Primary use:** Pro plan, 1-2 servers (BYO cloud), 5-10 sub-clients.

### 4.3 Persona: Bola — the Developer's end-client

- Owns a small online store; needs her app + DB hosted.
- Doesn't know Docker, doesn't want to know.
- Was invited by Adaeze (her developer) via email link.
- Sees a stripped-down Nolbase: one project, deploy button, logs, env vars. No server management, no other projects, no team members beyond Adaeze.
- Pays Adaeze ₦10k/mo through Nolbase. Renewal is automatic.

**Primary use:** Client account (no Nolbase plan of her own — Adaeze covers the plan; Bola only pays the hosting offer).

### 4.4 Persona: Chinedu — the bootcamp graduate

- Just learned Laravel.
- Has never used Docker, doesn't want to.
- Wants the cheapest possible "click deploy" experience to ship his first portfolio app.

**Primary use:** Free plan, attaches a $5 DigitalOcean droplet (signed up via Nolbase's referral link), deploys 1 small app.

### 4.5 Persona: Tomi — the Nolbase support engineer

- Internal Nolbase staff.
- Needs to view any tenant's state, impersonate to debug, suspend abusers, refund payments.
- Should **never** be able to see/break their personal Nolbase tenant accidentally.

**Primary use:** Super-admin guard, separate login from any personal account.

---

## 5. Product Pillars

The product breaks into six pillars. Each maps to a phased build.

| # | Pillar | What it covers | Phase |
|---|---|---|---|
| P1 | **Identity & Tenancy** | Signup, login, teams, roles, invitations | 1 |
| P2 | **Billing** | Plans, Paystack subscriptions, trials, invoices, dunning | 1 |
| P3 | **Deployment Platform** | (Inherited from Coolify) Apps, DBs, services, Git deploys, proxy | 0 (already works) |
| P4 | **Server Attachment** | BYO server, BYO cloud, Nolbase-managed | 1 (BYO), 5 (BYO cloud), 7 (managed) |
| P5 | **Admin & Operations** | Super-admin panel, MRR, impersonation, referral code mgmt | 3 |
| P6 | **Quotas & Enforcement** | Plan limits enforced per-resource | 4 |
| P7 | **Reseller / Sub-tenancy** | Hosting offers, client invitations, marketplace billing, project-scoped client identity | 6 |

---

## 6. Key User Journeys

### 6.1 New tenant signup → first deploy (happy path)

1. Visitor lands on `nolbase.io/pricing`.
2. Clicks "Start Pro trial" → email/password registration.
3. ZeptoMail sends email-verification link. User verifies.
4. On first login: onboarding wizard.
   - **Step A:** Confirm team name (defaults to user's name).
   - **Step B:** Choose hosting:
     - "I already have a server" → SSH attach flow.
     - "I want to use my own cloud account" → cloud-provider picker → referral CTA (if applicable) → API token paste → Nolbase provisions a VPS on tenant's account.
     - "Nolbase manages it for me" → Nolbase provisions on Nolbase's Hetzner account, billed monthly on top of plan. (Phase 5.)
5. Server connection verified.
6. "Deploy your first app" CTA → Git URL → buildpack/Dockerfile detection → deploy.
7. App is live on a Traefik-served subdomain or tenant's custom domain.

Trial starts at step 2 (Pro features for 14 days, no card required). Card is requested only when (a) the trial ends and they want to keep Pro, or (b) they explicitly upgrade.

### 6.2 Trial ending → conversion (or downgrade)

- Day 11: ZeptoMail email "Your Pro trial ends in 3 days. Add a card to keep your servers."
- Day 14, 00:00 UTC: cron job runs `EndExpiredTrialsAction`.
  - If no payment method on file → downgrade to Free plan. Tenant is over-quota? Resources are NOT deleted but tenant cannot create new ones. UI shows "You're over your Free plan limit — upgrade or reduce."
  - If payment method on file → Paystack `subscription.create`, charged for first month.

### 6.3 Tenant brings DO account (referral flow)

1. Tenant adds a server → picks DigitalOcean.
2. Nolbase shows: "New to DigitalOcean? Sign up via our partner link to get $200 in credit." with link from `nolbase_settings.digitalocean_referral_url`.
3. After tenant signs up (or if already a DO user), they paste their **DO API token** into Nolbase.
4. Token is encrypted at rest (`cloud_provider_credentials.encrypted_token`).
5. Nolbase calls DO API to create a droplet (size/region picker, sensible defaults).
6. SSH key auto-injected via DO API.
7. Coolify SSH-connects → registers as a `Server` in tenant's team.

DO's referral program credits Nolbase one-time per new DO signup that spends $25. The credit doesn't recur. This is **not a revenue line** — it's a soft tailwind.

### 6.4 Developer resells a hosting slice to her own client

1. Adaeze (Developer, Pro plan) has a Hetzner droplet attached.
2. She opens **Hosting Offers** → **Create offer**.
3. She fills in:
   - Name: "Starter Hosting"
   - Server: her Hetzner droplet
   - RAM allocation: 512MB
   - Disk allocation: 5GB
   - Includes: 1 app, 1 small DB, custom domain, SSL
   - Price: ₦10,000 / month (or ₦100,000 / year)
   - Includes Nolbase platform fee (10% — shown transparently, deducted at payout)
4. She clicks **Invite client** → enters Bola's email + the project Bola will manage.
5. Nolbase sends Bola an email via ZeptoMail: "Adaeze invited you to host **Bola's Shop** on Nolbase. ₦10k/mo. [Accept invitation]"
6. Bola clicks → signs up (or logs in if she already has a Nolbase account) → reviews offer → pays first month via Paystack.
7. Paystack confirms payment → Nolbase keeps ₦1,000 (10% fee) → schedules ₦9,000 Paystack Transfer to Adaeze on the platform's payout cycle.
8. Bola is auto-added to a **sub-team under Adaeze's team** with the `client` role, scoped to one project.
9. Bola logs into Nolbase, sees only "Bola's Shop" — no other projects, no server detail, no team member list.
10. She deploys her code via Git or upload. Adaeze can also deploy on her behalf.
11. On day 30, Paystack auto-renews. If renewal fails → 7-day grace → app suspended → Adaeze and Bola both notified.

If Bola decides to leave: she can export her code/DB and disconnect; Adaeze keeps the server.
If Adaeze leaves Nolbase: all her client projects need a graceful handoff (see §15 Q9).

### 6.5 Nolbase staff suspends an abusive tenant

1. Tomi (support) logs in via `/nolbase/admin` using his super-admin credentials (separate from any personal account).
2. Finds tenant by email/team-name search.
3. Reviews recent activity / Paystack invoices / server usage.
4. Clicks "Suspend" → confirm modal → `Team.nolbase_status = suspended`.
5. Suspended teams:
   - Cannot log in (login throws "Account suspended — contact support@nolbase.io").
   - Their deployed apps **keep running** (we don't punish their end-users).
   - Their Paystack subscription is **not** auto-cancelled — that's a manual decision.

### 6.6 Tenant exports / leaves Nolbase

By design (AGPL spirit + Coolify ethos), tenants can take their servers with them.

1. Tenant clicks "Export & leave" in settings.
2. Nolbase generates a downloadable JSON bundle of:
   - All apps + env vars + deployment configs.
   - DB credentials (already on the tenant's server).
   - SSH key fingerprints (key itself stays on tenant's server).
3. Tenant disconnects their server from Nolbase.
4. Server keeps running their apps unchanged. They can self-host Coolify on the same server and point it at the existing deployments, or use raw Docker.

We will **not** delete their server-side data when they leave. That would be a customer-hostile act.

---

## 7. Functional Requirements

### 7.1 Identity & Tenancy (P1)

- **FR-7.1.1** Public email/password registration. Email verification required before first deploy.
- **FR-7.1.2** OAuth: Google, GitHub at launch. (Builds on existing Socialite integration.)
- **FR-7.1.3** A new registration auto-creates one `Team` for the user; they are its `OWNER`.
- **FR-7.1.4** Team owners can invite members by email. Invitees set `MEMBER` or `ADMIN` role.
- **FR-7.1.5** Multi-team support: a user can belong to multiple teams (already supported in Coolify).
- **FR-7.1.6** Password reset via ZeptoMail.
- **FR-7.1.7** 2FA optional (TOTP). Builds on existing Fortify 2FA.

### 7.2 Billing (P2)

- **FR-7.2.1** Three plans at launch: Free, Pro, Business. (Pricing in §11.)
- **FR-7.2.2** Plans defined in DB (`plans` table) — editable via super-admin without deploy.
- **FR-7.2.3** Paystack subscription per team (one active subscription per team max).
- **FR-7.2.4** New signups receive 14-day Pro trial automatically. No card required.
- **FR-7.2.5** Trial end → if no card on file, downgrade to Free; else, charge.
- **FR-7.2.6** Tenant can upgrade/downgrade plan mid-cycle. Pro-rated charges/credits via Paystack.
- **FR-7.2.7** Failed renewal (Paystack `invoice.payment_failed`):
  - Day 0: retry once.
  - Day 3: email reminder.
  - Day 7: email reminder + grace.
  - Day 14: downgrade to Free (don't suspend resources).
- **FR-7.2.8** All invoice events from Paystack are stored locally for audit (`paystack_events` table).
- **FR-7.2.9** Tenant can download invoice PDFs from Paystack via deep link or by fetching from API.
- **FR-7.2.10** No Stripe. All references to existing Stripe code removed.

### 7.3 Deployment Platform (P3 — inherited)

This pillar is **already implemented by Coolify**. Nolbase does not modify the core deployment engine in v1. Inherited capabilities:

- Deploy apps from Git (GitHub/GitLab/Bitbucket public or private with PAT/App).
- Buildpack auto-detection (Nixpacks, Dockerfile, static).
- Container management via Docker.
- Standalone databases (Postgres, MySQL, MariaDB, MongoDB, Redis, ClickHouse, KeyDB, Dragonfly).
- Service templates (~200 pre-configured stacks).
- Traefik reverse proxy with auto-TLS via Let's Encrypt.
- Real-time deployment logs via Soketi.
- Preview environments per PR.
- Backups, scheduled tasks, environment variables.

**What we explicitly do NOT touch in v1:** the deployment job (`ApplicationDeploymentJob`), the proxy config, the buildpack flow, the service templates.

### 7.4 Server Attachment (P4)

- **FR-7.4.1** "BYO existing server": IP / port / SSH key paste, validation via SSH connect (existing Coolify flow, exposed in onboarding).
- **FR-7.4.2** "BYO cloud account":
  - Provider picker: DigitalOcean, Hetzner, Vultr at launch.
  - Pre-token CTA: "New to {provider}? Sign up via our partner link" (link from `nolbase_settings`).
  - API token paste (encrypted at rest).
  - Region / size picker with sensible defaults (cheapest "Nolbase-recommended" preselected).
  - Provision a VPS on the tenant's cloud account.
  - SSH key auto-injected via cloud API.
  - Coolify SSH-attaches → registers as `Server` row with `provisioning_source = byo_cloud`.
- **FR-7.4.3** "Nolbase-managed" (Phase 5): Nolbase uses its own Hetzner account to spin a VPS, marks the cost up, bills tenant on Paystack invoice. `provisioning_source = nolbase_managed`. Tenant cannot SSH directly (root access stays with Nolbase).
- **FR-7.4.4** Server health monitoring (inherited from Coolify).
- **FR-7.4.5** Server detach: tenant can remove a server from Nolbase. For BYO, the server keeps running their apps. For Nolbase-managed, the VPS is destroyed and final-billed.

### 7.5 Admin & Operations (P5)

- **FR-7.5.1** Separate `nolbase_admins` table. Distinct guard (`nolbase`), distinct login route (`/nolbase/admin/login`), distinct session.
- **FR-7.5.2** Three super-admin roles: `support` (read + impersonate), `staff` (write, refund, suspend), `superadmin` (manage other admins).
- **FR-7.5.3** Tenant list: search by team name, owner email, Paystack customer code.
- **FR-7.5.4** Tenant detail: subscription status, MRR contribution, servers, apps count, last login, billing history.
- **FR-7.5.5** Impersonation: super-admin clicks "View as tenant" → opens a read-only view of tenant's dashboard. All actions audit-logged. Tenant is notified by email after the fact.
- **FR-7.5.6** Suspend / un-suspend tenant.
- **FR-7.5.7** Refund (initiates Paystack refund + posts internal note).
- **FR-7.5.8** Referral code management: edit `nolbase_settings` rows for DO, Hetzner, Vultr referral URLs/codes.
- **FR-7.5.9** Audit log: every super-admin action recorded (`nolbase_admin_audit` table). Immutable from app — only DB superuser can delete.
- **FR-7.5.10** MRR dashboard: today / 30d / 90d MRR, churn, plan distribution, new signups.

### 7.6 Quotas & Enforcement (P6)

- **FR-7.6.1** Plan defines limits: max servers, max apps, max team members, max DB instances.
- **FR-7.6.2** Quota check happens at the policy/gate layer (`createAnyResource`, extended).
- **FR-7.6.3** Over-quota teams (after plan downgrade): cannot create new resources but existing resources are **not** auto-deleted.
- **FR-7.6.4** Upgrade CTA shown inline whenever quota blocks an action.

### 7.7 Reseller / Sub-tenancy (P7)

- **FR-7.7.1** Reseller features are gated to **Pro and Business** plans. Free tenants cannot create hosting offers.
- **FR-7.7.2** A Developer can create one or more **hosting offers** on any server they own (BYO or BYO-cloud). Each offer specifies: server, RAM cap, disk cap, included resources (apps/DB count), price (monthly/annual), currency (NGN), and a description.
- **FR-7.7.3** A Developer can mark an offer **public** (listed in a future marketplace catalogue) or **invite-only** (default; not listed anywhere, only reachable via invitation link). v1 is invite-only across the board; public marketplace listings are deferred.
- **FR-7.7.4** A Developer invites a Client by entering the Client's email + selecting an offer + naming the project the Client will manage. Nolbase generates a signed invitation link and emails it via ZeptoMail.
- **FR-7.7.5** The invitation link expires in 7 days. Expired invitations can be re-sent (resets the 7-day timer).
- **FR-7.7.6** When the Client clicks the invitation link:
  - If the email matches an existing Nolbase user: log them in (or prompt for password) and show the offer accept screen.
  - If new: sign-up flow scoped to this invitation (no plan picker — they're a Client, not a Developer).
- **FR-7.7.7** On accept, the Client is shown the offer details + Paystack checkout for the first period (month or year). No card-stored, just a one-shot charge that establishes the subscription.
- **FR-7.7.8** On successful payment:
  - Nolbase creates a **`SubTeam`** under the Developer's team (a new entity).
  - The Client is added with the `client` role to the SubTeam.
  - A new `Project` is created in the SubTeam scoped to the Developer's specified server.
  - A `ClientSubscription` row is created with the Paystack subscription code, status `active`, and the configured renewal period.
- **FR-7.7.9** Nolbase splits the payment: keeps the marketplace fee (default 10%, configurable via `nolbase_settings.marketplace_fee_pct`), schedules a Paystack Transfer to the Developer's payout account for the remainder. Payouts run on a fixed cadence (e.g., weekly Mondays) to batch and reduce Paystack transfer fees.
- **FR-7.7.10** The Developer must have a verified payout account (Paystack-supported bank) before any payout is released. Until verified, fees accrue in a `pending_payouts` balance visible in their dashboard.
- **FR-7.7.11** Client identity & scoping:
  - The `client` role sees **only the projects in their SubTeam**.
  - They cannot view server settings, server logs, other projects, team members beyond themselves and the Developer (as their "host").
  - They can: deploy/redeploy their app, view its logs, manage env vars for their project, manage custom domains for their project, see their own billing history.
  - They cannot: change RAM/disk caps, add new resources beyond the offer's allowance, see the underlying server's IP or other tenants on the same server.
- **FR-7.7.12** Resource enforcement on a shared (resold) server:
  - Apps deployed by a Client are launched with Docker `--memory` and `--cpus` limits derived from the offer's RAM allocation.
  - Disk usage is monitored; soft-warn at 80%, hard-block new writes at 100%. (Initial v1 may use simple periodic check; cgroup v2 disk quotas are a v2 improvement.)
  - Outbound bandwidth is not metered in v1.
- **FR-7.7.13** Renewal flow: Paystack subscription auto-charges on renewal date. On success → continue. On failure → 7-day grace, ZeptoMail reminders at day 0, 3, 6, suspend at day 7. Suspension stops the Client's containers but does **not** delete data for 30 days.
- **FR-7.7.14** Cancellation: Client can cancel at any time. Service runs until the end of the current paid period. After period end, suspended; after additional 30 days, data is destroyed (with email warning at day 21).
- **FR-7.7.15** Developer-initiated termination: Developer can revoke a Client's access at any time. Refund-on-revoke is the Developer's responsibility (Nolbase shows a "Refund this client" button that triggers a Paystack refund; Nolbase's platform fee is also refunded pro-rata).
- **FR-7.7.16** Custom domain support is available **for Clients on all plans** (per the locked decision). Client adds their domain, points DNS to the Developer's server IP, Traefik issues TLS via Let's Encrypt.
- **FR-7.7.17** All money flows are logged in `marketplace_transactions` (one row per charge, refund, payout): Paystack reference, amount, fee, net, currency, status.
- **FR-7.7.18** Both Developer and Client see itemized billing history. Tax invoicing (PDF generation with VAT/TIN fields) is v2.
- **FR-7.7.19** Nolbase super-admin can suspend a Developer's marketplace activity (stop new offers, freeze payouts, do not affect existing Client subscriptions) — used in fraud investigations.
- **FR-7.7.20** White-label (Developer's logo/colors/custom domain for the control plane Clients see) is **explicitly NOT in v1**. Listed in Phase 6+ ideas.

---

## 8. Non-Functional Requirements

### 8.1 Performance

- **NFR-8.1.1** P95 page load (control plane) <800ms on 3G.
- **NFR-8.1.2** Deploy job pickup latency <30s from queue push.
- **NFR-8.1.3** Live deployment logs <1s end-to-end via Soketi (current Coolify baseline).

### 8.2 Reliability

- **NFR-8.2.1** Control plane SLA: 99.5% (paid plans). 99% (free).
- **NFR-8.2.2** Deployments to tenant servers continue working even if Nolbase control plane is briefly down (servers are not dependent on Nolbase for runtime).
- **NFR-8.2.3** Database backups: daily, retained 30 days. Restore tested monthly.

### 8.3 Security

- **NFR-8.3.1** All tenant cloud credentials encrypted at rest with Laravel's `Crypt` facade.
- **NFR-8.3.2** All SSH private keys encrypted at rest.
- **NFR-8.3.3** Paystack webhook signature verification.
- **NFR-8.3.4** Rate limiting on auth endpoints (existing Fortify config).
- **NFR-8.3.5** Tenant data fully isolated: no cross-team data leakage. Policy-tested.
- **NFR-8.3.6** 2FA enforced for super-admins.
- **NFR-8.3.7** Audit log for super-admin actions (FR-7.5.9).
- **NFR-8.3.8** Annual penetration test once revenue justifies it (~year 2).

### 8.4 Compliance

- **NFR-8.4.1** AGPLv3 source-available repo, footer link, `/legal/source` page.
- **NFR-8.4.2** Privacy policy + ToS pages.
- **NFR-8.4.3** PCI: we don't store card data — Paystack handles. Vault all sensitive references.
- **NFR-8.4.4** Data residency: Nolbase-managed servers default to a region close to Nigeria (Hetzner Falkenstein DE — best latency available within Hetzner's footprint at launch).

### 8.5 Localization

- **NFR-8.5.1** English only at launch.
- **NFR-8.5.2** Currency: NGN only at launch. (USD support is a v2 question.)
- **NFR-8.5.3** Timezone: Africa/Lagos default for displays; UTC in DB.

---

## 9. Technical Architecture

### 9.1 Stack inherited from Coolify

- PHP 8.4 / Laravel 12 (Laravel 10 file structure)
- Livewire 3 + Alpine.js + Tailwind v4
- PostgreSQL 15
- Redis (queues + cache) + Horizon
- Soketi (WebSockets)
- Traefik (per-tenant-server, not central)
- Docker for app containers

### 9.2 New components added for Nolbase

| Component | Purpose |
|---|---|
| `app/Actions/Paystack/` | Subscription init, verify, webhook handling, refunds |
| `app/Services/PaystackService.php` | Paystack API wrapper |
| `app/Services/CloudProviders/` | DO, Hetzner, Vultr API wrappers (one class each) |
| `app/Actions/Provisioning/` | `ProvisionByoCloudServer`, `ProvisionNolbaseManagedServer` |
| `app/Models/Plan.php` | DB-backed plan definitions |
| `app/Models/Subscription.php` | Per-team Paystack subscription state |
| `app/Models/CloudProviderCredential.php` | Per-team encrypted cloud tokens |
| `app/Models/NolbaseAdmin.php` | Super-admin identity (separate from `User`) |
| `app/Models/NolbaseSetting.php` | Admin-managed key/value (referral URLs, marketplace fee %) |
| `app/Models/NolbaseAdminAudit.php` | Immutable audit log |
| `app/Models/HostingOffer.php` | Developer's reseller offer (server, RAM, disk, price) |
| `app/Models/ClientInvitation.php` | Developer-issued invitation tied to an offer |
| `app/Models/SubTeam.php` | Child team scoped under a parent Developer's team |
| `app/Models/ClientSubscription.php` | Recurring Paystack subscription paid by a Client to a Developer |
| `app/Models/MarketplaceTransaction.php` | Charge / refund / payout ledger row |
| `app/Models/PayoutAccount.php` | Developer's verified Paystack payout bank |
| `app/Actions/Marketplace/` | `CreateHostingOffer`, `SendClientInvitation`, `AcceptInvitation`, `SplitMarketplacePayment`, `RunPayoutBatch`, `SuspendClientSubscription` |
| `app/Livewire/Nolbase/Admin/` | Super-admin UI |
| `app/Livewire/Developer/Marketplace/` | Hosting offers, client list, payout dashboard |
| `app/Livewire/Client/` | Stripped-down Client dashboard (single-project scope) |
| `app/Livewire/Public/Pricing.php` | Public pricing page |
| `app/Livewire/Public/Register.php` | Plan-aware registration |
| `app/Http/Middleware/EnforcePlanQuota.php` | Quota gate |
| `app/Http/Middleware/RequireNolbaseAdmin.php` | Super-admin guard |
| `app/Http/Middleware/ScopeClientToProject.php` | Forces `client` role into single-project view |
| `app/Enums/Role.php` (extended) | Add `CLIENT` rank below `MEMBER` |
| `config/paystack.php`, `config/cloud_providers.php`, `config/marketplace.php` | Provider config |
| Updated `config/mail.php` | ZeptoMail SMTP |

### 9.3 Removed / replaced

| What | Why |
|---|---|
| `app/Actions/Stripe/` | Replaced with Paystack |
| Stripe config keys in `.env.example` | No Stripe in Nolbase v1 |
| Coolify-branded marketing pages | Replaced with Nolbase branding |

### 9.4 High-level deployment topology

```
                   Public DNS (nolbase.io)
                          │
                  ┌───────┴────────┐
                  │  Control plane │
                  │  (Nolbase app) │
                  │  Fortify auth  │
                  │  Livewire UI   │
                  │  Paystack API  │
                  └───────┬────────┘
                          │ SSH (per-tenant key)
              ┌───────────┼───────────┬─────────────┐
              ▼           ▼           ▼             ▼
       Tunde's VPS  Adaeze's DO  Nolbase-mgd     Free tier
       (BYO own)    (BYO cloud)  Hetzner VPS    BYO Pi/VPS
       Traefik      Traefik       Traefik        Traefik
       App+DB       Apps+DBs      Apps+DBs       App
```

The control plane never proxies tenant traffic. Tenant apps are reached at their server's IP / DNS, with Traefik on that server handling routing. Nolbase only orchestrates.

---

## 10. Data Model

Tables are listed by area. **Bold** = new; *italic* = extended existing Coolify table.

### 10.1 Identity & tenancy

```
users                       (existing)
*teams                      add: nolbase_status enum
                                 (active|suspended|closed)
team_user                   (existing — role join table)
**nolbase_admins**          id, user_id (nullable), email, password_hash,
                            role (support|staff|superadmin),
                            mfa_secret, last_login_at
```

### 10.2 Billing

```
**plans**                   id, code (free|pro|business),
                            name, price_ngn, paystack_plan_code,
                            max_servers, max_apps, max_team_members,
                            max_databases, features jsonb,
                            is_public bool, sort_order

**subscriptions**           id, team_id (unique), plan_id,
                            paystack_subscription_code,
                            paystack_customer_code, status,
                            trial_ends_at, current_period_end,
                            cancel_at_period_end bool

**paystack_events**         id, event_type, paystack_id, payload jsonb,
                            processed_at, error
```

### 10.3 Hosting

```
**cloud_provider_credentials**
                            id, team_id, provider (do|hetzner|vultr),
                            encrypted_token, label, last_used_at

*servers                    add: provisioning_source enum
                                 (manual|byo_cloud|nolbase_managed),
                                 cloud_provider nullable,
                                 cloud_resource_id nullable,
                                 cloud_credential_id nullable FK,
                                 monthly_cost_ngn nullable

**nolbase_managed_servers** id, server_id (unique), our_hetzner_id,
                            cost_basis_ngn, markup_pct,
                            billing_status, suspended_at
```

### 10.4 Reseller / sub-tenancy

```
**hosting_offers**          id, team_id (Developer's team), server_id,
                            name, description,
                            ram_mb, disk_gb, max_apps, max_databases,
                            allow_custom_domain bool,
                            price_ngn_monthly, price_ngn_annual nullable,
                            is_public bool (default false),
                            is_active bool

**client_invitations**      id, hosting_offer_id, developer_team_id,
                            email, project_name,
                            invitation_token (uniq, signed),
                            sent_at, expires_at, accepted_at nullable,
                            cancelled_at nullable

**sub_teams**               id, parent_team_id (Developer's team),
                            client_user_id (the Client's user),
                            project_id (the one project they see),
                            hosting_offer_id (snapshot at creation),
                            created_at, terminated_at nullable

**client_subscriptions**    id, sub_team_id, hosting_offer_id,
                            paystack_subscription_code,
                            paystack_customer_code, status,
                            period (monthly|annual),
                            current_period_start, current_period_end,
                            cancel_at_period_end bool,
                            suspended_at nullable, suspend_reason

**marketplace_transactions**id, type (charge|refund|payout|fee),
                            client_subscription_id nullable,
                            developer_team_id, client_user_id,
                            amount_ngn, fee_ngn, net_ngn,
                            paystack_reference, status,
                            occurred_at, payload jsonb

**payout_accounts**         id, team_id (Developer's), bank_code,
                            account_number_encrypted, account_name,
                            paystack_recipient_code,
                            verified_at nullable
```

### 10.5 Admin & ops

```
**nolbase_settings**        key (pk), value, updated_by_admin_id,
                            updated_at
                            
                            seeded keys:
                            - digitalocean_referral_url
                            - hetzner_referral_code
                            - vultr_referral_code
                            - support_email
                            - announcement_banner

**nolbase_admin_audit**     id, admin_id, action, target_type,
                            target_id, payload jsonb, ip,
                            created_at (no updated_at; immutable)
```

### 10.6 Multi-tenancy scoping

**All existing Coolify tables that have a `team_id` continue to be scoped by team.** No table cross-references teams. Tenant isolation is enforced by:
- Eloquent global scopes (existing pattern).
- Policy classes (existing pattern, extended for plan quotas).
- The forthcoming `EnforcePlanQuota` middleware.
- The `ScopeClientToProject` middleware for `client`-role users.

Super-admin queries explicitly bypass scopes via a `NolbaseAdminScope::withoutTenancy()` helper, never by raw SQL.

**Sub-team scoping note:** A `SubTeam` is a real `Team` row in the existing table (re-used for code-path consistency) with a `parent_team_id` foreign key. The parent Developer can read/write into the SubTeam (acts as a host). The Client (a User with `client` role on that SubTeam) cannot navigate up to the parent. The server the SubTeam's project lives on is **owned by the parent team**, never by the SubTeam — this is the critical invariant that prevents a Client from accidentally claiming the underlying server.

---

## 11. Pricing & Plans

All prices in NGN. Subject to revision before launch based on Paystack fee analysis.

| | **Free** | **Pro** | **Business** |
|---|---|---|---|
| Price (monthly) | ₦0 | ₦15,000 | ₦50,000 |
| Price (annual, 2 months free) | — | ₦150,000 | ₦500,000 |
| Servers | 1 | 5 | Unlimited |
| Apps | 3 | Unlimited | Unlimited |
| Databases | 1 | Unlimited | Unlimited |
| Team members | 1 | 5 | Unlimited |
| BYO server | ✅ | ✅ | ✅ |
| BYO cloud account | ❌ | ✅ (Phase 5) | ✅ (Phase 5) |
| Nolbase-managed hosting | ❌ | ✅ (Phase 7) | ✅ (Phase 7) |
| **Resell hosting to your own clients** | ❌ | ✅ up to 5 clients (Phase 6) | ✅ unlimited clients (Phase 6) |
| Preview environments | ❌ | ✅ | ✅ |
| Backup retention | 7 days | 30 days | 90 days |
| Email support | ❌ | ✅ (48h) | ✅ (24h) |
| Priority queue (deploys) | ❌ | ❌ | ✅ |
| SSO (Google Workspace SAML) | ❌ | ❌ | ✅ (Phase 8+) |
| White-label client portal | ❌ | ❌ | ✅ (Phase 8+) |
| Audit log export | ❌ | ❌ | ✅ |

**Trial:** All new signups get 14 days of Pro features. No card required upfront.

**Annual:** 2 months free if paid annually. Only Pro+ eligible.

**Nolbase-managed surcharge:** Phase 7 will add line items per managed server: cost basis (e.g., €5/mo Hetzner = ~₦8.5k) + Nolbase markup (e.g., 50%) = ~₦12.5k/server/mo. Billed alongside the plan via Paystack.

**Marketplace platform fee (reseller, Phase 6):** When a Client pays a Developer for a hosting offer, Nolbase takes a **10% platform fee** of the gross charge before remitting the remainder to the Developer's verified payout account. The fee percentage is stored in `nolbase_settings.marketplace_fee_pct` and is adjustable by super-admins. Examples on a ₦10,000/mo Client charge:
- Gross charged to Client: ₦10,000
- Nolbase platform fee (10%): ₦1,000
- Paystack fee (deducted from gross by Paystack, ~1.5% capped at ₦2,000): ~₦150
- **Developer payout: ~₦8,850**

Paystack fees are passed through transparently — Nolbase does **not** absorb them. The fee model is shown in the offer-creation UI so Developers can price accordingly.

**Future pricing levers (deferred):**
- Variable platform fee (e.g., 8% for high-volume Developers).
- Yearly platform fee discount for annual-billing clients.
- Custom enterprise plans.

---

## 12. License & Compliance

### 12.1 AGPLv3 commitments

- **Source-available:** Nolbase's full source code is published at `github.com/<org>/nolbase` (or similar). Updated on each production release.
- **Footer link:** "Source code" link on every page → public repo.
- **`/legal/source`:** dedicated page explaining AGPL, with a clear "Get the source for the running version" link to the exact commit deployed.
- **Modification disclosure:** any non-trivial change Nolbase makes is part of the public repo. We do **not** maintain a private patch set.
- **Upstream contributions:** non-strategic bug fixes get PR'd to Coolify upstream as a courtesy.

### 12.2 What AGPL does NOT prevent

- Charging money for the hosted service. (Source-available ≠ free-of-charge.)
- Closed proprietary documentation, marketing, brand assets.
- Trademark on "Nolbase" name and logo. (Trademark is separate from copyright.)

### 12.3 Tenant data ownership

Tenants own their:
- Server-side data (it's literally on their server).
- Database contents.
- Code repositories (their Git provider holds these).
- Account information (we provide an export).

Nolbase owns:
- Aggregated, anonymized usage data (for product analytics).
- Billing records (legal retention).

Tenant can export and leave at any time (see §6.5).

---

## 13. Phased Roadmap

### Phase 0 — Foundation (DONE / IN PROGRESS)

- ✅ Coolify dev environment running locally
- ✅ Nolbase brand palette (navy / electric blue / signal teal)
- ✅ Nolbase logo + favicon
- ⏳ AGPL source-available footer + `/legal/source` page

### Phase 1 — Hosted SaaS MVP (~2-3 weeks)

**Identity & Billing slice.** Tenants can register, pay, deploy on BYO server.

- [ ] Rip out `app/Actions/Stripe/` and references
- [ ] `plans` and `subscriptions` migrations + models + factories
- [ ] Paystack integration (init, verify, webhooks, refunds)
- [ ] ZeptoMail SMTP in `config/mail.php`
- [ ] Public registration page + plan picker
- [ ] 14-day Pro trial logic + cron downgrade job
- [ ] Email verification flow on ZeptoMail
- [ ] Tenant billing page (current plan, upgrade/downgrade, invoices)
- [ ] AGPL footer + `/legal/source`
- [ ] Pest tests for billing happy paths + dunning

**Exit criteria:** A real human (not Nolbase team) can sign up at `nolbase.io`, pay ₦15k via Paystack, attach a Hetzner server they already own, deploy a Laravel app.

### Phase 2 — Quotas & Polish (~1 week)

- [ ] `plans` quotas enforced via `EnforcePlanQuota` middleware
- [ ] Upgrade CTAs at quota walls
- [ ] Marketing pages (landing, pricing, docs link, contact)
- [ ] Onboarding wizard
- [ ] Status page (uptime.nolbase.io via Better Stack or self-hosted)

### Phase 3 — Super-admin (~2 weeks)

- [ ] `nolbase_admins` + separate guard + login route
- [ ] Tenant list + detail + impersonation
- [ ] MRR dashboard
- [ ] Suspend / refund flows
- [ ] Referral code management UI
- [ ] Audit log table + viewer

### Phase 5 — BYO Cloud (~3 weeks)

- [ ] DO API wrapper + droplet provisioning
- [ ] Hetzner API wrapper (extends existing `HetznerService` if compatible)
- [ ] Vultr API wrapper
- [ ] Cloud credential storage (encrypted, per-team)
- [ ] Onboarding flow updated with cloud-picker
- [ ] Referral CTA wired to `nolbase_settings`
- [ ] Cost preview before provisioning

### Phase 6 — Reseller / Marketplace (~4-5 weeks)

The biggest phase. Adds the entire P7 pillar.

- [ ] `hosting_offers` model + CRUD UI for Developer
- [ ] `client_invitations` flow (create, send via ZeptoMail, signed link, accept)
- [ ] `sub_teams` model + `client` role + `ScopeClientToProject` middleware
- [ ] Client-scoped Livewire dashboard (single-project view, stripped of server/team navigation)
- [ ] `client_subscriptions` model + Paystack subscription lifecycle
- [ ] Marketplace payment split (`SplitMarketplacePayment` action) + 10% fee
- [ ] Developer payout dashboard (`pending_payouts` balance, transaction history)
- [ ] `payout_accounts` model + Paystack bank verification flow
- [ ] Weekly `RunPayoutBatch` job creating Paystack Transfers to verified Developers
- [ ] Resource limits (Docker memory/cpu caps) enforced on Client-deployed apps
- [ ] Disk usage monitor with soft/hard thresholds
- [ ] Custom domain flow for Client projects (Traefik + Let's Encrypt — leverages existing Coolify)
- [ ] Client-suspend, Developer-revoke flows + refund logic
- [ ] Super-admin: marketplace transaction viewer, suspend Developer marketplace activity
- [ ] Pest tests: invitation accept happy path, payment split correctness, scope leakage tests (Client cannot see Developer's other projects)

**Exit criteria:** Adaeze (a real Developer) can create an offer, invite Bola, Bola pays ₦10k via Paystack, Bola sees only her project, Nolbase has ₦1,000 fee recorded, weekly payout of ₦8,850 lands in Adaeze's bank.

### Phase 7 — Nolbase-managed hosting (~4 weeks)

- [ ] Nolbase Hetzner account integration
- [ ] `nolbase_managed_servers` model + lifecycle
- [ ] Cost markup logic + Paystack invoice line items
- [ ] Monthly usage rollup → Paystack invoice
- [ ] Decommission flow (destroy VPS on detach)
- [ ] Refund logic for partial-month VPS

### Phase 8 — Enterprise polish (post-revenue)

- [ ] SSO (Google Workspace SAML)
- [ ] Audit log export
- [ ] Priority deploy queue
- [ ] Usage-based overages
- [ ] Webhook API for tenant integrations
- [ ] **White-label client portal** (Developer's logo/colors/custom-domain control plane for their Clients)
- [ ] Public marketplace catalogue (Developers can list public offers)

---

## 14. Success Metrics

| Metric | Target by month 6 | Target by month 12 |
|---|---|---|
| Paying tenants | 100 | 500 |
| MRR | ₦1.5M | ₦7.5M |
| Trial → paid conversion | 15% | 25% |
| Monthly logo churn | <15% | <10% |
| Deployment success rate (excluding tenant code errors) | >90% | >95% |
| Time from signup to first deploy (median) | <30 min | <10 min |
| Support response time (Pro) | <72h | <48h |

---

## 15. Risks & Open Questions

### 15.1 Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Coolify upstream makes breaking changes that conflict with Nolbase fork | Medium | High | Pin upstream version; selectively merge; maintain test suite |
| Paystack rate limits during high-volume signups | Low | Medium | Idempotency keys, queue subscriber events |
| ZeptoMail deliverability to Nigerian ISPs | Medium | Medium | SPF/DKIM/DMARC setup; warm sender domain; monitor bounce |
| AGPL public repo reveals proprietary tactics (pricing logic etc.) | Medium | Low-Med | Accepted tradeoff; competitive moat is execution and brand, not code |
| Nolbase-managed cost basis goes underwater (Hetzner price hike) | Medium | High | Monthly cost review; pass-through on contract renewal |
| Multi-tenant data leak | Low | Catastrophic | Policy tests; pen test; bug bounty by year 2 |
| Cloud-provider API token mishandling (logging, etc.) | Medium | High | Encrypted at rest, never logged, redacted in error reports |
| Developer abandons Nolbase mid-period; Clients still paying | Medium | High | 30-day grace; auto-refund unused Client subscriptions; offer Clients self-host export |
| Developer mistreats Clients (over-allocates, doesn't deliver) | Medium | Medium | Client can file dispute → super-admin reviews → can force-refund + suspend Developer |
| Marketplace fee revenue triggers tax/regulatory obligation | Medium | High | Consult Nigerian tax counsel before Phase 6 launch; may require formal payment-aggregator registration with NIBSS/CBN |
| Resource limits not enforced cleanly on shared (resold) server → noisy neighbor | High | Medium | Docker memory/cpu caps + monitoring; lean on Coolify's existing per-container limits; alert Developer if a Client app hits limit repeatedly |
| Client compromise (their app gets hacked) taints Developer's server | Medium | High | Per-container isolation is Docker's default; document the risk; recommend Developers segment by sensitivity |

### 15.2 Open questions (need answers before relevant phase)

- **Q1 (Phase 1):** Annual billing — Paystack supports it natively or do we charge once and grant 12 months?
- **Q2 (Phase 1):** Email-verification grace — can a user deploy before verifying? Lean: no, but allow exploring the dashboard.
- **Q3 (Phase 3):** Should super-admin impersonation be a true session-swap or a read-only "view-as" mode? Read-only is safer; recommend that.
- **Q4 (Phase 5):** Linode / AWS Lightsail support? Likely v2.
- **Q5 (Phase 6):** Payout cadence — weekly Mondays, twice a month (1st + 15th), or end-of-month? Trade-off: more frequent = better cash flow for Developer, more Paystack transfer fees. Lean: weekly Mondays, minimum threshold ₦5,000 to batch out small balances.
- **Q6 (Phase 6):** VAT/tax handling on marketplace transactions — does Nolbase collect VAT on behalf of the Developer or does the Developer self-report? Likely requires Nigerian tax counsel; if Nolbase collects, add VAT line to every charge.
- **Q7 (Phase 6):** Can a Client become a Developer (graduate to a paying Nolbase plan and start reselling themselves)? Lean: yes, frictionless upgrade — preserves the open ethos.
- **Q8 (Phase 6):** Should a Developer be allowed to **delete a Client's account** entirely, or only revoke access? Lean: only revoke; Client account survives so they can dispute/recover.
- **Q9 (Phase 6):** **Developer disappears scenario** — Developer cancels their Nolbase plan or their card stops working. What happens to their Clients? Options:
  - Power-down the server (Clients' apps go offline).
  - Auto-refund Clients pro-rata for the unused subscription, offer them export.
  - Try to charge Developer's payout-account balance to cover the gap.
  Lean: 14-day grace → email both Developer and Clients → auto-refund and offer export at day 14.
- **Q10 (Phase 7):** Auto-scale a Nolbase-managed server vertically when app maxes out CPU, or require manual upgrade? Likely manual at launch.
- **Q11 (Phase 7):** What happens to a Nolbase-managed server if Paystack subscription lapses? Lean: 14-day grace, then power off (don't destroy), then destroy after 30 more days.
- **Q12 (general):** Custom-domain DNS — first-party DNS like Vercel, or always "you point your DNS at your server's IP"? Lean: the latter at launch.

---

## 16. Glossary

| Term | Meaning |
|---|---|
| **Tenant** | A customer organization. One `Team` in the DB. |
| **Developer** | A Nolbase Tenant on Pro+ who resells hosting to their own clients (Phase 6+). |
| **Client** | A user invited by a Developer; sees only their assigned project. Lives in a SubTeam. |
| **Owner / Admin / Member** | Roles within a Team. Coolify's existing `Role` enum (rank-ordered). |
| **Client role** | New role below Member, single-project scoped. |
| **Super-admin** | Nolbase staff. Separate identity in `nolbase_admins`. |
| **BYO server** | Bring-your-own-server — tenant attaches an existing host via SSH. |
| **BYO cloud** | Tenant gives Nolbase their cloud-provider API token; Nolbase provisions on their account. |
| **Nolbase-managed** | Nolbase provisions on its own account, marks up, bills tenant. |
| **Hosting offer** | A Developer-defined package (RAM, disk, price) sold to Clients. |
| **SubTeam** | A child team scoped under a Developer's team; holds a Client and their project. |
| **Marketplace fee** | Nolbase's cut of Client-to-Developer payments. Default 10%. |
| **Payout** | A Paystack Transfer from Nolbase to a Developer's verified bank account. |
| **Control plane** | The Nolbase web app at nolbase.io — orchestrates but does not proxy tenant traffic. |
| **Plan** | A pricing tier paid by the Tenant to Nolbase (Free, Pro, Business). |
| **Subscription** | A Tenant's Paystack subscription to Nolbase (plan billing). |
| **Client subscription** | A Client's Paystack subscription paying a Developer for a hosting offer (marketplace billing). |
| **Quota** | A plan-defined limit on a resource type (servers, apps, etc.). |

---

## 17. Out-of-band notes

- This PRD describes Nolbase v1. v2 questions (USD support, multi-region, AWS, mobile) are deliberately deferred.
- The PRD is a living document. Update the **Last updated** date at the top whenever it changes substantively.
- All file paths in this document are relative to the repo root.
- "Coolify" in this document refers to the open-source project being forked. "Nolbase" refers to this product. They are not interchangeable.
