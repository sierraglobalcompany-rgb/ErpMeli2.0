# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1 GREEN / UI-DM2 GREEN / NEXT UI-DM3 SALES / STOP-SAFE**

> Autoridad operativa para recuperar el proyecto si el chat se interrumpe. No reconstruir por intuición. Retomar desde `NEXT`.

## 1. REPOSITORY AUTHORITY

```text
DATE=2026-10-15
REPO=sierraglobalcompany-rgb/ErpMeli2.0
STABLE_BACKEND_BRANCH=impl/v3-b-sales-audit-20261010
STABLE_BACKEND_SHA=d56183584840187e215e225942831f40ec9a04ae
FOUNDATION_BRANCH=ui/operator-shell-v1-20261014
FOUNDATION_BASE_SHA=c6f8af7f446cb327a905ae2f9bd9ee424c2d421f
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
HEAD_AT_CHECKPOINT=b5ccf1730a7c90ccf2b2f3b90be2a8bdddc4af53
REMOTE_WRITES=OFF
REAL_MELI_HTTP=0 for this development work
HOSTINGER_UI_DEPLOYED=NO
```

Do not merge PR #19 or deploy before the later explicit acceptance gate.

## 2. NON-NEGOTIABLE LAWS

```text
KISS=A→B; simple, efficient, optimized, effective, minimum architecture.
ANTI_NOISE=delete only with evidence; otherwise merge, simplify, optimize, or preserve.
ANTI_PATCH=fix owner/root cause; no workaround stacks.
```

Execution discipline:

```text
microblock → CI/QA → audit → checkpoint → next microblock
```

## 3. APPROVED DESIGN AUTHORITY

```text
DESIGN_MASTER_V1=APPROVED
DESKTOP=D-01 approved light ERP dashboard
MOBILE=M-01 approved mobile shell
MOBILE_BOTTOM_NAV=Inicio | Operación | Apps | Productos | Mercado Libre
APPS=central launcher
TABLET=responsive derivation, not a third UI
ONE_PRODUCT=YES
ONE_DESIGN_SYSTEM=YES
```

No React/Vue/SPA or new frontend framework unless later evidence proves necessity.

## 4. UI-DM1 — FOUNDATION

**STATE=GREEN**

Implemented on `ui/design-master-v1-20261015`:

- responsive shell foundation;
- desktop sidebar/topbar structure;
- mobile fixed bottom navigation;
- central Apps launcher;
- minimal `public/assets/app.js` interaction;
- evolved shared CSS/tokens;
- authorization-aware destinations;
- no fake routes for deferred capabilities;
- responsive structural contract tests.

Important rule: launcher/navigation may show only implemented + authorized + usable destinations.

## 5. UI-DM2 — FACTUAL DASHBOARD

**STATE=GREEN**

Implemented:

```text
GET /dashboard
active-company GET / → 303 /dashboard
DashboardController
DashboardReadModel
Dashboard view
DashboardHttpRouteTest
```

Certified behavior:

- active company required;
- company scoped;
- bounded date range;
- invalid/unbounded range → 422;
- local persisted facts only;
- no Work enqueue caused by dashboard GET;
- no live MELI HTTP required to render;
- sales totals remain separated by currency;
- profitability remains explicit UNKNOWN when facts are insufficient;
- account states remain factual.

### Recovery history for UI-DM2

```text
e49d641... = partial Dashboard implementation; CI RED
7656185... = fix PHPStan impossible int===false comparison
3185aa3... = wire DashboardController in Routes.php and root landing
fbc8a24... = update FreshInstall landing contract
b5ccf173... = update OperatorFoundation landing contract
```

### QA proof at HEAD `b5ccf173...`

GitHub Actions run `38817394804`:

```text
PHP 8.3 = PASS
PHP 8.4 = PASS
PHP 8.5 = PASS
composer validate = PASS
PHP syntax = PASS
PHPStan = PASS
full PHPUnit = PASS
```

The previous RED run reached 785 tests; failures were only stale `/sales` landing assertions after Dashboard became the approved landing. They were corrected as test-authority drift, not by reverting product behavior.

## 6. SAFETY / BACKEND GUARDRAILS STILL CLOSED

The design work must not weaken:

```text
429 durable defer/cooldown
Work durable execution
no inline retry storm
no FIFO/HOL resurrection
no child/page work explosion
OAuth lifecycle
company/account scoping
Financial UNKNOWN/CONFLICT semantics
Billing cursor/period behavior
remote writes OFF
```

ERP1 failure patterns must not return:

```text
429 storms
FIFO/HOL blocking
gRPC/batch/job desynchronization
```

## 7. PRODUCT SEQUENCE — USER CORRECTION

Demo and Hostinger are intentionally deferred.

```text
ERP COMPLETE
→ full automated QA
→ final visual/responsive QA
→ local demo
→ manual E2E
→ user approval
→ Hostinger
→ Hostinger runtime certification
→ cron / Work Runner
→ real MELI READ-only gates
→ later explicitly authorized phases
```

Do **not** create demo data now.
Do **not** test/deploy Hostinger now.
Do **not** configure cron now.

## 8. CURRENT PR #19 SCOPE

At this checkpoint the branch changes include the responsive shell foundation plus factual Dashboard. It is still a draft integration branch, not a release branch.

No merge authorization exists.

## 9. KNOWN SMALL DESIGN CLEANUP

The shared navigation still uses `/` as the visible Home link while `/` redirects to `/dashboard`. Functionally valid, but the UI-DM2 cleanup should prefer `/dashboard` as the direct Home destination and active-state owner so `Inicio / Resumen` is active while viewing the dashboard.

This is a presentation cleanup, not a backend blocker. Handle with a small test-first microblock before or at the start of UI-DM3.

## 10. NEXT — EXACTLY ONE MICROBLOCK

```text
NEXT=UI-DM3 SALES LIST + SALES DETAIL REDESIGN
```

Before redesigning Sales:

1. close the direct `/dashboard` Home link/active-state cleanup test-first;
2. inspect current Sales list/detail view contracts;
3. write only the RED presentation/responsive assertions required by D-01/M-01;
4. preserve all Sales/Audit/Billing/Financial/PostSale factual behavior;
5. implement desktop table + mobile list/card composition from the same dataset;
6. run focal Sales/Financial tests;
7. run full QA matrix;
8. write the next checkpoint;
9. STOP before Products/Inventory if CI is not fully GREEN.

## 11. ABSOLUTE NO-GO NOW

```text
NO demo seed
NO Hostinger work
NO cron
NO real MELI mutation
NO REMOTE_WRITES
NO F15
NO F16
NO merge PR #19
NO Products/Inventory implementation until UI-DM3 checkpoint is GREEN
```
