# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1..10-A GREEN / UI-DM10-B1 GREEN / UI-DM10-B2A GREEN / UI-DM10-B2B SALE DETAIL FACTUAL FIXTURE GREEN / NEXT UI-DM10-B2C1 FINANCIAL CONFLICT / STOP-SAFE**

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
DM10_B2A_CHECKPOINT_HEAD=31c8c211df60995d82245a48da1937232460a2d8
DM10_B2A_CHECKPOINT_RUN=38848733895
DM10_B2A_CHECKPOINT_RUN_RESULT=PASS
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
UI-DM10-B2B SALE DETAIL FACTUAL FIXTURE=GREEN
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

### B2A docs checkpoint CI

```text
HEAD=31c8c211df60995d82245a48da1937232460a2d8
RUN=38848733895
RESULT=PASS
```

No product/test/CSS/JS/route/migration/composer change occurred in B2A or B2B.

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
EMPTY_STATE=truthful
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
EMPTY_STATE=truthful
MISSING_INVENTORY_FABRICATED_ZERO=NO
PRIMARY_CONTROLS=PASS
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
EMPTY_STATE=truthful
FABRICATED_FINANCIAL_ZERO=NO
FAKE_RECONCILIATION_OR_PROFITABILITY=NO
PAGE_HORIZONTAL_OVERFLOW=NO
CLIENT_WIDTH=1425
SCROLL_WIDTH=1425
CONSOLE_APP_ERRORS=NONE_OBSERVED
CAPTURED_HTTP_5XX=NONE
```

B2A certified empty-state/browser truth only; populated Financial truth was intentionally deferred to B2B+.

## 7. UI-DM10-B2B SALE DETAIL FACTUAL FIXTURE — GREEN

Authority/runtime:

```text
SOURCE_HEAD=31c8c211df60995d82245a48da1937232460a2d8
CHECKPOINT_CI_RUN=38848733895
CHECKPOINT_CI_RESULT=PASS
PHP=8.5.8
MARIADB=11.4.12
APP_ENV=test
REAL_MELI_HTTP=0
MELI_WRITES_ENABLED=0
DB_NAME=erp_meli2_test
REAL_BROWSER=Codex/ChatGPT in-app browser
VIEWPORT=1440x900
WORKING_TREE_BEFORE=CLEAN
WORKING_TREE_AFTER=CLEAN
SOURCE_CHANGES=NONE
CLEANUP_COMPLETE=YES
```

Disposable factual fixture:

```text
companies=1
users=1
meli_accounts=1
meli_tokens=0
orders=1
order_items=1
billing_periods=0
billing_details=0
work_items=0
api_usage_daily=0
products=0
external_order_id=1001
order_status=paid
order_currency=COP
order_total=10.0000
item_title=Local item
item_sku=BROWSER-SKU-1001
item_currency=COP
item_sale_fee=9.1250
pack_id=NULL
```

Sale Detail `/sales/1001`:

```text
HTTP=200
ORDER_ID_VISIBLE=YES
ITEM_VISIBLE=YES
SKU_VISIBLE=YES
RECONCILIATION_VISIBLE=YES
SCOPE=order
COMMISSION_VALUE=9.12500000
COMMISSION_COVERAGE=KNOWN
SCOPE_CURRENCY=COP
SELLER_SHIPPING=UNKNOWN
RECEIVER_SHIPPING_COVERAGE=UNKNOWN
OFFICIAL_REFERENCE=UNKNOWN
BILLING_COVERAGE=UNKNOWN
NET_RENDERED=NO
CLIENT_WIDTH=1425
SCROLL_WIDTH=1425
PAGE_HORIZONTAL_OVERFLOW=NO
PRODUCT_TABLE_INTERNAL_HORIZONTAL_SCROLL=YES / acceptable for this desktop evidence
CONSOLE_APP_ERRORS=NONE_OBSERVED
CAPTURED_HTTP_5XX=NONE
```

Read-only proof:

```text
work_items_before=0
work_items_after=0
api_usage_before=0
api_usage_after=0
orders_before=1
orders_after=1
order_items_before=1
order_items_after=1
billing_periods_before=0
billing_periods_after=0
billing_details_before=0
billing_details_after=0
READ_ONLY_PASS=YES
```

Meaning certified by B2B:

```text
KNOWN != UNKNOWN
UNKNOWN != 0
missing Billing != zero Billing
known sale fee remains exact decimal
Sale Detail render causes no Work/API/Billing side effect
```

Evidence hashes independently rechecked against the uploaded files:

```text
dm10-b2b-runtime.txt=0D787E63BAA32D9FDF36E7734F3E255D5045171800F304F82F2227EE9D6EFC6E
dm10-b2b-fixture-proof.txt=F4A277E1A5FE08B1B8BB4F6E317C1B3E194E0546CBA680E924C692A0434AB369
dm10-b2b-console-network.txt=7B839BFC5BC3AF710E9E30C52A4A56409B4330DA28E870663F8C378449A3C88A
dm10-b2b-side-effects.txt=833A5014E8D4AF10D3354580A40C671D1EE46E2174186969D687492D59A2B880
dm10-b2b-sale-detail-1001-1440x900.png=37BB71818B0EBA3438778501514F81C9F41A5A740B8692F4A90A38492B3C7DE3
dm10-b2b-sale-detail-1001-reconciliation-1440x900.png=384468EF86379C42D61D10F4669CF278C42F5E4D6B7BE5AFE4535A6B684425B2
```

Non-blocking noise:

```text
/favicon.ico=404
CLASSIFICATION=harmless browser asset noise
PRIMARY_PAGE_OR_ASSET_FAILURE=NO
DO_NOT_PATCH_PRODUCT_FOR_THIS_ALONE=YES
```

## 8. NEXT — UI-DM10-B2C1 FINANCIAL CONFLICT

Do NOT combine Financial CONFLICT and Inventory missing-vs-zero in one diagnostic run.

```text
NEXT=UI-DM10-B2C1 FINANCIAL CONFLICT
MAX_SCOPE=one local populated Sale Detail conflict scenario
VIEWPORT=1440x900
```

Goal:
- use one company and one local MELI account;
- use the smallest repository-proven pack scenario that creates mixed operational currencies;
- verify `Comisión operativa=CONFLICT` and `Cobertura de comisión=CONFLICT` when the factual scope is conflicting;
- verify `Moneda del ámbito=CONFLICT` if that is the existing authoritative behavior;
- verify missing unrelated facts remain UNKNOWN, not zero;
- verify no invented net/allocation/totals;
- verify no Work/API/remote side effect;
- first evidence-backed defect => STOP; no fix in same diagnostic run.

The existing `FinancialConsumerHttpRouteTest` is the fixture/semantic authority. Do not invent a new Financial model.

After B2C1, open a separate checkpoint for:

```text
UI-DM10-B2C2 INVENTORY MISSING VS FACTUAL ZERO
```

## 9. REMAINING DM10-B BROWSER WORK AFTER B2C1/B2C2

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
currency separation remaining cases
remaining populated factual checks
```

Do not combine all remaining items into one run.

## 10. BACKEND GUARDRAILS STILL CLOSED

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

## 11. PRODUCT SEQUENCE

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

## 12. ABSOLUTE NO-GO

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

## 13. STOP-SAFE

```text
STOP_NOW=YES
RESUME_EXACTLY_FROM=UI-DM10-B2C1 FINANCIAL CONFLICT
B1=GREEN
B2A=GREEN
B2B=GREEN
BROWSER_EVIDENCE_REQUIRED=YES
DO_NOT_CLAIM_ERP_COMPLETE_BEFORE_DM10_B=YES
DO_NOT_MERGE_PR_19=YES
```
