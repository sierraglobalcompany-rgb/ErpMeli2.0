# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1 GREEN / UI-DM2 GREEN / R3 HOME GREEN / UI-DM3 SALES GREEN / UI-DM4 PRODUCTS-INVENTORY GREEN / UI-DM5 BILLING-FINANCIAL GREEN / UI-DM6 MELI GREEN / UI-DM7A COMPANIES GREEN / NEXT UI-DM7B USERS / STOP-SAFE**

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
LATEST_CERTIFIED_CODE_HEAD=67121c8f1afad3a7c5b58aa9f25dc8c6f4fee2d4
CERTIFIED_ACTIONS_RUN=38826341046
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
UI-DM7A COMPANIES=GREEN
```

## 4. UI-DM7A — COMPANIES

**STATE=GREEN**

```text
RED_COMMIT=9aa082e6d931a8f12a264acdd1ae1a5367507700
RED_RUN=38826014191
RED_RESULT=794 tests / 6639 assertions / exactly 1 expected failure in CompanyDesignMasterUiTest
GREEN_COMMIT=67121c8f1afad3a7c5b58aa9f25dc8c6f4fee2d4
GREEN_RUN=38826341046
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

Authoritative boundary preserved:

```text
/system/companies = existing company-management owner
SYSTEM_ADMIN_ONLY=YES
NO controller rewrite
NO new company domain
NO new permission model
NO new CSS family
```

Behavior preserved:

- DB-backed system-admin authority;
- CSRF on all writes;
- atomic create/update;
- archive blocks pending/running Work, live MELI accounts and active invoicing keys;
- archive preserves operational owners/history;
- hard delete only for archived pristine company with sole creator admin membership;
- hard delete never deletes the user;
- active company session is cleared on archive/delete when needed.

Presentation now uses the shared Design Master family with one administration surface and company cards; existing actions and owners are unchanged.

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

## 7. NEXT — UI-DM7B USERS / PERMISSIONS

```text
NEXT=UI-DM7B USERS
```

Execution boundary:

1. inspect `users.php`, `UserManagementController.php`, `UserManagementTest.php` and role authorization tests;
2. preserve DB-backed authority, memberships, status lifecycle, password rules and CSRF;
3. do not invent a new roles/permissions domain;
4. adapt presentation only with existing Design Master components;
5. RED → GREEN → full PHP 8.3/8.4/8.5 → checkpoint;
6. only then open UI-DM7C System Settings / diagnostics.

## 8. ABSOLUTE NO-GO NOW

```text
NO UI-DM7C until Users is GREEN
NO UI-DM8 completeness work until all UI-DM7 is GREEN
NO demo seed
NO Hostinger work
NO cron
NO real MELI mutation
NO REMOTE_WRITES
NO F15
NO F16
NO merge PR #19
```
