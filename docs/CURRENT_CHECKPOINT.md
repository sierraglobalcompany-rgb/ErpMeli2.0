# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1..9 GREEN / UI-DM10-A1 GREEN CERTIFIED / NEXT UI-DM10-A2 STATIC RESPONSIVE-CSS AUDIT / STOP-SAFE**

> Autoridad operativa para recuperar el proyecto si el chat se interrumpe. Retomar exactamente desde `RESUME_EXACTLY_FROM`; no reconstruir por intuición.

## 1. REPOSITORY AUTHORITY

```text
DATE=2026-10-16
REPO=sierraglobalcompany-rgb/ErpMeli2.0
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
LATEST_CERTIFIED_BRANCH_HEAD=7f4312e0d828bb7007a384166e4ddf490003b646
LATEST_CERTIFIED_BRANCH_RUN=38834828976
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
```

## 4. UI-DM10-A1 — LAUNCHER KEYBOARD ACCESSIBILITY

### RED

```text
RED_COMMIT=b6293b9ece63b60b725f729a2fb379b9e9013508
RED_RUN=38834252391
RED_CERTIFIED_PHP_8_3=YES
TESTS=801
ASSERTIONS=6712
FAILURES=1
EXPECTED_FAILURE=DesignMasterResponsiveContractTest::testLauncherBackdropIsOutsideKeyboardTabOrderAndKeyboardFocusIsVisible
```

### GREEN CERTIFIED

```text
MARKUP_COMMIT=ab08b705d0e4e185bb7df0fe96574653c5a9e5f5
FOCUS_TRAP_COMMIT=4f722a0a9fa2a1913637731c83fb7e2c90be6279
FOCUS_VISIBLE_COMMIT=7f4312e0d828bb7007a384166e4ddf490003b646
GREEN_RUN=38834828976
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

Root fix:
- launcher backdrop has `tabindex="-1"` while remaining mouse/touch clickable;
- JS focus-trap excludes `[tabindex="-1"]` controls from keyboard recycling;
- shared CSS provides visible `:focus-visible` outlines for links and buttons;
- no backend, route, database, Work, Billing, Financial, MELI client, OAuth-service or remote-write behavior changed.

## 5. NEXT — UI-DM10-A2 STATIC RESPONSIVE / CSS AUDIT

```text
NEXT=UI-DM10-A2 STATIC RESPONSIVE-CSS AUDIT
```

Scope is static source audit only:
1. D-01 / M-01 / T-01 shell composition against current HTML/CSS;
2. breakpoint coherence and viewport-risk patterns;
3. mobile table → card transformation;
4. fixed mobile bottom nav spacing/safe-area risks;
5. Apps launcher sizing/overflow/focus/ESC contracts;
6. authorized destinations and no fabricated/dead routes;
7. CSS patch noise: duplicate media systems, page-specific override chains, `!important` usage;
8. accessibility facts that can be proven statically;
9. no browser certification claim from source inspection alone.

If A2 finds a proven source-level defect, open one focused RED microblock. If no defect is proven, close UI-DM10-A and set `NEXT=UI-DM10-B REAL BROWSER QA`.

## 6. UI-DM9 QA AUTHORITY

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

## 7. BACKEND GUARDRAILS STILL CLOSED

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

## 8. PRODUCT SEQUENCE

```text
UI-DM10-A2 static responsive/CSS audit
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

## 9. ABSOLUTE NO-GO

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

## 10. STOP-SAFE

```text
STOP_NOW=NO
RESUME_EXACTLY_FROM=UI-DM10-A2 STATIC RESPONSIVE-CSS AUDIT
DO_NOT_OPEN_DM10_B_UNTIL_DM10_A_COMPLETE=YES
DO_NOT_MERGE_PR_19=YES
```
