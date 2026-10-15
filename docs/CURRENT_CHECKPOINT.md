# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1 GREEN / UI-DM2 GREEN / R3 HOME GREEN / UI-DM3 SALES GREEN / UI-DM4 PRODUCTS-INVENTORY GREEN / UI-DM5 BILLING-FINANCIAL GREEN / UI-DM6A MELI-ACCOUNTS GREEN / UI-DM6B MELI-APP-SETTINGS GREEN / NEXT UI-DM7 ADMINISTRATION / STOP-SAFE**

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
LATEST_CERTIFIED_HEAD=ebf7f00ff1b320ea0ebcc676d969b58d99d5cfaa
CERTIFIED_ACTIONS_RUN=38825552488
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

## 3. CLOSED DESIGN MASTER BLOCKS

```text
UI-DM1 FOUNDATION=GREEN
UI-DM2 FACTUAL DASHBOARD=GREEN
R3 DIRECT HOME=GREEN
UI-DM3 SALES LIST+DETAIL=GREEN
UI-DM4 PRODUCTS/CATALOG+INVENTORY=GREEN
UI-DM5 BILLING/FINANCIAL=GREEN
UI-DM6A MERCADO LIBRE COMPANY ACCOUNTS=GREEN
UI-DM6B GLOBAL MELI DEVELOPER APP SETTINGS=GREEN
```

## 4. UI-DM6B — GLOBAL MELI DEVELOPER APP SETTINGS

**STATE=GREEN**

### TDD evidence

```text
RED_COMMIT=59361f2b8deebfb98463d524ed3bd19ed6120e3d
RED_RUN=38825048573
RED_RESULT=793 tests / 6627 assertions / exactly 1 expected failure
PRODUCTION_GREEN_COMMIT=44a1f551203ba6df8251f7c930034a9a91899443
ANTI_PATCH_TEST_CORRECTION_COMMIT=bad5e939d8dc38974cb8b8e55220ab88f49db011
VISUAL_ASSERTION_ALIGNMENT_COMMIT=ebf7f00ff1b320ea0ebcc676d969b58d99d5cfaa
GREEN_RUN=38825552488
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

### Authoritative boundary

```text
/settings/mercadolibre = global Developer app owner
SYSTEM_ADMIN_ONLY=YES
/mercadolibre/accounts = separate company seller-account owner
NO owner merge
NO fake health/sync routes
NO direct company-account link added from global page solely to satisfy a test
NO new CSS family
NO controller/runtime-policy/OAuth/Work/429/route/remote-write changes
```

Security/behavior preserved:

- effective Client ID visible;
- Client Secret never rendered;
- server-managed credentials remain read-only;
- blank secret preserves existing secret only when Client ID is unchanged;
- Client ID change without new secret fails closed;
- clear remains explicit;
- CSRF remains required;
- no Work/API side effects added.

## 5. BACKEND GUARDRAILS STILL CLOSED

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

## 6. PRODUCT SEQUENCE

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

## 7. NEXT — UI-DM7 ADMINISTRATION

```text
NEXT=UI-DM7 ADMINISTRATION
```

Split into short microblocks:

```text
UI-DM7A = Companies
UI-DM7B = Users / permissions
UI-DM7C = System settings / diagnostics presentation
```

Execution rules:

1. inspect current views/controllers/tests before changing production;
2. preserve all role/authorization/CSRF/tenancy behavior;
3. reuse existing Design Master component families only;
4. no new admin domain or permission model;
5. RED → GREEN → full PHP 8.3/8.4/8.5 → checkpoint for each microblock;
6. do not chain DM7A+B+C without checkpoints.

## 8. ABSOLUTE NO-GO NOW

```text
NO demo seed
NO Hostinger work
NO cron
NO real MELI mutation
NO REMOTE_WRITES
NO F15
NO F16
NO merge PR #19
NO UI-DM8 completeness work until UI-DM7 is GREEN
```
