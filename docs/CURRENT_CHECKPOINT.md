# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1 GREEN / UI-DM2 GREEN / R3 HOME GREEN / NEXT UI-DM3 SALES REDESIGN / STOP-SAFE**

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
LATEST_CERTIFIED_CODE_HEAD=5574f703a4743f4850137c62c5b5ec45dcd17562
REMOTE_WRITES=OFF
REAL_MELI_HTTP=0 for this development work
HOSTINGER_UI_DEPLOYED=NO
```

Do not merge PR #19 or deploy before the later explicit acceptance gate.

## 2. LAWS / EXECUTION DISCIPLINE

```text
KISS=A→B; simple, efficient, optimized, effective, minimum architecture.
ANTI_NOISE=delete only with evidence; otherwise merge, simplify, optimize, or preserve.
ANTI_PATCH=fix owner/root cause; no workaround stacks.
MICROBLOCK=microblock → RED → GREEN → full CI → audit → checkpoint → next.
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

Implemented:
- responsive shell foundation;
- desktop sidebar/topbar;
- mobile fixed bottom navigation;
- central Apps launcher;
- minimal vanilla `public/assets/app.js`;
- shared CSS/tokens;
- authorization-aware destinations;
- no fake routes for deferred capabilities;
- responsive structural contract tests.

Launcher/navigation rule: show only implemented + authorized + usable destinations.

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

Certified:
- active company required;
- company scoped;
- bounded date range;
- invalid/unbounded range → 422;
- local persisted facts only;
- dashboard GET does not enqueue Work;
- no live MELI HTTP required to render;
- currencies remain separated;
- profitability stays explicit UNKNOWN when facts are insufficient;
- account states remain factual.

Recovery commits:

```text
e49d641... = partial Dashboard implementation / CI RED
7656185... = PHPStan fix only
3185aa3... = wire /dashboard + root landing
fbc8a24... = FreshInstall landing contract
b5ccf173... = OperatorFoundation landing contract
```

QA at `b5ccf173...`, Actions run `38817394804`:

```text
PHP 8.3 PASS
PHP 8.4 PASS
PHP 8.5 PASS
Composer PASS
Syntax PASS
PHPStan PASS
Full PHPUnit PASS
```

## 6. R3 — DIRECT HOME DESTINATION

**STATE=GREEN**

TDD history:

```text
3dec3d14... = RED test: /dashboard must be direct Inicio destination and active owner
RED proof = 786 tests / 6553 assertions / exactly 1 expected failure
5574f703... = GREEN: desktop + mobile Inicio now href=/dashboard and active on /dashboard
```

GitHub Actions run `38818383063` at `5574f703...`:

```text
PHP 8.3 PASS
PHP 8.4 PASS
PHP 8.5 PASS
full QA PASS
```

No other navigation behavior changed.

## 7. SAFETY / BACKEND GUARDRAILS STILL CLOSED

The redesign must not weaken:

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

ERP1 failure patterns must not return: 429 storms, FIFO/HOL blocking, gRPC/batch/job desynchronization.

## 8. PRODUCT SEQUENCE — USER CORRECTION

Demo and Hostinger remain deferred.

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

Do not create demo data, test/deploy Hostinger, or configure cron now.

## 9. UI-DM3 AUDIT PREP

Current Sales findings before code changes:

```text
SalesListController = factual/company-scoped owner; no redesign need identified in controller.
list.php = foundation-level UI; two desktop tables/forms; no real mobile card composition yet.
detail.php = stronger foundation; preserves Financial/Billing/Invoice/PostSale facts but tables still need responsive composition.
SalesListHttpRouteTest = basic company-scope truth only.
SalesDetailHttpRouteTest = company-scope + basic presentation markers.
```

KISS design ruling for UI-DM3:

```text
ONE DATASET / ONE HTML ROW FAMILY
Desktop = table
Mobile = same rows recomposed as cards/list via responsive CSS + data labels
NO duplicate backend query
NO duplicate mobile data view
NO controller rewrite unless proven necessary
```

## 10. NEXT — EXACTLY ONE MICROBLOCK

```text
NEXT=UI-DM3 SALES LIST + SALES DETAIL REDESIGN
```

Execution order:
1. add RED structural tests for approved Sales presentation/mobile composition;
2. watch expected failure;
3. redesign `list.php` and `detail.php` only plus shared CSS as needed;
4. preserve Sales/Audit/Billing/Financial/PostSale facts and UNKNOWN semantics;
5. run focal Sales + Financial/PostSale tests;
6. run full QA PHP 8.3/8.4/8.5;
7. audit KISS/noise/patches;
8. persist checkpoint;
9. do not start Products/Inventory until UI-DM3 is fully GREEN.

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
