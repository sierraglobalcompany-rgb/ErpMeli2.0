# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1 GREEN / UI-DM2 GREEN / R3 HOME GREEN / UI-DM3 SALES GREEN / UI-DM4 PRODUCTS-INVENTORY GREEN / UI-DM5 BILLING-FINANCIAL GREEN / NEXT UI-DM6 MERCADO-LIBRE / STOP-SAFE**

> Autoridad operativa para recuperar el proyecto si el chat se interrumpe. Retomar exactamente desde `NEXT`; no reconstruir por intuición.

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
LATEST_CERTIFIED_CODE_HEAD=2de2b50b730c566f41163c3c0e81c42080845400
CERTIFIED_ACTIONS_RUN=38823564752
QA_PHP_8_3=PASS
QA_PHP_8_4=PASS
QA_PHP_8_5=PASS
REMOTE_WRITES=OFF
REAL_MELI_HTTP=0 for this development work
HOSTINGER_UI_DEPLOYED=NO
```

## 2. EXECUTION DISCIPLINE

```text
KISS=A→B; simple, efficient, optimized, effective, minimum architecture.
ANTI_NOISE=delete only with evidence; otherwise merge, simplify, optimize, or preserve.
ANTI_PATCH=fix owner/root cause; no workaround stacks.
MICROBLOCK=microblock → RED → GREEN → full CI → audit → checkpoint → next.
```

Every completed microblock must update this file before the next microblock starts.

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
NO_NEW_FRONTEND_FRAMEWORK=YES
```

## 4. UI-DM1 — FOUNDATION

**STATE=GREEN**

- responsive shell foundation;
- desktop sidebar/topbar;
- mobile fixed bottom navigation;
- central Apps launcher;
- minimal vanilla `public/assets/app.js`;
- shared CSS/tokens;
- authorization-aware destinations;
- no fake routes for deferred capabilities.

## 5. UI-DM2 — FACTUAL DASHBOARD

**STATE=GREEN**

- `GET /dashboard`;
- active-company `/` → `303 /dashboard`;
- company scoped;
- bounded date range;
- invalid/unbounded range → 422;
- local persisted facts only;
- no Work enqueue on render;
- no live MELI HTTP required to render;
- currencies remain separated;
- profitability remains explicit `UNKNOWN` when facts are insufficient;
- account states remain factual.

## 6. R3 — DIRECT HOME

**STATE=GREEN**

Desktop and mobile `Inicio` point directly to `/dashboard` and use `/dashboard` as active owner.

## 7. UI-DM3 — SALES LIST + DETAIL

**STATE=GREEN**

- responsive Sales list and detail;
- one factual data family, not duplicated desktop/mobile datasets;
- mobile recomposition via shared responsive rules;
- company scope preserved;
- Sales Audit, Financial, PostSale and Invoice factual contracts preserved;
- `UNKNOWN` preserved.

## 8. UI-DM4 — PRODUCTS / CATALOG + INVENTORY

**STATE=GREEN**

```text
RED_COMMIT=8c8b4f8e12c1eb4134a29cb8c358c12966ea6c71
GREEN_COMMIT=f7c06f119d2d17e246e2e6b8028c59021faa8f6c
ACTIONS_RUN=38822686688
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

Ruling preserved:

```text
ONE HUMAN PRODUCT AREA
ONE EXISTING /catalog OWNER
NO SEPARATE /inventory ROUTE OR BACKEND MODULE
NO DUPLICATE MOBILE DATASET
NO NEW CSS FAMILY
NO N+1 INVENTORY READS
```

## 9. UI-DM5 — BILLING / FINANCIAL

**STATE=GREEN**

TDD evidence:

```text
RED_COMMIT=f135880ef5f632b4f11e67625bc750a1f9b95556
RED_RUN=38823181176
RED_RESULT=791 tests / 6587 assertions / exactly 1 expected failure in BillingDesignMasterUiTest
GREEN_COMMIT=2de2b50b730c566f41163c3c0e81c42080845400
GREEN_RUN=38823564752
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

Authoritative boundary:

```text
/billing = human Billing surface
FinancialReadModel = factual authority consumed from sale detail
NO /financial route
NO /reconciliation route
NO /profitability route
NO parallel ledger
NO new reconciliation engine
NO invented profitability calculator
```

Implementation:

- Billing now follows the approved Design Master component family;
- responsive periods table uses the same data rows for desktop/mobile;
- selected period and sync request are presented as shared panels;
- existing message remains explicit: requesting sync only registers pending Work and does not query Mercado Libre from the page;
- no new CSS family was created;
- `BillingController`, `BillingPeriodSyncHandler`, Billing cursor/period behavior, Work, 429, `FinancialReadModel`, OAuth and routes were not modified for UI-DM5;
- existing Financial `UNKNOWN` / `CONFLICT` / currency truth remains unchanged.

## 10. BACKEND GUARDRAILS STILL CLOSED

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
REMOTE_WRITES=OFF
```

ERP1 failure patterns must not return.

## 11. PRODUCT SEQUENCE

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

Demo, Hostinger and cron remain deferred.

## 12. NEXT — UI-DM6 MERCADO LIBRE

```text
NEXT=UI-DM6 MERCADO-LIBRE
```

Before changing production code:

1. inspect `meli-accounts.php`, `mercadolibre.php`, account/OAuth lifecycle tests and authorization rules;
2. define the human connection-center boundary without inventing health engines or remote-write actions;
3. preserve human states already backed by facts: `Conectada`, `Requiere revisión`, `Debes volver a autorizar`, `Desconectada`;
4. preserve Connect / Disconnect / Reauthorize / Delete only where currently authorized;
5. add RED structural UI test;
6. redesign with existing Design Master components only;
7. no real MELI HTTP and no remote-write expansion;
8. run focal MELI lifecycle/OAuth/authorization tests;
9. run full QA PHP 8.3/8.4/8.5;
10. persist checkpoint before Administration.

## 13. ABSOLUTE NO-GO NOW

```text
NO demo seed
NO Hostinger work
NO cron
NO real MELI mutation
NO REMOTE_WRITES
NO F15
NO F16
NO merge PR #19
NO Administration redesign until UI-DM6 checkpoint is GREEN
```
