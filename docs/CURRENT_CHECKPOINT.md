# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1..7 GREEN / UI-DM8-A1 GREEN / NEXT UI-DM8-A2 COMPANY SELECT PRESENTATION / STOP-SAFE**

> Autoridad operativa para recuperar el proyecto si el chat se interrumpe. Retomar exactamente desde `NEXT`; no reconstruir por intuición.

## 1. REPOSITORY AUTHORITY

```text
DATE=2026-10-16
REPO=sierraglobalcompany-rgb/ErpMeli2.0
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
LATEST_CERTIFIED_CODE_HEAD=5de62e520e5f163103b3cb05ef508aa3a49c8fa5
CERTIFIED_ACTIONS_RUN=38829270851
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
```

## 4. UI-DM8 COMPLETENESS MAP

Audit document:

```text
docs/UI_DM8_COMPLETENESS_AUDIT.md
MAP_COMMIT=6f14ef35357e112397ba538ddc140454f270cec2
```

Key decisions:

- Human surfaces already covered: login, dashboard, sales/detail, catalog/inventory, billing, MELI accounts/settings, companies, users, system diagnostics, installer.
- Do not invent operator screens for Work runner, RuntimePreflight, ERP API, webhooks, Invoicing bridge, Billing C0, F13 rehearsal or other technical-only owners.
- Financial remains factual and merged into real consumers; no `/financial` ledger engine.
- Inventory remains under `/catalog`; no duplicate `/inventory` domain.
- Historical LC-P1-1..4 local operator gaps are closed in current product.
- Active DM8 follow-ups remain isolated: A2 selector presentation, A3 reproduce invalid System Settings input, A4 OAuth callback destination review.

## 5. UI-DM8-A1 — COMPANY SELECT HOME CONSISTENCY

**STATE=GREEN**

```text
RED_COMMIT=4f8893e1637eb2bd76df202da717b80976d6d5fc
RED_RUN=38828799463
RED_RESULT=797 tests / 6683 assertions / exactly 1 expected failure
RED_EXPECTED=/dashboard
RED_ACTUAL=/sales
GREEN_PRODUCTION_COMMIT=cd69a47f8659bbf685fcee5c00675560692758dc
GREEN_TEST_ALIGNMENT_COMMIT=5de62e520e5f163103b3cb05ef508aa3a49c8fa5
GREEN_RUN=38829270851
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

Change boundary:

```text
GET /company/select with exactly one already-active company:
OLD=/sales
NEW=/dashboard
```

Preserved:

- memberships/tenant selection;
- CSRF;
- foreign-company rejection;
- no Work enqueue;
- no controller/domain rewrite;
- OAuth callback destination unchanged;
- all backend guardrails unchanged.

## 6. BACKEND GUARDRAILS STILL CLOSED

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

## 7. PRODUCT SEQUENCE

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

Demo, Hostinger and cron remain deferred.

## 8. NEXT — UI-DM8-A2 COMPANY SELECT PRESENTATION

```text
NEXT=UI-DM8-A2 COMPANY SELECT PRESENTATION
```

Microblock boundary:

1. inspect current `company-select.php` against approved Design Master component family;
2. create one focused RED structural contract only if a real presentation gap is confirmed;
3. reuse `page-header`, `panel`, `settings-form` and existing shared CSS where sufficient;
4. no new CSS family and no new route/controller/domain;
5. preserve multi-company selection, roles, CSRF, active-company semantics and zero Work/API side effects;
6. RED → GREEN → full PHP 8.3/8.4/8.5 → checkpoint;
7. only then open DM8-A3 Settings invalid-input reproduction.

## 9. ABSOLUTE NO-GO

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

## 10. STOP-SAFE

```text
STOP_NOW=NO
RESUME_EXACTLY_FROM=UI-DM8-A2 COMPANY SELECT PRESENTATION
```
