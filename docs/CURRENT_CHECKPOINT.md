# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1..9 GREEN / UI-DM10-A1 GREEN / UI-DM10-A2.1 GREEN / NEXT UI-DM10-A2.2 MOBILE SAFE-AREA / STOP-SAFE**

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

## 4. UI-DM10-A1 — LAUNCHER KEYBOARD ACCESSIBILITY

```text
RED_COMMIT=b6293b9ece63b60b725f729a2fb379b9e9013508
RED_RUN=38834252391
GREEN_COMMIT=7f4312e0d828bb7007a384166e4ddf490003b646
GREEN_RUN=38834828976
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

Root fix:
- launcher backdrop removed from keyboard tab order;
- focus-trap excludes `[tabindex="-1"]` controls;
- shared CSS provides visible `:focus-visible` outlines.

## 5. UI-DM10-A2.1 — INITIAL SHEET FOCUS TRAP

### RED

```text
RED_COMMIT=7406c497a2cec288f2579fe3849ba9630f7178ce
RED_RUN=38835352489
RED_CERTIFIED=YES
TESTS=802
ASSERTIONS=6717
FAILURES=1
EXPECTED_FAILURE=DesignMasterResponsiveContractTest::testLauncherTrapHandlesTabFromInitiallyFocusedSheet
```

### GREEN CERTIFIED

```text
GREEN_COMMIT=503181a181c40dd2d6f1be2685bb61dff0b23081
GREEN_RUN=38835728947
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

Root fix:
- launcher still opens with focus on `.apps-launcher-sheet`;
- first Tab from the sheet moves to the first interactive item;
- first Shift+Tab from the sheet moves to the last interactive item;
- first/last interactive wrap behavior remains unchanged;
- only `public/assets/app.js` changed in GREEN.

## 6. STATIC AUDIT FINDINGS SO FAR

```text
CSS_IMPORTANT_COUNT=1
IMPORTANT_USAGE=.apps-launcher[hidden] only
RESPONSIVE_SYSTEM=single shared media-query family
RESPONSIVE_TABLE_SYSTEM=single table[data-responsive-table] → [data-mobile-card] pattern
PARALLEL_FRONTEND_FRAMEWORK=NO
```

Remaining proven source-level risk:
- fixed mobile bottom navigation and launcher spacing do not currently use `safe-area-inset-bottom`;
- this can collide with home-indicator/device safe areas;
- this must be handled as a separate microblock, not mixed with focus behavior.

## 7. NEXT — UI-DM10-A2.2 MOBILE SAFE-AREA

```text
NEXT=UI-DM10-A2.2 MOBILE SAFE-AREA
```

Scope:
1. add one focused RED contract proving mobile shell CSS accounts for `safe-area-inset-bottom`;
2. certify RED;
3. implement the minimum shared CSS root fix for mobile bottom nav + page bottom clearance + launcher offset;
4. do not add per-device hacks;
5. run full PHP 8.3/8.4/8.5 matrix;
6. checkpoint A2.2;
7. only then finish the remaining static audit and decide whether UI-DM10-A can close.

## 8. UI-DM9 QA AUTHORITY

```text
composer qa = PHP lint + PHPStan + full PHPUnit
phpunit.xml = entire tests/ directory
workflow = PHP 8.3 / 8.4 / 8.5 + MariaDB 11.4
REAL_MELI_HTTP=0
DM9_RUN=38833821545
DM9_PHP_8_3=PASS
DM9_PHP_8_4=PASS
DM9_PHP_8_5=PASS
```

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
UI-DM10-A2.2 mobile safe-area
→ finish UI-DM10-A static audit
→ UI-DM10-B real browser QA
→ ERP COMPLETE candidate
→ local demo
→ manual E2E
→ user approval
→ Hostinger
→ runtime certification
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
RESUME_EXACTLY_FROM=UI-DM10-A2.2 MOBILE SAFE-AREA
DO_NOT_OPEN_DM10_B_UNTIL_DM10_A_COMPLETE=YES
DO_NOT_MERGE_PR_19=YES
```
