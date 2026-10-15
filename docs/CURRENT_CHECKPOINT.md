# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1 GREEN / UI-DM2 GREEN / R3 HOME GREEN / UI-DM3 SALES GREEN / UI-DM4 PRODUCTS-INVENTORY GREEN / UI-DM5 BILLING-FINANCIAL GREEN / UI-DM6A MELI-ACCOUNTS GREEN / NEXT UI-DM6B MELI-APP-SETTINGS / STOP-SAFE**

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
LATEST_CERTIFIED_CODE_HEAD=9df8c23d7260b3df42a8c0f31be11025c256a4c7
CERTIFIED_ACTIONS_RUN=38824607991
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

```text
RED_COMMIT=f135880ef5f632b4f11e67625bc750a1f9b95556
RED_RUN=38823181176
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

## 10. UI-DM6A — MERCADO LIBRE COMPANY ACCOUNTS

**STATE=GREEN**

TDD evidence:

```text
RED_COMMIT=c4d07183c16af35743276830df6c4cc24b30d817
RED_RUN=38824233998
RED_RESULT=792 tests / 6603 assertions / exactly 1 expected failure in MeliAccountsDesignMasterUiTest
PRODUCTION_GREEN_COMMIT=1e906f0e26fa24330a49da4d6e10bdfa75e70f00
VISUAL_TEST_ALIGNMENT_COMMIT=9df8c23d7260b3df42a8c0f31be11025c256a4c7
GREEN_RUN=38824607991
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

Authoritative boundary:

```text
/mercadolibre/accounts = company seller-account connection center
/settings/mercadolibre = separate system-admin Developer app owner
NO merge of both owners
NO fake health/sync/webhook routes
NO remote-write expansion
```

Implementation:

- company accounts page is now `Centro de conexión` using shared Design Master components;
- one responsive account table is reused for desktop/mobile;
- factual labels remain `Conectada`, `Requiere revisión`, `Debes volver a autorizar`, `Desconectada`;
- existing Connect / Disconnect / Reauthorize / Delete forms and CSRF remain unchanged;
- lifecycle authorization and company scoping remain protected by existing tests;
- no new CSS family;
- no lifecycle controller, OAuth service, Work, 429 or route change.

## 11. BACKEND GUARDRAILS STILL CLOSED

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

## 12. PRODUCT SEQUENCE

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

## 13. NEXT — UI-DM6B GLOBAL MELI DEVELOPER APP SETTINGS

```text
NEXT=UI-DM6B MELI-APP-SETTINGS
```

Execution boundary:

1. keep `/settings/mercadolibre` system-admin only;
2. preserve effective Client ID visibility and never render Client Secret;
3. preserve server-managed read-only mode;
4. preserve save / blank-secret keep / explicit clear / fail-closed on client change;
5. preserve CSRF and no Work/API side effects;
6. adapt only presentation to the shared Design Master shell/components;
7. do not merge this global owner into company accounts;
8. RED → GREEN → full PHP 8.3/8.4/8.5 → checkpoint;
9. only then open Administration.

## 14. ABSOLUTE NO-GO NOW

```text
NO demo seed
NO Hostinger work
NO cron
NO real MELI mutation
NO REMOTE_WRITES
NO F15
NO F16
NO merge PR #19
NO Administration redesign until UI-DM6B checkpoint is GREEN
```
