# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1..7 GREEN / UI-DM8-A1 GREEN / UI-DM8-A2 GREEN / UI-DM8-A3 GREEN / NEXT UI-DM8-A4 OAUTH CALLBACK DESTINATION REVIEW / STOP-SAFE**

> Autoridad operativa para recuperar el proyecto si el chat se interrumpe. Retomar exactamente desde `NEXT`; no reconstruir por intuición.

## 1. REPOSITORY AUTHORITY

```text
DATE=2026-10-16
REPO=sierraglobalcompany-rgb/ErpMeli2.0
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
LATEST_CERTIFIED_CODE_HEAD=5a135a91f7255522c6f1c03eff2f69eddd3cd535
CERTIFIED_ACTIONS_RUN=38831215153
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
UI-DM8-A3 SETTINGS INVALID INPUT=GREEN
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
- follow-up DM8 restante: A4 OAuth callback destination review.

## 5. UI-DM8-A1 — COMPANY SELECT HOME CONSISTENCY

**STATE=GREEN**

```text
RED_COMMIT=4f8893e1637eb2bd76df202da717b80976d6d5fc
RED_RUN=38828799463
GREEN_PRODUCTION_COMMIT=cd69a47f8659bbf685fcee5c00675560692758dc
GREEN_TEST_ALIGNMENT_COMMIT=5de62e520e5f163103b3cb05ef508aa3a49c8fa5
GREEN_RUN=38829270851
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

## 6. UI-DM8-A2 — COMPANY SELECT PRESENTATION

**STATE=GREEN**

```text
RED_COMMIT=a1bab019588144b929ae717dd469d5aecbc184f7
RED_RUN=38829656997
GREEN_COMMIT=d4e7c51f43f61082c3a9ddfe5eb38a93e720b7e5
GREEN_RUN=38830267285
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

Change boundary:
- only `app/Core/Http/views/company-select.php` changed;
- shared Design Master components reused;
- no route/controller/domain change;
- memberships, roles, CSRF and zero Work/API side effects preserved.

## 7. UI-DM8-A3 — SETTINGS INVALID INPUT

**STATE=GREEN**

Proven defect:
- numeric-but-invalid `debug_retention_days` / `debug_max_mb` values reached the repository;
- repository correctly threw `InvalidArgumentException`;
- controller leaked that boundary as HTTP 500.

RED:

```text
RED_COMMIT=173b1a3e0e9f1a36f8086d2b6c9cc17deabf1c34
RED_RUN=38830859209
RED_RESULT_PHP_8_3=800 tests / 6700 assertions / exactly 2 expected failures
FAILURE_1=debug_retention_days=5 returned 500, expected 422
FAILURE_2=debug_max_mb=9 returned 500, expected 422
PRODUCTION_CHANGED_IN_RED=NO
```

GREEN:

```text
GREEN_COMMIT=5a135a91f7255522c6f1c03eff2f69eddd3cd535
GREEN_RUN=38831215153
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

Root fix boundary:
- only `app/Modules/Settings/SystemSettingsController.php` changed;
- catches repository `InvalidArgumentException` in `update()`;
- returns HTTP 422;
- repository validation remains authoritative and unchanged;
- CSRF, system-admin guard, debug export/clear, hidden `meli_writes_enabled`, and `REMOTE_WRITES=OFF` preserved.

## 8. NEXT — UI-DM8-A4 OAUTH CALLBACK DESTINATION REVIEW

```text
NEXT=UI-DM8-A4 OAUTH CALLBACK DESTINATION REVIEW
```

Microblock boundary:
1. inspect OAuth callback route/controller/service and current tests;
2. determine current successful callback destination(s) and company/account context behavior;
3. compare against approved product navigation (`/mercadolibre/accounts` and `/dashboard`) without inventing a new destination;
4. do not change production unless a real inconsistency is proven;
5. if defect proven, add one focused RED test and fix only the callback destination/context boundary;
6. preserve one-time OAuth code use, state/PKCE guards, seller/company binding, reauthorization semantics, CSRF-equivalent OAuth state protections, and `REMOTE_WRITES=OFF`;
7. RED → GREEN → full PHP 8.3/8.4/8.5 → checkpoint;
8. only then decide whether DM8 is complete or whether another evidence-backed gap remains.

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

## 10. PRODUCT SEQUENCE

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

## 11. ABSOLUTE NO-GO

```text
NO demo seed
NO Hostinger
NO cron
NO real MELI mutation
NO REMOTE_WRITES
NO F15
NO F16
NO merge PR #19
```

## 12. STOP-SAFE

```text
STOP_NOW=NO
RESUME_EXACTLY_FROM=UI-DM8-A4 OAUTH CALLBACK DESTINATION REVIEW
```
