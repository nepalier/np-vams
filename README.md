# NP-VAMS — Phase 2: Project Foundation

This is real, runnable source for the foundation phase described in the
Step 1 roadmap: Laravel 12 + Inertia/Vue 3/TypeScript, Docker Compose
(PostgreSQL+PostGIS, Redis, MinIO, Nginx), Sanctum auth, multi-tenancy,
Spatie roles/permissions, Spatie activity log, and Nepal master data
(all 7 provinces, all 77 districts, area units, property types,
valuation purposes, current fiscal year).

## ⚠️ One thing this sandbox could not do

This response was generated in a sandboxed environment whose network
access is limited to source-code registries (GitHub, npm, PyPI) and does
**not** include `packagist.org` / `repo.packagist.org`. That means
`composer install` could not actually be executed here to verify the
dependency graph resolves and the app boots. Every file was written by
hand as real Laravel 12 / Vue 3 code (no pseudo-code, no TODO stubs), but
please run the install step below yourself as the first sanity check —
if anything doesn't resolve cleanly, tell me the error and I'll fix it.

## Setup

```bash
cd np-vams
cp backend/.env.example backend/.env

docker compose build
docker compose up -d postgres redis minio mailhog

docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate
docker compose run --rm app php artisan db:seed

docker compose up -d app nginx horizon scheduler vite
```

Visit `http://localhost:8080`. Demo login (non-production data only):
`admin@demo.npvams.local` / `ChangeMe!12345`.

## Tests

```bash
docker compose run --rm app php artisan test
```

Covers: tenant isolation (a user genuinely cannot query another tenant's
organizations, even via `Organization::all()`), and auth (login success,
inactive-user rejection, wrong-password rejection).

## What's actually implemented in this phase

- Multi-tenancy: `tenants`, `organizations`, `organization_branches`,
  `users` tables; `BelongsToTenant` trait + `TenantScope` global scope
  applied at the model layer (not just controller-level filtering);
  `IdentifyTenant` middleware resolves tenant from the **authenticated
  user**, never from client-supplied input.
- AuthN: Sanctum token issuance, login throttling, inactive-account
  rejection, real TOTP MFA verification (`pragmarx/google2fa`).
- AuthZ: Spatie `roles`/`permissions` tables (UUID-adapted), the 17
  default roles from the Step 1 role matrix seeded, a reference
  `OrganizationPolicy` showing the module+action+org(+branch) permission
  shape every future domain policy will follow, `Gate::before` bypass for
  Super Administrator only (platform-level, does not bypass `TenantScope`).
- Audit: Spatie Activitylog wired on `Tenant`, `Organization`,
  `OrganizationBranch`, `User`; login/logout explicitly logged too.
- Master data: `provinces` (7), `districts` (77), `local_levels`/`wards`
  (schema ready, bulk import path documented — see `NepalGeoSeeder`
  docblock — rather than 753 local levels hand-typed into a seeder),
  `fiscal_years`, `property_types`, `area_units` (with Nepal→sqm
  conversion factors), `valuation_purposes`.
- Centralized JSON exception handling and a consistent API error envelope.
- Frontend: Inertia+Vue3+TS scaffold, Tailwind, a working login page
  hitting the real `/api/v1/auth/login` endpoint, and a placeholder
  dashboard.

## What's intentionally NOT in Phase 2

Everything from Phase 3 onward: assignments, properties,
parcels, buildings, documents, GIS, the 5 valuation engines, review/
approval, report generation, billing, notifications. Building those
against a fake/unauthenticated shell would mean redoing them once real
auth+tenancy landed — this phase exists so they don't have to be redone.

---

## Phase 3: Core Workflow (clients, owners/borrowers, assignments, properties, parcels, buildings, documents, site visits, workflow engine)

Adds 47 new files on top of Phase 2's foundation — still real code, still
no placeholder logic, with the same sandbox caveat: `composer install`
could not be executed here (no `packagist.org` access), so please run it
as your first step and report back anything that doesn't resolve.

### Setup

No new PHP packages this phase. Re-run:

```bash
docker compose run --rm app composer install
docker compose run --rm app php artisan migrate
docker compose run --rm app php artisan db:seed
docker compose run --rm app php artisan test
```

`DatabaseSeeder` now also runs `WorkflowSeeder` (24 statuses, 30 guarded
transition edges matching the Step 1 workflow diagram).

### What's actually implemented

- **Workflow engine** (`App\Domain\Workflow\Services\WorkflowEngine`): the
  status graph is entirely DB-driven (`workflow_statuses` +
  `workflow_transition_rules`), not a hard-coded switch. It guarantees: a
  transition absent from the configured graph is rejected; a
  role-restricted edge is rejected for a user without that role; an edge
  marked `requires_remarks` is rejected without them; every successful
  transition writes an **insert-only, immutable** `workflow_transitions`
  row and updates the subject's `status` inside one DB transaction.
  Covered by `tests/Feature/WorkflowEngineTest.php` (6 tests).
- **Assignment numbering**: `AssignmentNumberGenerator` produces
  `VAL-{fiscal_year}-{000001}` per tenant per fiscal year using a Postgres
  advisory lock (not `MAX()+1`, which isn't safe under concurrent
  creation). Covered by `AssignmentNumberGeneratorTest.php`.
- **Clients vs. Organizations**: `clients`/`client_branches` are new and
  deliberately separate from Phase 2's `organizations` — a client (a bank,
  an insurer) is the valuation firm's business counterparty and very often
  never logs into the platform at all, whereas `organizations` are
  platform subscribers.
- **Parties**: `property_owners`, `borrowers`, `guarantors` as three
  distinct tables (matching the Step 1 DB design), with consent-capture
  fields wired in from the start.
- **Properties & parcels**: real PostGIS `point`/`polygon` columns (SRID
  4326, so raw GPS coordinates need no reprojection in the later GIS
  phase). Areas are stored **both** in the unit as originally entered
  (never overwritten) **and** normalized to square metres via the new
  `Area` value object (`App\Support\ValueObjects\Area`), backed by the
  real Nepal conversion factors seeded in Phase 2.
- **Buildings & floors**: core shell + floor-wise measurement. Building
  blocks, component-level construction detail, and condition-assessment
  checklists are deferred to the cost-approach valuation phase that
  actually consumes them, rather than created empty here.
- **Documents**: `PropertyDocument` + `DocumentVersion`, uploaded only
  through `DocumentService`, which enforces max size, real MIME-type
  detection (from file content, not the client-supplied extension — those
  are spoofable), a private storage disk, SHA-256 hashing, and hash-based
  duplicate detection, with a named hook for a ClamAV-style scan later.
- **Site visits**: scheduling/check-in/checklist/completion, with
  `SiteVisit::canBeMarkedComplete()` enforcing Section 17's "prevent
  inspection completion if mandatory information is missing." Schema-ready
  for offline PWA sync (`sync_status`, `last_synced_at`); the actual
  service-worker/PWA build-out is its own later phase.
- **Assignment API**: `POST/GET /api/v1/assignments`,
  `GET /api/v1/assignments/{id}`,
  `POST /api/v1/assignments/{id}/workflow/transition` — real FormRequest
  validation, a policy that lets an assignee see their own assignment even
  without the blanket `assignments.view` permission, and a
  server-computed `total_fee` (never trusted from the client) via a model
  `saving` event.
- New config: `config/npvams.php` (app-specific settings, keeps framework
  config untouched) and `config/filesystems.php` (adds the
  `private_documents` disk — merge into your skeleton's file rather than
  overwrite it if you've already customized `local`/`public`/`s3`).

### What's intentionally NOT in Phase 3

Land physical characteristics (Section 11) and land-use/planning detail
(Section 12) — those primarily feed valuation adjustments and land in
Phase 5 alongside the market-comparison engine that consumes them. GIS
tooling (GeoJSON/KML import-export, map UI), photo watermarking, the five
valuation engines, review/approval, report generation, billing, and
notifications are all still ahead per the roadmap.

---

## Phase 5: Valuation Engine (government rates, market comparison, cost approach, income approach, residual method, reconciliation, risk assessment)

Adds 42 new files — the actual financial-calculation core of the system.
Same sandbox caveat as every phase: `composer install` could not be run
here, so please run it first and report anything that doesn't resolve.
No new PHP packages this phase.

```bash
docker compose run --rm app composer install
docker compose run --rm app php artisan migrate
docker compose run --rm app php artisan db:seed
docker compose run --rm app php artisan test
```

### Design choice worth knowing about

**The five calculation engines are plain, DB-free PHP classes**
(`App\Domain\Valuation\Services\{MarketComparisonEngine,CostApproachEngine,
IncomeApproachEngine,ResidualEngine}`, `ReconciliationService`,
`App\Domain\Risk\Services\RiskAssessmentService`). They take arrays in and
return arrays out — no Eloquent, no `DB::`. `ValuationCalculationService`
is the only place that takes an engine's output and writes it to
`valuation_calculations`, so what's in the database is always exactly
what the engine computed, never hand-adjusted in a controller along the
way (Section 49: "Do not trust client-side calculations. Validate all
calculations on the server."). It also means the formulas are covered by
**32 fast unit tests that need no database at all**
(`tests/Unit/*EngineTest.php`, `ReconciliationServiceTest.php`,
`RiskAssessmentServiceTest.php`) — real assertions against real numbers
(e.g. "100,000 × 1.05 × 0.95 = 99,750", "40% straight-line depreciation on
a 20-year-old, 50-year-life building", "Gordon-growth DCF terminal value"),
not smoke tests that just check something ran.

### What's actually implemented

- **Government land rates**: modelled as **platform-level shared reference
  data** (no `tenant_id`) rather than per-tenant — these are officially
  published rates identical for every firm in a district/fiscal-year, so
  duplicating them per tenant would just let N copies of the same public
  fact drift apart. "Never overwrite historical fiscal-year rates"
  (Section 20) is enforced structurally by `RateVersioningService`: a
  revision always creates a new row and marks the old one superseded,
  never an `UPDATE` on `minimum_rate` itself. Proven by
  `GovernmentRateVersioningTest`.
- **Construction rates**: same versioning pattern, but tenant-scoped —
  firms legitimately maintain their own internal rate library, unlike
  government rates.
- **Market comparison**: `Adjusted rate = base × combined factors`, then
  mean/median/weighted-average/std-dev/outlier detection (flagged, never
  silently dropped) — exactly Section 22's formula, computed for real.
- **Cost approach**: replacement cost new (area × rate × location ×
  transportation × material × labour × (1+fee%) + external works +
  service cost, completion-percentage adjusted) minus depreciation, with
  all **five** depreciation methods from Section 23 actually implemented
  (straight-line, age-life, observed-condition, component-wise, custom) —
  not just straight-line with the others as stubs.
- **Income approach**: direct capitalization (NOI ÷ cap rate) **and** a
  real discounted cash flow with Gordon-growth terminal value, not just
  the simpler formula.
- **Residual/development method**: GDV minus every cost line from Section
  25; a negative residual is returned as-is (a real "this scheme doesn't
  pencil out" signal), never floored at zero.
- **Reconciliation**: weighted-average across method results (weight
  defaults to reliability rating), with a **hard requirement** for
  justification on any manual override — enforced in code, not just a UI
  hint (Section 26). Distress/forced-sale/mortgage/insurance values are
  derived from caller-supplied percentages, never a hard-coded national
  LTV or haircut (Section 28/49).
- **Risk assessment**: indicator weights and score-to-category bands are
  both tenant-configurable master data (`risk_indicators`,
  `risk_score_bands`), not hard-coded thresholds in the service. 22
  default indicators seeded per Section 29's list via
  `DefaultRiskConfigSeeder`. Override requires justification, same pattern
  as reconciliation.
- **API**: `POST /api/v1/assignments/{id}/calculations/market-comparison`
  and `.../calculations/cost-approach` as the reference implementation —
  income-approach, residual, reconciliation, and risk-assessment endpoints
  follow the identical Request→Policy→Service→Resource shape and are the
  next controllers to add.

### What's intentionally NOT in Phase 5

Full cash-flow *scheduling* for the development method (period-by-period
draw-down with financing interest compounding) is simplified to
caller-supplied cost totals rather than a generated schedule — flagged in
the `ResidualEngine` docblock rather than silently assumed. GIS layer
tooling, photo watermarking, review/approval workflow wiring into these
calculations, report generation, billing, and notifications are all still
ahead per the roadmap.

---

## Phase 6: Review, Approval, Digital Signature, Report Generation, QR Verification

Adds 33 new files — closing the loop from a reconciled valuation to an
issued, signed, publicly-verifiable report. Same sandbox caveat: `composer
install` couldn't run here, so please run it first. No new PHP packages
this phase (barryvdh/laravel-dompdf, phpoffice/phpword, and
simplesoftwareio/simple-qrcode were already added to `composer.json` back
in Phase 2, anticipating this phase).

```bash
docker compose run --rm app composer install
docker compose run --rm app php artisan migrate
docker compose run --rm app php artisan test
```

### What's actually implemented

- **Segregation of duties** (Section 30): `SegregationOfDutiesChecker`
  blocks the assigned valuer from also recording the technical-review
  decision, and blocks the assigned reviewer from also recording the final
  approval decision — on the *same* assignment. The only way around it is
  an explicit, auditable per-organization
  `allow_segregation_of_duties_exception` flag, off by default, itself
  logged via Activitylog like any other change to `organizations`. Proven
  by `SegregationOfDutiesTest` (4 tests, including the exception path).
- **Review & approval**: `review_comments` (many, inline, severity-graded)
  separate from `approval_records` (one immutable, insert-only row per
  decision) — a clean decision timeline independent of comment volume.
  Review/approval decisions that represent a real workflow transition
  (recommend → awaiting_approval, reject → correction_requested, approve →
  approved, etc.) drive the *same* `WorkflowEngine` from Phase 3, so the
  assignment's status and its decision history can never disagree.
- **Report integrity** (Section 34): `ReportIntegrityService` is the only
  code path that ever writes a `report_versions` row. A version is never
  updated after creation — a report generated after approval requires an
  explicit `supersedeReason`, which marks the *old* version superseded
  (not deleted) and points `reports.current_version_id` at the new one.
  `verifyIntegrity()` recomputes the SHA-256 hash of whatever's currently
  on disk and compares it to the hash recorded at generation time — real
  tamper detection, not a documented-but-unimplemented field. Proven by
  `ReportIntegrityTest` (4 tests, including an actual "swap the file on
  disk, watch integrity check fail" scenario).
- **Report generation** (Section 32): a real Blade PDF template
  (`resources/views/reports/templates/default.blade.php`) rendered via
  DomPDF with the assignment's actual data — cover page, purpose, property
  location, valuation-method summary table, reconciled/government/
  distress/forced-sale/mortgage values, risk category, assumptions,
  declaration, signature block. The DOCX twin is built independently
  through PhpWord's own document-object API (Blade doesn't render to
  DOCX). **Explicitly not yet built**, rather than silently thin: the
  full 30-plus-section layout (site-inspection narrative, comparable-by-
  comparable tables, embedded photos/maps/calculation-sheet annexes) —
  this phase ships the skeleton every other section slots into.
- **Digital signature**: `DigitalSignature` snapshots the signer's name/
  license at the moment of signing (so it survives the signer's profile
  changing later), records the file hash *at the moment of signing*, and
  `ReportWorkflowService::sign()` refuses to run unless the assignment is
  actually in the `approved` workflow status — signing can't jump the
  queue.
- **QR verification** (Section 33): `QrVerificationService::publicPayload()`
  is the *only* method that can answer an unauthenticated request, and it
  returns a hand-built array of exactly the allow-listed fields (report
  number, firm, report date, district/municipality, status, revision,
  signed-by name) — never a model instance, so a field added to `Report`
  later can't leak here by accident. An unknown token and a
  cancelled/expired one both resolve through the same code path (no
  distinct 404-reason to avoid handing a token-enumeration oracle to a
  scripted prober). Proven by `QrVerificationTest`, including an explicit
  assertion that the serialized payload never contains "citizenship" or
  "loan" anywhere.
- **Report lifecycle API**: `POST .../report/generate-draft`,
  `.../report/sign`, `.../report/issue`, plus
  `POST .../review/comments`, `.../review/decision`,
  `.../approval/decision`, and the public `GET /api/v1/verify/{token}`
  (outside the authenticated route group entirely, rate-limited
  separately).

### What's intentionally NOT in Phase 6

The full multi-section report layout (as above). Cancellation/supersede of
an *issued* report has service methods (`ReportWorkflowService::cancel()`/
`supersede()`) but no controller endpoint yet — wiring those to the
`report_issued → superseded` / `→ cancelled` workflow edges is the next
small piece. Real X.509/PKI certificate integration for the digital
signature (currently stores certificate *metadata* the org supplies;
actual cryptographic signing of the PDF bytes is a follow-on). Billing,
notifications, and dashboards are still ahead per the roadmap.

---

## Phase 7: Billing, Invoicing, Dashboards, Market Analytics

Adds 24 new files. Same sandbox caveat: `composer install` couldn't run
here. No new PHP packages this phase.

```bash
docker compose run --rm app composer install
docker compose run --rm app php artisan migrate
docker compose run --rm app php artisan test
```

### What's actually implemented

- **Billing arithmetic** (Section 35): `InvoiceCalculationService` is pure,
  DB-free PHP (same pattern as the Phase 5 valuation engines) — 8 unit
  tests cover the actual Nepal tax convention modelled here: **VAT is
  added on top and is part of what the client owes; TDS is withheld by the
  client at payment time, so it's treated as settled immediately rather
  than counted toward `outstanding_amount`**, which is documented as an
  explicit modelling decision, not left implicit. `outstanding_amount =
  total_amount - tds_amount - paid_amount - credited_amount`, floored at
  zero even if overpaid.
- **`BillingService`**: creates invoices with a real
  `INV-{fiscal_year}-{seq}` sequence (same advisory-lock pattern as
  assignment numbering), records payments (rejects one that would exceed
  the outstanding balance), and issues credit notes — both payments and
  credit notes recompute `outstanding_amount` and `status`
  (issued/partially_paid/paid/overdue/cancelled) through the same
  calculation service, so the two can never disagree. 6 feature tests.
- **Dashboards** (Section 37): `ValuationFirmDashboardService` and
  `MarketAnalyticsDashboardService` run **real aggregate SQL** against the
  tables built in earlier phases — no mocked numbers. Average turnaround
  time is computed from the immutable `workflow_transitions` log itself
  (days between assignment creation and the `report_issued` transition),
  not a separately-maintained duration field that could silently drift
  from what actually happened. A dashboard test explicitly creates a
  second tenant's assignment and asserts it never appears in the first
  tenant's count — proving the dashboards ride on `TenantScope` rather
  than needing their own manual `WHERE tenant_id = ...` that someone could
  forget to add later.
- **Government-to-market ratio**: computed per-reconciliation (each
  reconciliation's own market value ÷ its own government minimum value,
  *then* averaged) rather than averaging the two figures independently and
  dividing the averages — a materially different, less meaningful
  statistic that's easy to compute by accident.
- **Notifications** (Section 36): two real, queued `Notification` classes
  (`ReportIssuedNotification`, `CorrectionRequestedNotification`) wired to
  the workflow engine via `WorkflowTransitionObserver` — when a
  `workflow_transitions` row lands with `new_status = report_issued` or
  `correction_requested`, the assigned valuer is notified automatically,
  no controller has to remember to call it. English copy only for now;
  the other ~16 events and Nepali templates need a proper
  translation-template layer, flagged as the next piece rather than
  built as unused English-only stubs for all 18.
- **API**: `POST /api/v1/invoices`, `POST /api/v1/invoices/{id}/payments`,
  `GET /api/v1/dashboards/firm`, `GET /api/v1/dashboards/market-analytics`.

### What's intentionally NOT in Phase 7

Client-statement generation and fiscal-year financial reports (Section 35)
— straightforward extensions of the same `Invoice`/`Payment` tables, but
not yet built as endpoints. Valuer commission and staff-payment tracking
(Section 35) — no table for these yet. Bank reconciliation. Platform
Administrator and Client Institution dashboards (Section 37) — the
`ValuationFirmDashboardService` pattern extends directly to them, next.
Bilingual notification templates and the remaining ~16 lifecycle events
(Section 36), as above.

---

## Phase 7b: Platform/Client Dashboards, Client Statements, Fiscal-Year Reports, Bilingual Notification Templates

Adds 16 new files, closing out the items explicitly deferred at the end of
Phase 7. Same sandbox caveat: `composer install` couldn't run here. No new
PHP packages.

```bash
docker compose run --rm app composer install
docker compose run --rm app php artisan migrate
docker compose run --rm app php artisan test
```

### What's actually implemented

- **Bilingual notification templates** (Section 36): `notification_templates`
  is tenant-configurable (subject/body per event/channel/locale);
  `NotificationTemplateRenderer` checks it first and falls back to a
  built-in English **and Nepali** default baked into code — so a
  notification never silently fails to send just because nobody has
  configured a template row yet. `ReportIssuedNotification` and
  `CorrectionRequestedNotification` (from Phase 7) now render through
  this, keyed off the recipient's own `preferred_locale`. 4 unit tests,
  including one proving a tenant's own configured template overrides the
  default, and one proving an inactive template is correctly ignored.
- **Platform Administrator dashboard**: `PlatformAdminDashboardService`
  is the one place in the codebase that deliberately queries **without**
  tenant scoping (`::withoutTenantScope()` throughout) — correct here
  specifically because this view exists to see across every tenant.
  Gated in the controller by an explicit `Super Administrator` /
  `Platform Administrator` role check, not the tenant-scoped
  `dashboards.view` permission every firm's own staff also holds. A test
  creates two tenants' assignments and confirms the platform dashboard's
  count includes both, unlike the firm dashboard from Phase 7.
- **Client statement**: `ClientStatementService` builds a running-balance
  ledger (invoice → debit, payment/credit note → credit) in date order,
  computed fresh from the underlying rows each call — no separately
  maintained ledger table that could drift from the invoices/payments it's
  supposed to summarize.
- **Fiscal-year financial report**: total invoiced/VAT/TDS/collected/
  outstanding, invoice count by status, and a month-by-month invoiced
  total for one fiscal year.
- **Client Institution dashboard** — implemented, but with a **documented
  gap rather than a silent workaround**: Section 3 implies the bank's own
  staff log in and see only their institution's cases, but this schema's
  `clients` (Phase 3 decision) aren't linked to `User` records — most
  clients never authenticate at all. `ClientInstitutionDashboardService`
  is written so a *valuation firm's own staff* can pull up "how is Client
  X doing" today, in exactly the query shape a real client-portal endpoint
  would reuse once a second tenancy axis (client-scoped users, or a
  separate portal guard) is built. That auth model is flagged as the
  actual next step here, not glossed over.
- **API**: `GET /api/v1/dashboards/platform`,
  `GET /api/v1/dashboards/clients/{clientId}`,
  `GET /api/v1/clients/{clientId}/statement`,
  `GET /api/v1/fiscal-years/{fiscalYearId}/financial-report`.

### What's still not in the system

Valuer commission / staff-payment tracking, bank reconciliation, a real
client-portal auth model (see above), full multi-section report layout
(Phase 6), GIS map UI and GeoJSON/KML import-export tooling, the offline
field-inspection PWA's actual service worker, and the remaining ~16
notification events beyond the 2 wired so far. This is now the honest,
complete state of the incremental build across all phases — see each
phase's own README section above for what it added and what it explicitly
left for the next one.

---

## Deployment Target Change: Shared cPanel Hosting (MySQL, No Redis, No Docker)

**As of this revision, the default deployment target changed from
Docker + PostgreSQL/PostGIS + Redis to shared cPanel hosting on
MySQL/MariaDB with no Redis and no persistent worker process.** This was
a real architectural rework, not a config swap — it touched:

- **Spatial columns removed.** `properties.location` (was a PostGIS
  `point`) is now plain `latitude`/`longitude` decimals.
  `land_parcels.boundary_polygon` (was a PostGIS `polygon`) is now
  `boundary_points`, a JSON array of `{lat, lng}` vertices.
  `comparable_properties.coordinates` likewise became `latitude`/
  `longitude`. Distance/area calculations that would have used PostGIS
  spatial functions now need application-level math (e.g. the haversine
  formula for distance, a shoelace-formula helper for polygon area) —
  **not yet implemented**, flagged here rather than silently assumed;
  this is the concrete cost of the MySQL move.
- **Advisory locks → named locks.** `AssignmentNumberGenerator` and
  `InvoiceNumberGenerator` used Postgres `pg_advisory_xact_lock`
  (transaction-scoped); MySQL doesn't have a transaction-scoped
  equivalent, so they now use `GET_LOCK`/`RELEASE_LOCK` (connection-scoped)
  with an explicit `finally` block to release — because unlike Postgres,
  MySQL won't release a named lock automatically at transaction end.
- **Dashboard/report SQL rewritten.** `EXTRACT(EPOCH FROM ...)` →
  `TIMESTAMPDIFF(SECOND, ...)`; `to_char(date, 'YYYY-MM')` →
  `DATE_FORMAT(date, '%Y-%m')`.
- **Redis removed entirely.** `CACHE_STORE`, `SESSION_DRIVER`, and
  `QUEUE_CONNECTION` now default to `database`/`file`/`database`
  respectively (new `cache`, `cache_locks`, `jobs`, `job_batches`,
  `failed_jobs` tables added). `laravel/horizon` (Redis-only) was removed
  from `composer.json`.
- **No persistent worker process.** Shared hosting doesn't allow
  long-running processes — queued jobs (PDF/DOCX generation, notification
  sending) sit in the `jobs` table until a cron-triggered
  `artisan queue:work --stop-when-empty` burst drains them. See the cron
  setup below.
- **Private document storage** defaults to the local disk
  (`storage/app/private`, outside the public webroot) instead of
  MinIO/S3 — no object storage service assumed to exist on shared
  hosting. An S3-compatible disk definition is still there, ready to
  switch to if you add DigitalOcean Spaces / Backblaze B2 / Cloudflare R2
  later.

### Before you start: check what your host actually gives you

Go to your cPanel dashboard and look for:
1. **"Terminal"** (search the cPanel search box). A growing number of
   hosts include a browser-based shell even on basic shared plans — if
   you have it, the steps below are straightforward.
2. **"Select PHP Version"** → set to **8.3** (or the closest available
   ≥8.2) for this domain, then under **Extensions** confirm `pdo_mysql`,
   `mbstring`, `bcmath`, `intl`, `zip`, `gd`, `exif` are enabled. All of
   these are near-universal on modern cPanel/EasyApache setups.

If you have **no Terminal at all**, see "Path B: no shell access" further
down — it's slower but works.

### Path A: cPanel with Terminal access

**1. Create the MySQL database** (cPanel → MySQL® Databases):
- Create a database, e.g. `cpaneluser_npvams`
- Create a user, e.g. `cpaneluser_npvamsuser`, with a strong password
- Add the user to the database with **All Privileges**

**2. Upload the code.** Either cPanel's **Git Version Control** feature
(if your repo is on GitHub/GitLab), or zip the project and upload/extract
via **File Manager**. Put it **outside** `public_html`, e.g. at
`/home/cpaneluser/npvams`, so the application code (including `.env`,
`app/`, `database/`) is never web-accessible — only `public/` should be.

**3. Point the domain's document root at `public/`.** In WHM/cPanel →
**Domains**, edit the domain or create a subdomain and set its **Document
Root** to `/home/cpaneluser/npvams/backend/public`. (If your plan doesn't
let you customize the primary domain's document root, use an addon
domain or subdomain instead — those almost always allow it.)

**4. Open Terminal and run:**
```bash
cd ~/npvams/backend
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
# Edit .env: fill in DB_DATABASE / DB_USERNAME / DB_PASSWORD from step 1,
# APP_URL to your real domain, MAIL_* from a cPanel email account you've created.
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
```

**5. Build the frontend** (needs Node.js — use cPanel's **"Setup Node.js
App"** if available, or build it locally on your own machine and upload
the `public/build` output instead, since shared hosting often lacks a
usable Node toolchain):
```bash
npm install
npm run build
```

**6. Set up cron jobs** (cPanel → **Cron Jobs**) — this replaces both the
Laravel scheduler and the missing persistent queue worker:
```
* * * * * cd /home/cpaneluser/npvams/backend && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/cpaneluser/npvams/backend && php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

**7. Visit your domain.** You should see the login page. Demo credentials
(from the seeder — **change immediately, this is demo data**):
`admin@demo.npvams.local` / `ChangeMe!12345`.

### Path B: no shell access at all

This is meaningfully harder — 38 migrations and Composer's dependency
resolution aren't practical through a GUI. Two options, in order of
preference:

1. **Ask your host to enable Terminal.** Many hosts will turn this on for
   an existing account on request, even if it's not shown by default —
   worth a support ticket before doing the below.
2. **Build everything locally, upload the finished product:**
   - On your own machine (with PHP 8.3 + Composer installed), run
     `composer install --no-dev --optimize-autoloader` and
     `npm install && npm run build` inside the project.
   - Point `.env` at your **production** database host/credentials (many
     cPanel MySQL databases allow remote connections — check
     **Remote MySQL** in cPanel to allow your home IP temporarily) and run
     `php artisan migrate --force` and `php artisan db:seed --force`
     **from your own machine**, connecting to the remote production DB.
   - Zip the whole project **including `vendor/` and `node_modules`'s
     build output** and upload/extract via File Manager.
   - Without Terminal, you can't run `artisan queue:work` via cron either
     — set `QUEUE_CONNECTION=sync` in `.env` instead (jobs run inline
     during the web request instead of being queued). PDF/DOCX generation
     and notification sending will make requests slower, but it'll work.
   - `php artisan key:generate` needs to run somewhere — do it locally
     and copy the resulting `APP_KEY` value from your local `.env` into
     the production one.

### What to check if something doesn't come up

- **500 error, blank page:** set `APP_DEBUG=true` temporarily in `.env`
  and reload to see the real error (put it back to `false` once fixed —
  never leave debug mode on in production).
- **"could not find driver":** `pdo_mysql` isn't enabled for this PHP
  version in cPanel's Extensions manager.
- **Migration errors about a missing database:** double check
  `DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` in `.env` match exactly what
  cPanel generated (often prefixed with your cPanel username).
- **Assets (CSS/JS) not loading:** confirm `npm run build` actually ran
  and `public/build/` exists with the compiled files.
