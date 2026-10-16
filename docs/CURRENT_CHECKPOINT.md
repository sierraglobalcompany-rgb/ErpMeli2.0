# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1..10-A GREEN / UI-DM10-B1 GREEN / UI-DM10-B2A DESKTOP CORE PAGES GREEN / NEXT UI-DM10-B2B SALE DETAIL FACTUAL FIXTURE / STOP-SAFE**

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
DM10_B_CHECKPOINT_HEAD_BEFORE_B2A=33fdfc8e24d3e3f98ea8b8ec18516f29ea7d54ff
DM10_B_CHECKPOINT_RUN_BEFORE_B2A=38847738064
DM10_B_CHECKPOINT_RUN_RESULT=PASS
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
UI-DM10-A STATIC VISUAL/RESPONSIVE AUDIT=GREEN
UI-DM10-B PRE-BROWSER LOCAL QA=GREEN
UI-DM10-B1 REAL BROWSER SHELL SMOKE=GREEN
UI-DM10-B2A DESKTOP CORE PAGES=GREEN
```

## 4. AUTOMATED QA AUTHORITY

### Product code certification

```text
PRODUCT_HEAD=a4d74349e5e28e4013a952bd47ea70ef12e1dd9a
PRODUCT_RUN=38836597229
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

### DM10-B local QA

```text
QA01=DIAGNOSED / ENVIRONMENT DB NAME ERROR ONLY
QA02=PASS
DIRECT_PHPUNIT=803 tests / 6720 assertions / 0 errors / 0 failures / 1 skipped
COMPOSER_QA_UNBOUNDED=PASS
COMPOSER_PROCESS_TIMEOUT_OVERRIDE=runtime-only; no composer.json change
PRODUCT_REGRESSION_PROVEN=NO
```

### Docs checkpoint CI

```text
HEAD=33fdfc8e24d3e3f98ea8b8ec18516f29ea7d54ff
RUN=38847738064
RESULT=PASS
```

## 5. UI-DM10-B1 REAL BROWSER CERTIFICATION

### Desktop 1440x900

```text
LOGIN_RENDER=PASS
AUTHENTICATION=PASS
DASHBOARD_SHELL=PASS
SIDEBAR=PASS
TOPBAR=PASS
CONSOLE_APP_ERRORS=NONE_OBSERVED
CAPTURED_HTTP_5XX=NONE
DESKTOP_APPS_REQUIRED=NO
QA_CONTRACT_FALSE_POSITIVE_CORRECTED=YES
```

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

## 6. UI-DM10-B2A DESKTOP CORE PAGES — GREEN

Environment:

```text
REAL_BROWSER=Codex In-app Browser
VIEWPORT=1440x900
APP_ENV=test
REAL_MELI_HTTP=0
MELI_WRITES_ENABLED=0
DB_NAME=erp_meli2_test
WORKING_TREE_BEFORE=CLEAN
WORKING_TREE_AFTER=CLEAN
SOURCE_CHANGES=NONE
```

### Sales `/sales`

```text
HTTP=200
ACTIVE_NAV=Ventas
EMPTY_STATE=truthful; no local sales/accounts/audits/results
PRIMARY_CONTROLS=PASS
PAGE_HORIZONTAL_OVERFLOW=NO
CLIENT_WIDTH=1425
SCROLL_WIDTH=1425
CONSOLE_APP_ERRORS=NONE_OBSERVED
CAPTURED_HTTP_5XX=NONE
```

### Catalog + Inventory `/catalog`

```text
HTTP=200
SINGLE_HUMAN_OWNER=YES
EMPTY_STATE=truthful; no products
MISSING_INVENTORY_FABRICATED_ZERO=NO
PRIMARY_CONTROLS=PASS with ordinary vertical scroll
PAGE_HORIZONTAL_OVERFLOW=NO
CLIENT_WIDTH=1425
SCROLL_WIDTH=1425
CONSOLE_APP_ERRORS=NONE_OBSERVED
CAPTURED_HTTP_5XX=NONE
```

### Billing `/billing`

```text
HTTP=200
SINGLE_HUMAN_OWNER=Facturación
EMPTY_STATE=truthful; no periods
FABRICATED_FINANCIAL_ZERO=NO
FAKE_RECONCILIATION_OR_PROFITABILITY=NO
PAGE_HORIZONTAL_OVERFLOW=NO
CLIENT_WIDTH=1425
SCROLL_WIDTH=1425
CONSOLE_APP_ERRORS=NONE_OBSERVED
CAPTURED_HTTP_5XX=NONE
UNKNOWN_WITH_FACTUAL_ROWS=NOT_EXERCISED
```

### Disposable DB proof after smoke

```text
companies=1
users=1
orders=0
products=0
product_inventory=0
billing_periods=0
billing_details=0
meli_accounts=0
work_items=0
```

Therefore B2A certifies empty-state/browser truth only. It does NOT certify populated Sale Detail/Financial UNKNOWN semantics.

### Evidence hashes

```text
dm10-b2a-sales-1440x900.png=169AD17459912DA555A600A0971DA33CBC8AEEC706E63DBB826BD6BE8798EF93
dm10-b2a-catalog-1440x900.png=05C5C2D96B68D5DD96C95D7850B64138FF1004F79827688C86162E234C239479
dm10-b2a-billing-1440x900.png=C6F8EC22FB0E9FBBF6349237D3E50881E9BF51CE40280C5AB5FB006C6D653F28
dm10-b2a-console-network.txt=D6176E2F0A53EC304064E7ECB991CF7B650778253DD769657B8C8873067401D1
dm10-b2a-runtime.txt=7552DA570EEBAAEA065D286250F6F0B88EDFFE29DB1FE46E14963ED0070A080C
```

### Non-blocking noise

```text
/favicon.ico=404 on initial login only
CLASSIFICATION=harmless browser asset noise
PRIMARY_PAGE_OR_ASSET_FAILURE=NO
DO_NOT_PATCH_PRODUCT_FOR_THIS_ALONE=YES
```

## 7. NEXT — UI-DM10-B2B SALE DETAIL FACTUAL FIXTURE

```text
NEXT=UI-DM10-B2B SALE DETAIL FACTUAL FIXTURE
MAX_SCOPE=one factual local sale detail browser scenario
```

Goal: exercise the populated Sale Detail surface with the smallest disposable local fixture already compatible with existing repository semantics.

Required checks:
- use only local disposable DB;
- no real MELI HTTP;
- no remote writes;
- no persistent demo seed;
- create the minimum factual sale/order/items/billing facts only if an existing supported fixture/helper can do so safely;
- verify Sale Detail renders at desktop 1440x900;
- verify factual fee/billing/financial values are represented without UNKNOWN→0 corruption;
- verify UNKNOWN/CONFLICT remains explicit where facts are insufficient;
- verify currency semantics are not silently merged;
- no application console error or captured HTTP 5xx;
- screenshot/evidence;
- first evidence-backed defect => STOP, no fix in same diagnostic run.

If no supported safe fixture mechanism exists, report BLOCKED instead of ad-hoc SQL guessing.

## 8. REMAINING DM10-B BROWSER WORK AFTER B2B

Continue only by checkpointed microblocks:

```text
MELI accounts + global settings according to role
Companies / Users / System Settings
430x932 mobile validation
768x1024 tablet portrait
1024x768 tablet landscape
1366x768 desktop
1920x1080 desktop
responsive table→card behavior
limited-role visibility/browser authorization
inventory missing vs factual zero
currency separation
remaining populated factual checks
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
RESUME_EXACTLY_FROM=UI-DM10-B2B SALE DETAIL FACTUAL FIXTURE
B1_OVERALL=GREEN
B2A=GREEN
BROWSER_EVIDENCE_REQUIRED=YES
DO_NOT_CLAIM_ERP_COMPLETE_BEFORE_DM10_B=YES
DO_NOT_MERGE_PR_19=YES
```
