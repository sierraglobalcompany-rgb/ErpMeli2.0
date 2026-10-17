# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=H5 CATALOG / INVENTORY HUMAN-FIRST FULLY CLOSED / H6 BILLING HUMAN-FIRST READY / H6 NOT STARTED / STOP-SAFE**

Autoridad operativa de recuperación. No depender del chat.

## Repository authority

```text
DATE=2026-10-17
REPO=sierraglobalcompany-rgb/ErpMeli2.0
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
H5_PRODUCT_HEAD=e869821f97b0e59dbd37f05cbb4a35d27b6b75a7
H5_BROWSER_CHECKOUT_HEAD=2613ab4315f83417d63b63a7168debbe9d443e22
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

Read before continuing:

```text
AGENTS.md
docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md
design-system/erp-meli-2-0/MASTER.md
docs/UX_HUMAN_FIRST_MASTER_AUDIT_AND_IMPROVEMENT_PLAN_2026-10-16.md
```

KISS / anti-noise / anti-patch remain mandatory. UNKNOWN never becomes 0. No Hostinger, cron, MELI real, merge, duplicate owner/routes, retry storms or speculative architecture.

Historical ERP1 429 causality remains `NOT_PROVEN`; do not encode guesses about FIFO, batching, gRPC, waits or transport as ERP2 architecture without fresh evidence.

## Certified history through H5

```text
H1-FIX1 Sale Detail browser hierarchy = GREEN
H2A Skip-to-main = GREEN
H2B1/H2B2 Typography + touch = GREEN
H3 Dashboard Human-First = GREEN
H4 Sales List Human-First = FULLY GREEN
H4_AUTOMATED_GREEN_HEAD=9f888c2f3f81c03c7f4e45c0ccb803d03a68f912
H4_AUTOMATED_CI_RUN=38979090777
H4_BROWSER_CHECKOUT_HEAD=e6eda931643b837652e4b642b0b301b905779d31
H4_BROWSER_DESKTOP_1440x900=PASS
H4_BROWSER_MOBILE_390x844=PASS
H5_CATALOG_INVENTORY_HUMAN_FIRST=FULLY CLOSED
```

## H5 — Catalog / Inventory Human-First

### Scope and truth preserved

H5 stayed inside the existing `/catalog` owner.

```text
CATALOG_OWNER=/catalog
DUPLICATE_/inventory_ROUTE=NO
BACKEND_CHANGED=NO
SCHEMA_CHANGED=NO
ROUTES_CHANGED=NO
MELI_CHANGED=NO
CSS_CHANGED=NO
JS_CHANGED=NO
UNKNOWN_TO_ZERO=NO
MISSING_INVENTORY_RENDER=Sin definir
PRIMARY_FLOW=recent product list -> select product
SECONDARY_FLOW=exact internal product ID lookup
```

Truth invariants:

- Catalog and inventory remain one surface/owner.
- Product rows remain the normal operator selection path.
- Exact-ID selection is secondary progressive disclosure.
- Missing local inventory remains `Sin definir`, never fabricated as `0`.
- Persisted zero remains visibly `0`.
- Existing authorization, tenancy, product creation and inventory persistence behavior remain unchanged.

### H5 RED contract

```text
H5_RED_CONTRACT_COMMIT=9da0cacec08f68bc1dcfc2820f09bb5deff4ab49
TEST=Tests\Integration\CatalogDesignMasterUiTest::testExactIdLookupIsSecondaryProgressiveDisclosure
EXPECTED_CONTRACT=<details class="catalog-advanced-selection"> + native summary + exact product_id input
RED_CI_RUN=38985548679
RED_RESULT=EXPECTED FAILURE
RED_FAILURE_COUNT=1
RED_TESTS=813
RED_ASSERTIONS=6829
RED_CAUSE=exact-ID lookup was still first-level panel and lacked progressive disclosure
LINT=PASS
PHPSTAN=PASS
INFRA_FAILURE=NO
```

### H5 minimal GREEN implementation

```text
H5_GREEN_CODE_COMMIT=e869821f97b0e59dbd37f05cbb4a35d27b6b75a7
PRODUCTION_FILE=app/Modules/Catalog/views/catalog.php
TEST_FILE=tests/Integration/CatalogDesignMasterUiTest.php
PRODUCTION_DELTA=+4 / -8 lines
TEST_DELTA=+22 / -0 lines
```

Implementation:

- Product list remains primary.
- Exact-ID form moved into native `<details class="catalog-advanced-selection">`.
- Summary text: `Seleccionar producto por ID`.
- No new abstraction, framework, JavaScript behavior, backend path or duplicate route.
- Change is hierarchy reduction/reordering, not a patch chain.

## H5 automated QA — certified GREEN

```text
H5_GREEN_CI_RUN=38985843177
CI_HEAD=e869821f97b0e59dbd37f05cbb4a35d27b6b75a7
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
COMPOSER_VALIDATE=PASS
DEPENDENCY_INSTALL=PASS
PHPSTAN=PASS
PHPUNIT=813 tests / 6831 assertions / 0 failures
REAL_MELI_HTTP=0
H5_AUTOMATED_FULL_GREEN=YES
```

Checkpoint-doc descendant `2613ab4315f83417d63b63a7168debbe9d443e22` also completed QA successfully on PHP 8.3 / 8.4 / 8.5 in run `38986310309`.

## H5 real-browser certification — audited PASS

Antigravity executed real Google Chrome via CDP against `/catalog` on a disposable test environment:

```text
BROWSER=Chrome/155.0.8059.84
APP_ENV=test
REAL_MELI_HTTP=0
MELI_WRITES_ENABLED=0
DB=disposable MariaDB 11.4 on erp_meli2_test
BROWSER_CHECKOUT_HEAD=2613ab4315f83417d63b63a7168debbe9d443e22
PRODUCT_GREEN_ANCESTOR=e869821f97b0e59dbd37f05cbb4a35d27b6b75a7
WORKING_TREE_BEFORE=CLEAN
WORKING_TREE_AFTER=CLEAN
DESKTOP_1440x900=PASS
MOBILE_390x844=PASS
PRODUCT_LIST_PRIMARY=PASS
NORMAL_SELECTION_PRIMARY=PASS
ID_LOOKUP_SECONDARY=PASS
ID_DISCLOSURE_COLLAPSED_DEFAULT=PASS
ID_DISCLOSURE_KEYBOARD=PASS
MISSING_INVENTORY=Sin definir PASS
ZERO_INVENTORY=0 PASS
PAGE_HORIZONTAL_OVERFLOW=NO
MOBILE_NAV=5 slots PASS
UNCAUGHT_JS_EXCEPTIONS=0
APPLICATION_5XX=0
REAL_MELI_NETWORK_CALLS=0
BUSINESS_SIDE_EFFECTS=0
DUPLICATE_/inventory_ROUTE=NO; deliberate GET /inventory probe = 404
HUMAN_FIRST=PASS
```

Evidence audit performed before accepting PASS:

- Two uploaded evidence archives contained the same 20 logical files; no content differences.
- The primary archive's 19-entry SHA-256 manifest was independently recalculated and every entry matched.
- Desktop/mobile JSON evidence agrees with screenshots and report.
- Side-effect snapshots before/after are identical: products `2`, product_inventory `1`, work_items `0`, api_usage_daily `0`, billing_periods `0`, billing_details `0`, orders `0`.
- Missing inventory fixture had no inventory row and rendered `Sin definir`.
- Persisted-zero fixture had quantity `0` and rendered `0`.
- Network evidence shows 0 real MELI calls and 0 application 5xx.
- Console contained two expected resource-level 404 entries only: `/favicon.ico` and the deliberate negative-route probe `/inventory`; uncaught JS/application exceptions remained 0.
- Primary evidence archive name: `ERP_MELI_2_0_H5_BROWSER_ANTIGRAVITY_EVIDENCE_2026-10-16.zip`.
- Primary evidence archive SHA-256: `4c986325c7444e339eacb8bf96a5d7fe8660138aa5d1778626f270e403c7edb4`.
- Evidence binaries are not committed to the repository to avoid repository noise.

## H5 final disposition

```text
H5_STARTED=YES
H5_RED=CONFIRMED
H5_MINIMAL_GREEN_IMPLEMENTED=YES
H5_KISS_AUDIT=PASS
H5_ANTI_NOISE_AUDIT=PASS
H5_ANTI_PATCH_AUDIT=PASS
H5_BACKEND_TRUTH_PRESERVED=YES
H5_AUTOMATED_PHP_8_3=GREEN
H5_AUTOMATED_PHP_8_4=GREEN
H5_AUTOMATED_PHP_8_5=GREEN
H5_AUTOMATED_FULL_GREEN=YES
H5_BROWSER_GREEN=YES
H5_FULLY_CLOSED=YES
H6_READY=YES
H6_STARTED=NO
STOP_SAFE=YES
```

## Next exact microblock — H6 preflight only

```text
NEXT=H6 BILLING HUMAN-FIRST — PREFLIGHT / TRUTH MAP / SMALLEST RED CONTRACT
H6_IMPLEMENTATION_STARTED=NO
```

Before touching code:

1. Confirm PR #19 remains OPEN / DRAFT / NOT MERGED and branch is still `ui/design-master-v1-20261015`.
2. Read current `app/Modules/Billing/BillingController.php`, `app/Modules/Billing/views/billing.php`, related repositories/handlers and all Billing UI/HTTP tests.
3. Map existing routes, permissions, period semantics, document types, states, cursor/raw/technical fields and ownership boundaries.
4. Prove the smallest Human-First defect before writing a RED test.
5. Prefer presentation/copy/progressive disclosure changes only unless evidence proves a deeper defect.
6. Human-facing labels may translate technical concepts without changing stored/API semantics, e.g. `BILL -> Factura`, `CREDIT_NOTE -> Nota crédito`, and technical states to understandable Spanish only when the underlying mapping is exact and testable.
7. Technical cursor/raw/request details should be secondary, not deleted if operationally necessary.
8. Any period UX simplification must preserve the exact existing backend/API period semantics; do not invent conversions.
9. Do not touch Financial/Work/429 pacing, retry, queue, batching or transport architecture as part of H6 UI work.
10. TDD: one RED -> diagnose -> minimal GREEN -> full QA -> browser gate -> checkpoint.

## Frozen / prohibited

No broad redesign, new frontend framework, duplicate dataset/route/owner, Hostinger, cron, real MELI mutation, PR merge, retry storms, child Work explosion, patch-chain CSS, UNKNOWN→0, or speculative 429 architecture.
