# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1..7 GREEN / UI-DM8-A1 GREEN / UI-DM8-A2 RED CREATED / CI RUNNING / STOP-SAFE**

> Autoridad operativa para recuperar el proyecto si el chat se interrumpe. Retomar exactamente desde `RESUME_EXACTLY_FROM`; no reconstruir por intuición.

## 1. REPOSITORY AUTHORITY

```text
DATE=2026-10-16
REPO=sierraglobalcompany-rgb/ErpMeli2.0
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
LAST_FULLY_CERTIFIED_HEAD=5de62e520e5f163103b3cb05ef508aa3a49c8fa5
LAST_FULLY_CERTIFIED_RUN=38829270851
LAST_FULLY_CERTIFIED_QA_PHP_8_3=PASS
LAST_FULLY_CERTIFIED_QA_PHP_8_4=PASS
LAST_FULLY_CERTIFIED_QA_PHP_8_5=PASS
CURRENT_RED_HEAD=a1bab019588144b929ae717dd469d5aecbc184f7
CURRENT_RED_RUN=38829656997
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
- follow-ups DM8: A2 selector presentation, A3 invalid System Settings input, A4 OAuth callback destination review.

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

**STATE=RED CREATED / PRODUCTION UNCHANGED / CI RUNNING**

Hallazgo confirmado:
- `company-select.php` es funcional pero usa markup desnudo;
- no reutiliza explícitamente la familia Design Master `page-header`, `panel`, `settings-form`;
- esto es un hueco de coherencia visual, no un dominio nuevo.

RED:

```text
RED_COMMIT=a1bab019588144b929ae717dd469d5aecbc184f7
RED_TEST=tests/Integration/CompanySelectorDesignMasterUiTest.php
RED_RUN=38829656997
PHP_8_3=IN_PROGRESS
PHP_8_4=IN_PROGRESS
PHP_8_5=IN_PROGRESS
PRODUCTION_CHANGED=NO
```

Contrato RED:
- `simple-page company-selector-workspace`;
- `page-header company-select-page-header`;
- `panel company-select-panel`;
- `settings-form company-select-form`;
- mismo POST `/company/select`;
- ambas empresas visibles;
- cero Work rows;
- cero `api_usage_daily` side effects.

## 7. RESUME EXACTLY

```text
RESUME_EXACTLY_FROM=UI-DM8-A2 RED RUN 38829656997
```

Al retomar:
1. revisar resultado final del run `38829656997`;
2. certificar que el fallo sea únicamente el esperado por markup Design Master ausente;
3. si RED es válido, modificar **sólo** `app/Core/Http/views/company-select.php`;
4. reutilizar CSS/clases existentes; no crear nueva familia CSS;
5. ejecutar full QA PHP 8.3/8.4/8.5;
6. persistir A2 GREEN antes de abrir DM8-A3;
7. si CI revela otra causa, resolver sólo la causa raíz y checkpoint.

## 8. BACKEND GUARDRAILS

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
NO DM8-A3 until A2 checkpoint closes
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
STOP_NOW=YES
RESUME_EXACTLY_FROM=UI-DM8-A2 RED RUN 38829656997
```
