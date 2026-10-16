# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1..9 GREEN / UI-DM10-A1 GREEN / UI-DM10-A2.1 GREEN / UI-DM10-A2.2 RED CREATED / CI IN PROGRESS / STOP NOW**

> Autoridad operativa para recuperar el proyecto si el chat se interrumpe. Retomar exactamente desde `RESUME_EXACTLY_FROM`; no reconstruir por intuición.

## 1. REPOSITORY AUTHORITY

```text
DATE=2026-10-16
REPO=sierraglobalcompany-rgb/ErpMeli2.0
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
LATEST_CERTIFIED_BRANCH_HEAD=503181a181c40dd2d6f1be2685bb61dff0b23081
LATEST_CERTIFIED_BRANCH_RUN=38835728947
LATEST_RED_HEAD=8ded16a5ca965baa04e5bd79a951616764979579
CURRENT_RED_RUN=38836160916
CURRENT_RED_RUN_STATE=IN_PROGRESS at checkpoint creation
PHP_8_3=IN_PROGRESS
PHP_8_4=IN_PROGRESS
PHP_8_5=IN_PROGRESS
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
CHECKPOINT_CADENCE=small blocks; persist frequently so the project is recoverable if chat ends
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
UI-DM7 ADMINISTRATION=GREEN
UI-DM8 COMPLETENESS AUDIT=GREEN
UI-DM9 FULL AUTOMATED QA=GREEN
UI-DM10-A1 LAUNCHER KEYBOARD ACCESSIBILITY=GREEN
UI-DM10-A2.1 INITIAL SHEET FOCUS TRAP=GREEN
```

## 4. UI-DM10-A1 CERTIFICATION

```text
GREEN_HEAD=7f4312e0d828bb7007a384166e4ddf490003b646
GREEN_RUN=38834828976
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

## 5. UI-DM10-A2.1 CERTIFICATION

```text
RED_HEAD=7406c497a2cec288f2579fe3849ba9630f7178ce
RED_RUN=38835352489
RED_RESULT=802 tests / 6717 assertions / 1 expected failure
GREEN_HEAD=503181a181c40dd2d6f1be2685bb61dff0b23081
GREEN_RUN=38835728947
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

Root fix:
- initial focus on `.apps-launcher-sheet` no longer allows Tab/Shift+Tab to escape;
- Tab enters the first interactive item;
- Shift+Tab enters the last interactive item;
- first/last wrap remains intact.

## 6. UI-DM10-A2.2 — MOBILE SAFE-AREA

**STATE=RED CREATED / PRODUCTION UNCHANGED / CI RUNNING**

```text
RED_HEAD=8ded16a5ca965baa04e5bd79a951616764979579
RED_RUN=38836160916
RED_TEST=DesignMasterResponsiveContractTest::testMobileShellAccountsForBottomSafeArea
```

Contract under test:
- shared CSS variable `--safe-bottom: env(safe-area-inset-bottom, 0px)`;
- mobile bottom nav bottom offset uses the variable;
- mobile main content bottom clearance uses the variable;
- Apps launcher bottom margin uses the variable;
- no per-device hacks;
- production CSS has NOT been modified yet.

Current CI at checkpoint creation:

```text
PHP 8.3 = IN_PROGRESS in QA
PHP 8.4 = IN_PROGRESS in QA
PHP 8.5 = IN_PROGRESS in QA
```

## 7. STATIC AUDIT FINDINGS SO FAR

```text
CSS_IMPORTANT_COUNT=1
IMPORTANT_USAGE=.apps-launcher[hidden] only
RESPONSIVE_SYSTEM=single shared media-query family
RESPONSIVE_TABLE_SYSTEM=single table[data-responsive-table] → [data-mobile-card] pattern
PARALLEL_FRONTEND_FRAMEWORK=NO
```

Known remaining source-level risk being tested:
- fixed mobile bottom navigation and launcher spacing do not currently account for `safe-area-inset-bottom`.

## 8. EXACT RESUME POINT

```text
RESUME_EXACTLY_FROM=CHECK WORKFLOW RUN 38836160916 ONCE
```

On resume:
1. inspect run `38836160916` once;
2. if at least one PHP job FAILS, inspect one failed log;
3. require the only relevant failure to be `testMobileShellAccountsForBottomSafeArea`;
4. if RED is clean, modify ONLY `public/assets/app.css` for the shared safe-area root fix;
5. run full PHP 8.3/8.4/8.5 matrix;
6. checkpoint A2.2 before any further static audit;
7. only after A2.2 GREEN finish UI-DM10-A and decide whether UI-DM10-B real browser QA can open.

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

## 10. ABSOLUTE NO-GO

```text
NO demo seed
NO Hostinger
NO cron
NO real MELI mutation
NO REMOTE_WRITES
NO F15
NO F16
NO merge PR #19
NO UI-DM10-B until UI-DM10-A is complete
```

## 11. STOP-SAFE

```text
STOP_NOW=YES
RESUME_EXACTLY_FROM=CHECK WORKFLOW RUN 38836160916 ONCE
DO_NOT_TOUCH_APP_CSS_BEFORE_RED_CERTIFIED=YES
DO_NOT_OPEN_NEW_MICROBLOCK=YES
DO_NOT_MERGE_PR_19=YES
```
