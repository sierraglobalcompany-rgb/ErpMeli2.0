# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1..7 GREEN / UI-DM8-A1 GREEN / UI-DM8-A2 GREEN / UI-DM8-A3 GREEN / UI-DM8-A4 GREEN-CANDIDATE / FINAL CI PENDING / STOP-SAFE**

> Autoridad operativa para recuperar el proyecto si el chat se interrumpe. Retomar exactamente desde `RESUME_EXACTLY_FROM`; no reconstruir por intuición.

## 1. REPOSITORY AUTHORITY

```text
DATE=2026-10-16
REPO=sierraglobalcompany-rgb/ErpMeli2.0
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
LATEST_FULLY_CERTIFIED_CODE_HEAD=5a135a91f7255522c6f1c03eff2f69eddd3cd535
LATEST_FULLY_CERTIFIED_RUN=38831215153
ACTIVE_A4_CANDIDATE_HEAD=37f7f789b884d1072208783c7509dc999c83a876
ACTIVE_A4_FINAL_CI_RUN=38833133356
ACTIVE_A4_FINAL_CI_STATUS=IN_PROGRESS at checkpoint time
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
CHECKPOINT_CADENCE=small blocks; persist approximately every 10–12 minutes or sooner at a closed/recoverable unit
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
- A4 es el único follow-up DM8 abierto y ya está implementado; falta sólo certificar su CI final antes de declarar DM8 cerrado.

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

```text
RED_COMMIT=173b1a3e0e9f1a36f8086d2b6c9cc17deabf1c34
RED_RUN=38830859209
RED_RESULT_PHP_8_3=800 tests / 6700 assertions / exactly 2 expected failures
GREEN_COMMIT=5a135a91f7255522c6f1c03eff2f69eddd3cd535
GREEN_RUN=38831215153
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

Root fix boundary:
- only `app/Modules/Settings/SystemSettingsController.php` changed;
- repository validation remains authoritative;
- numeric-but-invalid settings now return HTTP 422 instead of leaking 500;
- CSRF, system-admin guard, debug export/clear, hidden `meli_writes_enabled`, and `REMOTE_WRITES=OFF` preserved.

## 8. UI-DM8-A4 — OAUTH CALLBACK DESTINATION / COMPANY CONTEXT

**STATE=GREEN-CANDIDATE / FINAL CI PENDING**

Proven inconsistency:
- OAuth begins under one bound company and persists the seller/account to that company;
- if the operator changed active company in another tab before callback, successful callback still redirected to `/sales` under the other active company context;
- this was inconsistent with the approved owner `Mercado Libre → Cuentas` and could visually land the operator outside the company where the account was just connected.

RED evidence:

```text
RED_COMMIT=8dc33da62b7afe6405e5edf1a05eae800916488b
RED_RUN=38832223024
RED_PHP_8_3=800 tests / 6679 assertions / exactly 1 expected failure
EXPECTED=/mercadolibre/accounts
ACTUAL=/sales
PRODUCTION_CHANGED_IN_RED=NO
```

GREEN production:

```text
GREEN_PRODUCTION_COMMIT=14c36eeb4e9878f3ef5d8a882753308d6770d84e
CHANGE_1=restore $_SESSION['company_id'] to boundCompanyId after successful OAuth connection
CHANGE_2=redirect 303 to /mercadolibre/accounts
```

Preserved by design:
- bound-company membership/admin revalidation;
- OAuth state and PKCE consume-once behavior;
- canonical seller/company binding;
- reauthorization account guard;
- token exchange and encrypted token persistence;
- callback replay rejection;
- `REMOTE_WRITES=OFF`.

Historical contract alignment:

```text
PRE_ALIGNMENT_RUN=38832623520
PHP_8_3=800 tests / 6675 assertions / exactly 1 failure
ONLY_FAILURE=MeliHttpSimulationTest still expected /sales, actual /mercadolibre/accounts
ALIGNMENT_COMMIT=37f7f789b884d1072208783c7509dc999c83a876
PRODUCTION_CHANGED_BY_ALIGNMENT=NO
FINAL_CI_RUN=38833133356
FINAL_CI_STATUS=IN_PROGRESS at checkpoint time
```

## 9. NEXT EXACT ACTION

```text
RESUME_EXACTLY_FROM=CHECK RUN 38833133356 ONCE
```

Rules on resume:
1. inspect `38833133356` only;
2. require PHP 8.3, 8.4 and 8.5 PASS before certifying A4;
3. if all PASS, mark `UI-DM8-A4=GREEN` and close the DM8 completeness audit if no evidence-backed gap remains;
4. only after DM8 is formally closed may UI-DM9 full automated QA open;
5. if any job fails, inspect exact log and fix only the proven cause—no speculative patch;
6. do not merge PR #19.

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

## 11. PRODUCT SEQUENCE

```text
finish UI-DM8 certification
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

## 12. ABSOLUTE NO-GO

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

## 13. STOP-SAFE

```text
STOP_NOW=YES
RESUME_EXACTLY_FROM=CHECK RUN 38833133356 ONCE
DO_NOT_OPEN_NEW_BLOCK=YES
```
