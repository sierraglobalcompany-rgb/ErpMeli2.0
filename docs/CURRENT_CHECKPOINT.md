# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1 GREEN / UI-DM2 GREEN / R3 HOME GREEN / UI-DM3 SALES GREEN / UI-DM4 PRODUCTS-INVENTORY GREEN / UI-DM5 BILLING-FINANCIAL GREEN / UI-DM6A MELI-ACCOUNTS GREEN / UI-DM6B GREEN-CANDIDATE CI-PENDING / STOP-SAFE**

> Autoridad operativa para recuperar el proyecto si el chat se interrumpe. Retomar exactamente desde `RESUME`; no reconstruir por intuición.

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
LAST_FULLY_CERTIFIED_HEAD=9df8c23d7260b3df42a8c0f31be11025c256a4c7
LAST_FULLY_CERTIFIED_RUN=38824607991
CURRENT_GREEN_CANDIDATE_HEAD=ebf7f00ff1b320ea0ebcc676d969b58d99d5cfaa
CURRENT_GREEN_CANDIDATE_RUN=38825552488
CURRENT_GREEN_CANDIDATE_RUN_STATE=PENDING AT STOP CHECKPOINT
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

## 3. CLOSED DESIGN MASTER BLOCKS

```text
UI-DM1 FOUNDATION=GREEN
UI-DM2 FACTUAL DASHBOARD=GREEN
R3 DIRECT HOME=GREEN
UI-DM3 SALES LIST+DETAIL=GREEN
UI-DM4 PRODUCTS/CATALOG+INVENTORY=GREEN
UI-DM5 BILLING/FINANCIAL=GREEN
UI-DM6A MERCADO LIBRE COMPANY ACCOUNTS=GREEN
```

### UI-DM4 evidence

```text
RED_COMMIT=8c8b4f8e12c1eb4134a29cb8c358c12966ea6c71
GREEN_COMMIT=f7c06f119d2d17e246e2e6b8028c59021faa8f6c
GREEN_RUN=38822686688
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

### UI-DM5 evidence

```text
RED_COMMIT=f135880ef5f632b4f11e67625bc750a1f9b95556
RED_RUN=38823181176
GREEN_COMMIT=2de2b50b730c566f41163c3c0e81c42080845400
GREEN_RUN=38823564752
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

### UI-DM6A evidence

```text
RED_COMMIT=c4d07183c16af35743276830df6c4cc24b30d817
RED_RUN=38824233998
RED_RESULT=792 tests / 6603 assertions / exactly 1 expected failure
PRODUCTION_GREEN_COMMIT=1e906f0e26fa24330a49da4d6e10bdfa75e70f00
VISUAL_TEST_ALIGNMENT_COMMIT=9df8c23d7260b3df42a8c0f31be11025c256a4c7
GREEN_RUN=38824607991
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

## 4. UI-DM6B — GLOBAL MELI DEVELOPER APP SETTINGS

**STATE=GREEN CANDIDATE / CI PENDING AT STOP**

### RED evidence

```text
RED_COMMIT=59361f2b8deebfb98463d524ed3bd19ed6120e3d
RED_RUN=38825048573
RED_RESULT=793 tests / 6627 assertions / exactly 1 expected failure
RED_FAILURE=missing Design Master marker class="simple-page meli-app-settings-workspace"
SYNTAX=PASS
PHPSTAN=PASS
```

### GREEN candidate

```text
PRODUCTION_GREEN_COMMIT=44a1f551203ba6df8251f7c930034a9a91899443
ANTI_PATCH_TEST_CORRECTION_COMMIT=bad5e939d8dc38974cb8b8e55220ab88f49db011
VISUAL_ASSERTION_ALIGNMENT_COMMIT=ebf7f00ff1b320ea0ebcc676d969b58d99d5cfaa
GREEN_CANDIDATE_RUN=38825552488
RUN_STATE_AT_STOP=PENDING
```

### Authoritative DM6B boundary

```text
/settings/mercadolibre = global Developer app owner
SYSTEM_ADMIN_ONLY=YES
/company seller-account owner remains separate at /mercadolibre/accounts
NO owner merge
NO direct company-account link added from global page merely to satisfy a test
NO fake /mercadolibre/health route
NO fake /mercadolibre/sync route
NO new CSS family
NO controller/runtime-policy/OAuth/Work/429/route/remote-write changes
```

Security/behavior preserved:

- effective Client ID remains visible;
- Client Secret is never rendered;
- server-managed credentials remain read-only;
- blank secret preserves existing secret only when Client ID is unchanged;
- Client ID change without new secret fails closed;
- clear remains explicit;
- CSRF remains required;
- no Work/API side effects were added.

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

## 6. RESUME EXACTLY FROM HERE

```text
RESUME=CHECK GREEN CANDIDATE RUN 38825552488 ONCE
```

1. Check GitHub Actions run `38825552488` once.
2. If PHP 8.3 / 8.4 / 8.5 all PASS: certify UI-DM6B GREEN, persist checkpoint, then set `NEXT=UI-DM7 ADMINISTRATION`.
3. If any job FAILS: inspect that job only, fix root cause only, rerun; do not open Administration.
4. Do not repeat completed DM6A or earlier Design Master work.
5. Keep PR #19 draft and unmerged.

## 7. ABSOLUTE NO-GO UNTIL DM6B IS CERTIFIED

```text
NO Administration redesign
NO demo seed
NO Hostinger work
NO cron
NO real MELI mutation
NO REMOTE_WRITES
NO F15
NO F16
NO merge PR #19
```
