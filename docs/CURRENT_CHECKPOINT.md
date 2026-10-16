# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1..10-A GREEN / UI-DM10-B1 REAL BROWSER SHELL SMOKE GREEN / NEXT UI-DM10-B2A DESKTOP CORE PAGES / STOP-SAFE**

> Autoridad operativa para recuperar el proyecto si el chat se interrumpe. Retomar exactamente desde `RESUME_EXACTLY_FROM`; no reconstruir por intuición.

## 1. REPOSITORY AUTHORITY

```text
DATE=2026-10-16
REPO=sierraglobalcompany-rgb/ErpMeli2.0
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
LATEST_CERTIFIED_PRODUCT_HEAD=a4d74349e5e28e4013a952bd47ea70ef12e1dd9a
LATEST_CERTIFIED_PRODUCT_RUN=38836597229
QA_PHP_8_3=PASS
QA_PHP_8_4=PASS
QA_PHP_8_5=PASS
DM10_B_PRE_BROWSER_HEAD=b28e10a2e3da7fae14f793b6a1da34f7e8c1b56e
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
FIRST_BROWSER_DEFECT_RULE=capture first real defect and STOP; do not patch blindly
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
UI-DM10-A2.2 MOBILE SAFE-AREA=GREEN
UI-DM10-A STATIC VISUAL/RESPONSIVE AUDIT=GREEN
UI-DM10-B PRE-BROWSER LOCAL QA=GREEN
UI-DM10-B1 REAL BROWSER SHELL SMOKE=GREEN
```

## 4. UI-DM10-A CERTIFICATION SUMMARY

### A1 — launcher keyboard accessibility

```text
GREEN_HEAD=7f4312e0d828bb7007a384166e4ddf490003b646
GREEN_RUN=38834828976
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

### A2.1 — initial sheet focus trap

```text
GREEN_HEAD=503181a181c40dd2d6f1be2685bb61dff0b23081
GREEN_RUN=38835728947
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

### A2.2 — mobile safe-area

```text
GREEN_HEAD=a4d74349e5e28e4013a952bd47ea70ef12e1dd9a
GREEN_RUN=38836597229
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

## 5. DM10-B PRE-BROWSER LOCAL QA

The first local attempt used an unauthorized disposable DB name (`erp_meli2_dm10b_test`) and correctly hit the Catalog rehearsal guard. This was an environment error, not a product regression. The guard was preserved.

Corrected local QA used exactly `erp_meli2_test` and certified:

```text
QA01=DIAGNOSED / ENVIRONMENT NAME ERROR
QA02=PASS
DIRECT_PHPUNIT=803 tests / 6720 assertions / 0 errors / 0 failures / 1 skipped
DIRECT_PHPUNIT_ELAPSED=585.921s
COMPOSER_QA_UNBOUNDED=PASS
COMPOSER_PROCESS_TIMEOUT_OVERRIDE=runtime-only; no composer.json change
PRODUCT_REGRESSION_PROVEN=NO
```

Remote same-head reference remained GREEN on PHP 8.3 / 8.4 / 8.5.

## 6. UI-DM10-B1 REAL BROWSER CERTIFICATION

### Desktop 1440x900

```text
LOGIN_RENDER=PASS
AUTHENTICATION=PASS
DASHBOARD_SHELL=PASS
SIDEBAR=PASS
TOPBAR=PASS
CONSOLE_APP_ERRORS=NONE_OBSERVED
CAPTURED_HTTP_5XX=NONE
```

The first B1 contract incorrectly required a desktop Apps opener. Audit against source proved this was a QA-contract false positive:

```text
DESKTOP_APPS_REQUIRED=NO
MOBILE_BOTTOM_NAV_HIDDEN_ON_DESKTOP=EXPECTED
PRODUCT_CHANGE_REQUIRED=NO
```

Do not add a desktop Apps button to satisfy the discarded requirement.

### Mobile 390x844

```text
AUTHENTICATED_SHELL=PASS
DESKTOP_SIDEBAR_HIDDEN=YES
BOTTOM_NAV_VISIBLE=YES
BOTTOM_NAV_ORDER=Inicio | Operación | Apps | Productos | Mercado Libre
APPS_CENTRAL_CONTROL=PASS
MELI_SLOT=/mercadolibre/accounts through existing authorized branch
APPS_OPEN=PASS
LAUNCHER_NOT_CLIPPED=PASS
ESC_CLOSE=PASS
SECOND_CLOSE_PATH=PASS
FOCUS_RETURN_TO_APPS=PASS
INITIAL_BOTTOM_OVERLAP=NONE_OBSERVED
PRIMARY_HORIZONTAL_OVERFLOW=NO
CLIENT_WIDTH=375
SCROLL_WIDTH=375
HARDWARE_SAFE_AREA_NONZERO=NOT_EMULATED
CONSOLE_APP_ERRORS=NONE_OBSERVED
CAPTURED_HTTP_5XX=NONE
```

B1R evidence hashes:

```text
dm10-b1r-runtime.txt=D4C02C96217D41DADDFCFD7F926CC87B69769AF876F6C42768D8BB00810CDD4E
dm10-b1r-console-network.txt=58A5053F5CF4B5D687D71CAB8FAB8E07492B00FA6E158EDCEC4422F00990AAAD
dm10-b1r-mobile-shell-390x844.png=BD511D4C47BEBA5419BF30636966F02403C7CA20467C9B73FD9E747CA43860DD
dm10-b1r-mobile-apps-open-390x844.png=B5672DB7176641F5D5A32798C439C9BB239190BE96B7B64ACBC986A605069BA1
```

## 7. NEXT — UI-DM10-B2A DESKTOP CORE PAGES

```text
NEXT=UI-DM10-B2A DESKTOP CORE PAGES
VIEWPORT=1440x900
MAX_SURFACES=3
```

Exercise only these authenticated human surfaces in the next microblock:

```text
/sales
/catalog
/billing
```

For each surface verify:
- route renders in a real browser;
- Design Master desktop shell remains coherent;
- no clipped primary controls;
- no fabricated values or UNKNOWN→0 semantic corruption;
- empty local data is represented honestly;
- no application console error;
- no captured HTTP 5xx;
- screenshot evidence.

Stop on the first evidence-backed product defect. Do not fix in the same diagnostic run.

Do NOT open Sale Detail, MELI, Administration, tablet matrix or additional viewport matrix in B2A. Those are later small checkpoints.

## 8. REMAINING DM10-B BROWSER WORK

After B2A, continue only by checkpointed microblocks. Remaining scope includes:

```text
Sale Detail with minimal factual disposable fixture
MELI accounts + global settings according to role
Companies / Users / System Settings
430x932 mobile validation
768x1024 tablet portrait
1024x768 tablet landscape
1366x768 desktop
1920x1080 desktop
responsive table→card behavior
role visibility / limited-role browser verification
factual UNKNOWN / inventory missing vs zero / currency separation checks
```

Do not combine all remaining items into one run.

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
UI-DM10-B checkpointed real browser QA
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
NO persistent demo seed
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
RESUME_EXACTLY_FROM=UI-DM10-B2A DESKTOP CORE PAGES
B1_OVERALL=GREEN
BROWSER_EVIDENCE_REQUIRED=YES
DO_NOT_CLAIM_ERP_COMPLETE_BEFORE_DM10_B=YES
DO_NOT_MERGE_PR_19=YES
```
