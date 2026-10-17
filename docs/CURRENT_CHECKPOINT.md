# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=H6B BILLING HUMAN-FIRST TECHNICAL CURSOR FULLY CLOSED / H6C READY / H6C NOT STARTED / STOP-SAFE**

Autoridad operativa de recuperación. No depender del chat.

## Repository authority

```text
DATE=2026-10-17
REPO=sierraglobalcompany-rgb/ErpMeli2.0
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
BASE_BRANCH=ui/operator-shell-v1-20261014
BASE_SHA=c6f8af7f446cb327a905ae2f9bd9ee424c2d421f
REMOTE_WRITES=OFF
REAL_MELI_HTTP=0
HOSTINGER_DEPLOYED=NO
REAL_MELI_WRITES=NO
MERGE_AUTHORIZED=NO
STOP_SAFE=YES
```

## Required laws

```text
KISS=MANDATORY
ANTI_NOISE=MANDATORY
ANTI_PATCH=MANDATORY
HUMAN_FIRST=MANDATORY
UNKNOWN_NEVER_BECOMES_ZERO=MANDATORY
```

Read before continuing:

```text
AGENTS.md
docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md
design-system/erp-meli-2-0/MASTER.md
docs/UX_HUMAN_FIRST_MASTER_AUDIT_AND_IMPROVEMENT_PLAN_2026-10-16.md
```

Historical ERP1 429 causality remains `NOT_PROVEN`. Do not encode guesses about FIFO, batching, gRPC, waits, retries or transport as ERP2 architecture without fresh evidence.

## Certified history

```text
H1-FIX1 Sale Detail browser hierarchy = GREEN
H2A Skip-to-main = GREEN
H2B1/H2B2 Typography + touch = GREEN
H3 Dashboard Human-First = GREEN
H4 Sales List Human-First = FULLY GREEN
H4_AUTOMATED_GREEN_HEAD=9f888c2f3f81c03c7f4e45c0ccb803d03a68f912
H4_AUTOMATED_CI_RUN=38979090777
H4_BROWSER_CHECKOUT_HEAD=e6eda931643b837652e4b642b0b301b905779d31
H5_CATALOG_INVENTORY_HUMAN_FIRST=FULLY CLOSED
H5_PRODUCT_HEAD=e869821f97b0e59dbd37f05cbb4a35d27b6b75a7
H5_BROWSER_CHECKOUT_HEAD=2613ab4315f83417d63b63a7168debbe9d443e22
H5_GREEN_CI_RUN=38985843177
H5_FULLY_CLOSED=YES
```

## H6A — Billing Human-First document labels — FULLY CLOSED

```text
H6A_RED_COMMIT=024cb33ec82b8b59576e03b990f8c3b8ca7976cc
H6A_RED_CI_RUN=38988206740
H6A_PRODUCTION_GREEN_COMMIT=cd49a6dd4636507e2621b082e99ad7fb1f740c37
H6A_TEST_ONLY_FIX_COMMIT=f6648e6357b90432acb7ba66142a24b5fd04ebe7
H6A_FINAL_AUTOMATED_CI_RUN=38988788092
H6A_AUTOMATED_RESULT=814 tests / 6839 assertions / 0 failures
H6A_BROWSER_CHECKOUT_HEAD=a38c5c6958de3b79308ca813e7842637b0914297
H6A_BROWSER_GREEN=YES
H6A_FULLY_CLOSED=YES
```

Preserved truth:

- visible labels: `Factura` / `Nota crédito`;
- machine values remain `BILL` / `CREDIT_NOTE`;
- query semantics unchanged;
- no controller/schema/routes/Work/Financial/429 architecture changes;
- real browser desktop/mobile PASS;
- application 5xx = 0;
- real MELI calls = 0;
- business side effects = 0.

## H6B — Billing Human-First technical cursor — FULLY CLOSED

### Proven defect

`Cursor confirmado` is technical implementation detail and was exposed in the first information layer of the selected Billing request. Human-First requires the technical value to remain available but secondary. `Progreso persistido` and `Última lectura persistida (UTC)` remain primary operational facts.

```text
DEFECT=technical cursor exposed in primary information layer
ROOT_LAYER=presentation hierarchy only
BACKEND_CHANGE_REQUIRED=NO
CONTROLLER_CHANGE_REQUIRED=NO
ROUTE_CHANGE_REQUIRED=NO
SCHEMA_CHANGE_REQUIRED=NO
WORK_CHANGE_REQUIRED=NO
FINANCIAL_CHANGE_REQUIRED=NO
429_ARCHITECTURE_CHANGE_REQUIRED=NO
CSS_CHANGE_REQUIRED=NO
JS_CHANGE_REQUIRED=NO
```

### TDD trace

```text
H6B_RED_COMMIT=2b970371472426f37f9ddc5626e6ea001aae4470
H6B_RED_TEST=Tests\Integration\BillingDesignMasterUiTest::testBillingKeepsTechnicalCursorBehindProgressiveDisclosure
H6B_RED_CI_RUN=38991126596
H6B_RED_RESULT=EXPECTED FAILURE
H6B_RED_TESTS=815
H6B_RED_ASSERTIONS=6841
H6B_RED_FAILURES=1
H6B_GREEN_COMMIT=6fb24bfa47b144a69e11f626d3eee68d7bb9a160
H6B_FINAL_CI_RUN=38991387270
H6B_AUTOMATED_RESULT=815 tests / 6843 assertions / 0 failures
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
PHPSTAN=PASS
REAL_MELI_HTTP=0
```

Minimal implementation:

- `Cursor confirmado` remains present with the exact persisted value.
- It is inside native `<details class="billing-technical-details">`.
- Disclosure summary is `Detalles técnicos`.
- disclosure is collapsed by default.
- `Progreso persistido` remains first-layer.
- `Última lectura persistida (UTC)` remains first-layer.
- no CSS, JS, controller, route, schema, Work, Financial or 429 architecture changes.

### Real-browser certification — independently audited PASS

Antigravity executed Google Chrome via CDP against `/billing` using disposable local QA infrastructure on checkout:

```text
H6B_BROWSER_CHECKOUT_HEAD=63fa92d4b16b1590e7a245810ea695634ed3f708
AUTOMATED_GREEN_ANCESTOR=6fb24bfa47b144a69e11f626d3eee68d7bb9a160
BROWSER=Chrome/155.0.8059.84 via CDP
DESKTOP=1440x900 PASS
MOBILE=390x844 PASS
WORKING_TREE_BEFORE=CLEAN
WORKING_TREE_AFTER=CLEAN
```

Independent evidence audit before accepting PASS:

- uploaded evidence ZIP SHA-256: `b10ace06f8a893062749726b931bb5534837439942b0280bfb7f742ae16c2609`;
- Antigravity printed local outer ZIP SHA-256 `7af1373d1d5bb53572df08bb21a5658379c2afbe0a493be0e0aa2b19a08bfdf2`; outer ZIP hash mismatch is treated as repackaging metadata and is NOT used as integrity authority;
- internal `manifest-sha256.txt` contains 16 logical file entries;
- all 16 internal SHA-256 entries were independently recalculated and matched exactly;
- report, JSON, screenshots, network, console, fixture and side-effect evidence are mutually coherent;
- screenshots independently inspected: desktop/mobile collapsed hide the cursor; desktop/mobile open show `Cursor confirmado` with exact `987654321`;
- desktop `scrollWidth=1425`, `clientWidth=1425`; mobile `scrollWidth=390`, `clientWidth=390`; no page horizontal overflow;
- mobile navigation contains exactly 5 slots and does not overlap the technical disclosure;
- H6A labels remain `Factura` / `Nota crédito`, while URLs/options preserve `BILL` / `CREDIT_NOTE` machine semantics;
- native keyboard path reached the `<summary>` with Tab, Enter opened the disclosure and a second Enter closed it;
- exact persisted cursor `987654321` remained hidden while collapsed, visible unchanged while open, and hidden again after keyboard close;
- network: 16 responses, application 5xx = 0, real MELI network calls = 0; only incidental `/favicon.ico` 404;
- console: application errors = 0, uncaught JS exceptions = 0, console messages = 0;
- DB snapshots before/after are exact matches: `billing_periods=2`, `billing_details=0`, `work_items=0`, `api_usage_daily=0`, `orders=0`; business side effects = 0;
- disposable MariaDB/server/runtime were torn down and the working tree ended CLEAN.

Environment clarification:

Antigravity temporarily wrote `APP_ENV=local` into fallback `storage/private/runtime.json`, while the PHP server process was launched with environment `APP_ENV=test`. This does not invalidate the certification: `Environment::resolve()` gives `getenv()` / externally supplied process variables precedence over private-file fallback values, so effective `APP_ENV` remained `test` during the run.

Browser gates accepted:

```text
H6B_BROWSER_RESULT=PASS
BILLING_HTTP_200=PASS
H6A_HUMAN_LABELS_PRESERVED=PASS
H6A_MACHINE_VALUES_PRESERVED=PASS
PROGRESS_PRIMARY_VISIBLE=PASS
LAST_READ_PRIMARY_VISIBLE=PASS
TECHNICAL_DETAILS_PRESENT=PASS
TECHNICAL_DETAILS_COLLAPSED_DEFAULT=PASS
CURSOR_HIDDEN_WHEN_COLLAPSED=PASS
TECHNICAL_DETAILS_KEYBOARD_OPEN=PASS
CURSOR_VISIBLE_WHEN_OPEN=PASS
CURSOR_EXACT_VALUE_PRESERVED=PASS
TECHNICAL_DETAILS_KEYBOARD_CLOSE=PASS
CURSOR_HIDDEN_AFTER_CLOSE=PASS
PAGE_HORIZONTAL_OVERFLOW=NO
MOBILE_NAV=5 slots PASS
CONSOLE_APP_ERRORS=0
UNCAUGHT_JS_EXCEPTIONS=0
APPLICATION_5XX=0
REAL_MELI_NETWORK_CALLS=0
BUSINESS_SIDE_EFFECTS=0
BACKEND_CHANGED=NO
SCHEMA_CHANGED=NO
ROUTES_CHANGED=NO
CSS_CHANGED=NO
JS_CHANGED=NO
WORK_CHANGED=NO
FINANCIAL_CHANGED=NO
429_ARCHITECTURE_CHANGED=NO
NEW_DEPENDENCY=NO
KISS=PASS
ANTI_NOISE=PASS
ANTI_PATCH=PASS
HUMAN_FIRST=PASS
```

## Current disposition

```text
H6_STARTED=YES
H6A_FULLY_CLOSED=YES
H6B_PREFLIGHT=COMPLETE
H6B_RED=CONFIRMED
H6B_MINIMAL_GREEN=IMPLEMENTED
H6B_FINAL_AUTOMATED_GREEN=YES
H6B_BROWSER_GREEN=YES
H6B_FULLY_CLOSED=YES
H6C_READY=YES
H6C_STARTED=NO
STOP_SAFE=YES
```

## Exact resume point — H6C preflight only

```text
NEXT=H6C BILLING HUMAN-FIRST — PREFLIGHT / TRUTH MAP / SMALLEST RED CONTRACT
H6C_IMPLEMENTATION_STARTED=NO
```

Before touching production code:

1. Re-read current Billing view/controller/tests from the new branch HEAD.
2. Prove the next smallest Human-First defect using current source truth, not roadmap assumptions.
3. Candidate areas may be inspected but are NOT pre-approved changes: technical state wording, period input UX, operator guidance, remaining hierarchy/noise.
4. Preserve exact persisted/API semantics, authorization, period contract and document machine values.
5. Do not touch Financial/Work/429 pacing, retry, queue, batching or transport architecture.
6. TDD: one RED -> diagnose -> minimal GREEN -> full QA -> browser gate -> checkpoint.
7. If no meaningful H6C defect can be proven, do not invent work; close Billing Human-First instead.

## Frozen / prohibited

No broad redesign, new frontend framework, duplicate dataset/route/owner, Hostinger, cron, real MELI mutation, PR merge, Financial/Work/429 redesign, retry storms, child Work explosion, patch-chain CSS, UNKNOWN→0, or speculative ERP1 429 architecture.
