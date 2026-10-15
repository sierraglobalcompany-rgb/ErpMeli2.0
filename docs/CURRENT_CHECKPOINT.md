# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1 GREEN / UI-DM2 GREEN / R3 HOME GREEN / UI-DM3 SALES GREEN / UI-DM4 PRODUCTS-INVENTORY GREEN / NEXT UI-DM5 BILLING-FINANCIAL / STOP-SAFE**

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
LATEST_CERTIFIED_CODE_HEAD=f7c06f119d2d17e246e2e6b8028c59021faa8f6c
CERTIFIED_ACTIONS_RUN=38822686688
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

Certified previously on PHP 8.3 / 8.4 / 8.5.

## 8. UI-DM4 — PRODUCTS / CATALOG + INVENTORY

**STATE=GREEN**

TDD evidence:

```text
RED_COMMIT=8c8b4f8e12c1eb4134a29cb8c358c12966ea6c71
RED_RESULT=expected CatalogDesignMasterUiTest failure because approved responsive structure did not exist
GREEN_COMMIT=f7c06f119d2d17e246e2e6b8028c59021faa8f6c
ACTIONS_RUN=38822686688
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

Implementation ruling:

```text
ONE HUMAN PRODUCT AREA
ONE EXISTING /catalog OWNER
NO SEPARATE /inventory ROUTE OR BACKEND MODULE
NO DUPLICATE MOBILE DATASET
NO NEW CSS FAMILY
NO N+1 INVENTORY READS
```

Changes:

- responsive `Catálogo e inventario` presentation;
- existing shared `panel`, `settings-grid` and responsive-table patterns reused;
- product rows recompose for mobile from the same data;
- selected product inventory remains in the same `/catalog` owner;
- missing inventory remains `Sin definir`;
- zero remains zero;
- inventory mutation remains `POST /catalog/{product_id}/inventory`;
- CatalogController, repositories, routes, Work, MELI, Billing, Financial and OAuth were not modified for UI-DM4.

## 9. BACKEND GUARDRAILS STILL CLOSED

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

## 10. PRODUCT SEQUENCE

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

## 11. NEXT — UI-DM5 BILLING / FINANCIAL

```text
NEXT=UI-DM5 BILLING-FINANCIAL
```

Before changing production code:

1. inspect current Billing view, Financial consumer/read model and focal tests;
2. prove the presentation boundary;
3. add RED structural UI test only for the approved Billing/Financial presentation;
4. do not modify Billing period scheduling/cursor/429 behavior;
5. do not create a parallel ledger, reconciliation engine or profitability calculator;
6. preserve `UNKNOWN` / `CONFLICT` and currency truth;
7. use existing shared Design Master components;
8. run focal Billing/Financial/429/Work regressions;
9. run full QA PHP 8.3/8.4/8.5;
10. persist checkpoint before opening Mercado Libre/Admin.

## 12. ABSOLUTE NO-GO NOW

```text
NO demo seed
NO Hostinger work
NO cron
NO real MELI mutation
NO REMOTE_WRITES
NO F15
NO F16
NO merge PR #19
NO Mercado Libre/Admin redesign until UI-DM5 checkpoint is GREEN
```
