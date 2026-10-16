# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1..8 GREEN / NEXT UI-DM9 FULL AUTOMATED QA / STOP-SAFE**

> Autoridad operativa para recuperar el proyecto si el chat se interrumpe. Retomar exactamente desde `RESUME_EXACTLY_FROM`; no reconstruir por intuición.

## 1. REPOSITORY AUTHORITY

```text
DATE=2026-10-16
REPO=sierraglobalcompany-rgb/ErpMeli2.0
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
LATEST_CERTIFIED_CODE_HEAD=37f7f789b884d1072208783c7509dc999c83a876
LATEST_CERTIFIED_RUN=38833133356
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
CHECKPOINT_CADENCE=small blocks; persist approximately every 10–12 minutes or sooner at a closed/recoverable unit
```

## 3. CLOSED PRODUCT BLOCKS

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
UI-DM8-A4 OAUTH CALLBACK DESTINATION/CONTEXT=GREEN
UI-DM8 COMPLETENESS AUDIT=GREEN
```

## 4. UI-DM8 COMPLETENESS MAP

```text
DOC=docs/UI_DM8_COMPLETENESS_AUDIT.md
MAP_COMMIT=6f14ef35357e112397ba538ddc140454f270cec2
RESULT=NO EVIDENCE-BACKED PRODUCT GAP REMAINS INSIDE DM8 SCOPE
```

Active decisions preserved:
- do not invent operator screens for Work runner, RuntimePreflight, ERP API, webhooks, Invoicing bridge, Billing C0 or F13 rehearsal;
- Financial stays a factual read model consumed by existing human surfaces;
- Inventory stays under `/catalog`;
- historical LC-P1-1..4 are closed in the current product;
- no extra module is created merely because a backend class exists.

## 5. UI-DM8-A1 — COMPANY SELECT HOME CONSISTENCY

```text
STATE=GREEN
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

```text
STATE=GREEN
RED_COMMIT=a1bab019588144b929ae717dd469d5aecbc184f7
RED_RUN=38829656997
GREEN_COMMIT=d4e7c51f43f61082c3a9ddfe5eb38a93e720b7e5
GREEN_RUN=38830267285
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

Boundary:
- only `app/Core/Http/views/company-select.php` changed;
- shared Design Master components reused;
- memberships, roles, CSRF and zero Work/API side effects preserved.

## 7. UI-DM8-A3 — SETTINGS INVALID INPUT

```text
STATE=GREEN
RED_COMMIT=173b1a3e0e9f1a36f8086d2b6c9cc17deabf1c34
RED_RUN=38830859209
GREEN_COMMIT=5a135a91f7255522c6f1c03eff2f69eddd3cd535
GREEN_RUN=38831215153
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

Root fix:
- repository validation remains authoritative;
- numeric-but-invalid system settings now return HTTP 422 instead of leaking 500;
- CSRF, system-admin guard, debug export/clear, hidden `meli_writes_enabled`, and `REMOTE_WRITES=OFF` preserved.

## 8. UI-DM8-A4 — OAUTH CALLBACK DESTINATION / COMPANY CONTEXT

```text
STATE=GREEN
RED_COMMIT=8dc33da62b7afe6405e5edf1a05eae800916488b
RED_RUN=38832223024
GREEN_PRODUCTION_COMMIT=14c36eeb4e9878f3ef5d8a882753308d6770d84e
HISTORICAL_TEST_ALIGNMENT_COMMIT=37f7f789b884d1072208783c7509dc999c83a876
FINAL_RUN=38833133356
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

Proven root fix:
- successful OAuth callback restores `$_SESSION['company_id']` to the company bound when OAuth began;
- successful callback redirects to `/mercadolibre/accounts`, the human owner of the flow;
- state/PKCE consume-once, seller/company binding, reauthorization guard, encrypted token persistence and replay rejection remain unchanged;
- `REMOTE_WRITES=OFF` remains unchanged.

## 9. NEXT — UI-DM9 FULL AUTOMATED QA

```text
NEXT=UI-DM9 FULL AUTOMATED QA
```

Small-block execution:
1. inspect `composer.json`, `phpunit.xml`, `phpstan.neon` and `.github/workflows/*` to prove what the current QA actually covers;
2. verify the latest certified run is full enough for Design Master completion, not merely a focal suite;
3. inspect PR #19 diff/file inventory for accidental architecture/noise: duplicate routes, new frameworks, migrations, duplicate data owners, remote-write enablement, fake modules;
4. verify explicit guardrails: Work/429, Billing, Financial UNKNOWN/CONFLICT, OAuth lifecycle, tenancy/roles, CSRF, responsive contracts;
5. if a real gap is found, open a separate RED microblock; otherwise certify DM9 without speculative changes;
6. checkpoint before UI-DM10.

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
UI-DM9 full automated QA
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
STOP_NOW=NO
RESUME_EXACTLY_FROM=UI-DM9 FULL AUTOMATED QA
DO_NOT_OPEN_UI_DM10_UNTIL_DM9_CHECKPOINT=YES
```
