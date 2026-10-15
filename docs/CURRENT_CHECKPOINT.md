# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1 GREEN / UI-DM2 GREEN / R3 HOME GREEN / UI-DM3 SALES GREEN / NEXT UI-DM4 PRODUCTS-INVENTORY / STOP-SAFE**

> Autoridad operativa para recuperar el proyecto si el chat se interrumpe. No reconstruir por intuición. Retomar exactamente desde `NEXT`.

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
LATEST_CERTIFIED_CODE_HEAD=866ff5d70e7b556531ed81bed977b73b3ee54ffd
CERTIFIED_ACTIONS_RUN=38820335428
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

Implemented and certified at current HEAD:

- responsive Sales list;
- responsive Sales detail;
- one factual row/data family, not duplicated desktop/mobile datasets;
- mobile recomposition via responsive markup/CSS;
- company scope preserved;
- `UNKNOWN` preserved;
- Sales Audit status contract preserved;
- Financial consumer contract preserved;
- PostSale/Invoice consumer contract preserved.

Relevant recovery commits include:

```text
bc99a7b... = responsive sale detail
9cca0cac... = responsive sales presentation styles
383a6db9... = preserve factual audit status contract
866ff5d7... = preserve financial and post-sale consumer contracts
```

Full Actions run `38820335428` at `866ff5d70e7b556531ed81bed977b73b3ee54ffd`:

```text
PHP 8.3 PASS
PHP 8.4 PASS
PHP 8.5 PASS
```

## 8. BACKEND GUARDRAILS STILL CLOSED

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

## 9. PRODUCT SEQUENCE

Demo and Hostinger remain deferred:

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

## 10. NEXT — UI-DM4 PRODUCTS / CATALOG + INVENTORY

```text
NEXT=UI-DM4 PRODUCTS-INVENTORY
```

Authoritative KISS ruling:

```text
ONE HUMAN PRODUCT AREA
ONE EXISTING /catalog OWNER
NO SEPARATE INVENTORY BACKEND/ROUTE JUST FOR VISUAL IA
NO DUPLICATE MOBILE DATASET
NO N+1 INVENTORY READS
```

Current contract to preserve:

- `/catalog` lists max 100 company-scoped product identities;
- plain list does not read inventory per row;
- `/catalog?product_id=<id>` selects one product and reads inventory for that product only;
- missing inventory = `Sin definir`, never zero;
- zero remains exactly zero;
- inventory mutation stays `POST /catalog/{product_id}/inventory`;
- CSRF + company role authorization remain mandatory;
- Work/API usage delta remains zero.

Execution order:

1. add RED structural tests for approved responsive Products presentation;
2. certify expected RED;
3. redesign `catalog.php` plus shared CSS only unless code evidence proves another owner must change;
4. preserve all Product/Inventory behavioral contracts;
5. run focal Catalog/Inventory/API tests;
6. run full QA PHP 8.3/8.4/8.5;
7. audit KISS/noise/patches;
8. update this checkpoint to UI-DM4 GREEN;
9. only then open Billing/Financial.

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
NO Billing/Financial redesign until UI-DM4 checkpoint is GREEN
```
