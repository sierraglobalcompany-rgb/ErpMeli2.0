# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1..7 GREEN / UI-DM8-A1 GREEN / UI-DM8-A2 GREEN / NEXT UI-DM8-A3 SETTINGS INVALID INPUT REPRODUCTION / STOP-SAFE**

> Autoridad operativa para recuperar el proyecto si el chat se interrumpe. Retomar exactamente desde `NEXT`; no reconstruir por intuición.

## 1. REPOSITORY AUTHORITY

```text
DATE=2026-10-16
REPO=sierraglobalcompany-rgb/ErpMeli2.0
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
LATEST_CERTIFIED_CODE_HEAD=d4e7c51f43f61082c3a9ddfe5eb38a93e720b7e5
CERTIFIED_ACTIONS_RUN=38830267285
QA_PHP_8_3=PASS
QA_PHP_8_4=PASS
QA_PHP_8_5=PASS
REMOTE_WRITES=OFF
REAL_MELI_HTTP=0 for this development work
HOSTINGER_UI_DEPLOYED=NO
```

## 2. EXECUTION DISCIPLINE

```text
KISS=A→B
ANTI_NOISE=KEEP/MERGE/OPTIMIZE/SIMPLIFY/REMOVE only with evidence
ANTI_PATCH=root owner fix only
MICROBLOCK=RED → GREEN → full CI → audit → checkpoint → next
CHECKPOINT_CADENCE=small blocks; persist approximately every 10–12 minutes or sooner at a closed unit
```

## 3. CLOSED BLOCKS

```text
UI-DM1 FOUNDATION=GREEN
UI-DM2 FACTUAL DASHBOARD=GREEN
R3 DIRECT HOME=GREEN
UI-DM3 SALES LIST+DETAIL=GREEN
UI-DM4 PRODUCTS/CATALOG+INVENTORY=GREEN
UI-DM5 BILLING/FINANCIAL=GREEN
UI-DM6A MELI ACCOUNTS=GREEN
UI-DM6B MELI APP SETTINGS=GREEN
UI-DM7A COMPANIES=GREEN
UI-DM7B USERS/PERMISSIONS=GREEN
UI-DM7C SYSTEM SETTINGS/DIAGNOSTICS=GREEN
UI-DM7 ADMINISTRATION=GREEN
UI-DM8-A1 COMPANY SELECT HOME CONSISTENCY=GREEN
UI-DM8-A2 COMPANY SELECT PRESENTATION=GREEN
```

## 4. UI-DM8 COMPLETENESS MAP

```text
DOC=docs/UI_DM8_COMPLETENESS_AUDIT.md
MAP_COMMIT=6f14ef35357e112397ba538ddc140454f270cec2
```

Decisiones activas:
- no inventar pantallas para Work runner, RuntimePreflight, ERP API, webhooks, Invoicing bridge, Billing C0 ni F13 rehearsal;
- Financial permanece como lectura factual consumida por pantallas existentes;
- Inventory permanece bajo `/catalog`;
- LC-P1-1..4 históricos están cerrados en el producto actual;
- follow-ups DM8 restantes: A3 invalid System Settings input, A4 OAuth callback destination review.

## 5. UI-DM8-A1 — COMPANY SELECT HOME CONSISTENCY

**STATE=GREEN**

```text
RED_COMMIT=4f8893e1637eb2bd76df202da717b80976d6d5fc
RED_RUN=38828799463
RED_RESULT=797 tests / 6683 assertions / exactly 1 expected failure
GREEN_PRODUCTION_COMMIT=cd69a47f8659bbf685fcee5c00675560692758dc
GREEN_TEST_ALIGNMENT_COMMIT=5de62e520e5f163103b3cb05ef508aa3a49c8fa5
GREEN_RUN=38829270851
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

Cambio cerrado:
```text
GET /company/select with one already-active company:
OLD=/sales
NEW=/dashboard
```

## 6. UI-DM8-A2 — COMPANY SELECT PRESENTATION

**STATE=GREEN**

```text
RED_COMMIT=a1bab019588144b929ae717dd469d5aecbc184f7
RED_RUN=38829656997
RED_RESULT=798 tests / 6688 assertions / exactly 1 expected failure in CompanySelectorDesignMasterUiTest
GREEN_COMMIT=d4e7c51f43f61082c3a9ddfe5eb38a93e720b7e5
GREEN_RUN=38830267285
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

Change boundary:
- only `app/Core/Http/views/company-select.php` changed;
- reused shared `simple-page`, `page-header`, `panel`, `settings-form` components;
- no CSS family added;
- no route/controller/domain change;
- memberships, roles, CSRF and zero Work/API side effects preserved.

## 7. NEXT — UI-DM8-A3 SETTINGS INVALID INPUT REPRODUCTION

```text
NEXT=UI-DM8-A3 SETTINGS INVALID INPUT REPRODUCTION
```

Microblock boundary:
1. inspect `SystemSettingsController` update validation and existing Settings tests;
2. reproduce any invalid-input path that still returns 500 instead of a bounded human/client error;
3. do not change production unless a real failure is proven;
4. if a defect is proven, add one focused RED test and fix only the root validation/translation boundary;
5. preserve system-admin-only, CSRF, debug export/clear, hidden `meli_writes_enabled`, and `REMOTE_WRITES=OFF`;
6. RED → GREEN → full PHP 8.3/8.4/8.5 → checkpoint;
7. only then open DM8-A4 OAuth callback destination review.

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

## 9. PRODUCT SEQUENCE

```text
UI-DM8 gap closure
→ UI-DM9 full automated QA
→ UI-DM10 final visual/responsive QA
→ ERP COMPLETE
→ local demo
→ manual E2E
→ user approval
→ Hostinger
→ Hostinger runtime certification
→ cron / Work Runner
→ real MELI READ-only gates
→ later explicitly authorized phases
```

## 10. ABSOLUTE NO-GO

```text
NO DM8-A4 until A3 checkpoint closes
NO demo seed
NO Hostinger
NO cron
NO real MELI mutation
NO REMOTE_WRITES
NO F15
NO F16
NO merge PR #19
```

## 11. STOP-SAFE

```text
STOP_NOW=NO
RESUME_EXACTLY_FROM=UI-DM8-A3 SETTINGS INVALID INPUT REPRODUCTION
```
