# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=H6D BILLING HISTORY HUMAN ACCOUNT LABEL AUTOMATED GREEN / BROWSER NOT CERTIFIED / STOP-SAFE**

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

## Certified Human-First history

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

`Cursor confirmado` was technical implementation detail exposed in the first information layer. Human-First requires it to remain available but secondary; `Progreso persistido` and `Última lectura persistida (UTC)` remain primary operational facts.

```text
H6B_RED_COMMIT=2b970371472426f37f9ddc5626e6ea001aae4470
H6B_RED_CI_RUN=38991126596
H6B_RED_RESULT=EXPECTED FAILURE
H6B_GREEN_COMMIT=6fb24bfa47b144a69e11f626d3eee68d7bb9a160
H6B_FINAL_CI_RUN=38991387270
H6B_AUTOMATED_RESULT=815 tests / 6843 assertions / 0 failures
H6B_BROWSER_CHECKOUT_HEAD=63fa92d4b16b1590e7a245810ea695634ed3f708
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
PHPSTAN=PASS
H6B_BROWSER_GREEN=YES
H6B_FULLY_CLOSED=YES
```

Accepted browser facts:

- native `<details class="billing-technical-details">` collapsed by default;
- keyboard open/close PASS;
- exact cursor hidden/open/hidden PASS;
- H6A labels and machine semantics preserved;
- desktop 1440x900 and mobile 390x844 PASS;
- no page horizontal overflow;
- mobile navigation exactly 5 slots;
- application 5xx = 0;
- real MELI calls = 0;
- business side effects = 0.

## H6C — Billing Human-First selected account identity — FULLY CLOSED

### Proven defect

The selected Billing summary exposed the internal database PK as primary human language:

```text
Cuenta 10 · 2026-09-01 · Factura
```

The same page already had human account identity (`external_user_id` and `nickname`). The defect was therefore presentation-only.

Human-First rule: internal IDs remain machine truth and must not become the operator's primary mental model when an existing human identity is available.

### TDD trace

```text
H6C_RED_COMMIT=c7192affa7edf164c30c3468e220f321771312a9
H6C_RED_CI_RUN=38993014923
H6C_RED_RESULT=EXPECTED ISOLATED FAILURE
H6C_RED_TESTS=816
H6C_RED_ASSERTIONS=6845
H6C_RED_FAILURES=1
H6C_GREEN_COMMIT=e10e550a89ce7380aa49fb05d82fe6b16cddab7f
H6C_FINAL_CI_RUN=38993236102
H6C_AUTOMATED_RESULT=816 tests / 6848 assertions / 0 failures
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
PHPSTAN=PASS
REAL_MELI_HTTP=0
```

Minimal implementation:

- selected summary now uses existing human account label, e.g. `Cuenta principal`;
- internal `account_id=10` remains exact machine value in selector/query;
- nickname fallback uses existing external user identity, not an invented label or zero;
- no new query;
- no controller, schema, route, CSS or JS change;
- no Work, Financial, queue, retry, pacing, batching, transport or 429 architecture change.

### Real-browser certification — independently audited PASS

Antigravity executed Chrome 155 via CDP against `/billing` on exact checkout:

```text
H6C_BROWSER_CHECKOUT_HEAD=e10e550a89ce7380aa49fb05d82fe6b16cddab7f
AUTOMATED_GREEN_ANCESTOR=e10e550a89ce7380aa49fb05d82fe6b16cddab7f
BROWSER=HeadlessChrome/155.0.8059.84 via CDP
DESKTOP=1440x900 PASS
MOBILE=390x844 PASS
WORKING_TREE_BEFORE=CLEAN
WORKING_TREE_AFTER=CLEAN
```

Independent evidence audit before accepting PASS:

- received uploaded wrapper ZIP SHA-256: `01b8c6f6b9d01c310f66a35dcb0b00c8225bc8c299abcce91528261452ae4893`;
- Antigravity reported the locally generated original ZIP SHA-256 as `d08a0b7cd689fa6aed2d2c8595bd1a137ec120291b8b81cbf212bdc8f0a7e7b6`;
- those outer ZIP hashes do not match, so the reported original ZIP hash is NOT treated as authority for the uploaded wrapper;
- internal `manifest-sha256.txt` contains 16 logical entries;
- all 16 internal SHA-256 values were independently recalculated from the uploaded ZIP and matched exactly;
- report, JSON, screenshots, network, console, fixture and before/after DB snapshots are mutually coherent;
- screenshots independently inspected: desktop/mobile show `Cuenta principal · 2026-09-01 · Factura`; collapsed state hides cursor, open state shows exact `987654321`;
- desktop `scrollWidth=1425`, `clientWidth=1425`; mobile `scrollWidth=390`, `clientWidth=390`; no page horizontal overflow;
- account selector machine value remains `10`, while visible option is `100 — Cuenta principal`;
- selected/query semantics remain `account_id=10`, `period_key=2026-09-01`, `document_type=BILL`;
- H6A remains preserved: `Factura` / `Nota crédito`, with machine `BILL` / `CREDIT_NOTE` in options/query;
- H6B remains preserved: technical details collapsed by default, keyboard open/close PASS, exact cursor hidden/open/hidden;
- mobile navigation contains exactly 5 slots and does not overlap disclosure;
- network evidence: 16 responses, application 5xx = 0, real MELI network calls = 0, one incidental `/favicon.ico` 404 only;
- console: application errors = 0, uncaught JS exceptions = 0, console messages = 0;
- DB before/after snapshots are exact matches: `meli_accounts=1`, `billing_periods=2`, `billing_details=0`, `work_items=0`, `api_usage_daily=0`, `orders=0`; business side effects = 0;
- disposable server/DB/runtime were torn down and working tree ended CLEAN.

Harness note:

For the additional `CREDIT_NOTE` case, one generic boolean named `humanAccountLabelVisible` is `false` because that check was keyed to the BILL phrase. The same evidence record contains the correct rendered summary `Cuenta principal · 2026-08-01 · Nota crédito`, with internal account ID absent from primary text and machine query preserved. This is a harness-specific boolean mismatch, not a product failure and not the primary H6C gate.

Browser gates accepted:

```text
H6C_BROWSER_RESULT=PASS
BILLING_HTTP_200=PASS
HUMAN_ACCOUNT_LABEL_VISIBLE=PASS
HUMAN_ACCOUNT_LABEL=Cuenta principal
INTERNAL_ACCOUNT_ID_PRIMARY_VISIBLE=NO
ACCOUNT_MACHINE_VALUE_10=PASS
ACCOUNT_QUERY_ID_10=PASS
ACCOUNT_SELECTOR_HUMAN_LABEL=PASS
H6A_HUMAN_LABELS_PRESERVED=PASS
H6A_MACHINE_VALUES_PRESERVED=PASS
H6B_PROGRESS_PRIMARY_VISIBLE=PASS
H6B_LAST_READ_PRIMARY_VISIBLE=PASS
H6B_TECHNICAL_DETAILS_PRESENT=PASS
H6B_TECHNICAL_DETAILS_COLLAPSED_DEFAULT=PASS
H6B_CURSOR_HIDDEN_WHEN_COLLAPSED=PASS
H6B_KEYBOARD_OPEN=PASS
H6B_CURSOR_VISIBLE_WHEN_OPEN=PASS
H6B_CURSOR_EXACT_VALUE_PRESERVED=PASS
H6B_KEYBOARD_CLOSE=PASS
H6B_CURSOR_HIDDEN_AFTER_CLOSE=PASS
PERIOD_SEMANTICS_PRESERVED=PASS
PAGE_HORIZONTAL_OVERFLOW=NO
MOBILE_NAV=5 slots PASS
CONSOLE_APP_ERRORS=0
UNCAUGHT_JS_EXCEPTIONS=0
APPLICATION_5XX=0
REAL_MELI_NETWORK_CALLS=0
BUSINESS_SIDE_EFFECTS=0
BACKEND_CHANGED=NO
CONTROLLER_CHANGED=NO
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
H6C_BROWSER_GREEN=YES
H6C_FULLY_CLOSED=YES
```

## H6D — Billing history human account label — AUTOMATED GREEN / BROWSER PENDING

### Proven residual defect

After H6C, the selected summary and account selector used the existing human nickname, but `Períodos registrados` still rendered only the external numeric identity:

```text
Cuenta -> 100
```

This was inconsistent with the same page's available human identity (`Cuenta principal`). The internal `account_id` already remained safely in the period link/query, so no backend or data-model change was required.

The canonical Human-First law and Design Master require primary copy to use human labels while preserving raw/internal identifiers as machine truth. There is no Billing page override authorizing the numeric-only presentation.

### H6D RED — confirmed

```text
H6D_RED_COMMIT=d6d6f6f951756d0407d15866b3d50f2f447651cb
H6D_RED_CI_RUN=38995007737
H6D_RED_RESULT=EXPECTED ISOLATED FAILURE
H6D_RED_TESTS=817
H6D_RED_ASSERTIONS=6850
H6D_RED_FAILURES=1
H6D_FAILING_TEST=Tests\Integration\BillingDesignMasterUiTest::testBillingPeriodHistoryUsesHumanAccountLabelWithoutChangingMachineQuery
```

Observed RED truth:

```html
<td data-label="Cuenta">100</td>
```

Expected Human-First truth:

```html
<td data-label="Cuenta">Cuenta principal</td>
```

The same rendered row retained:

```text
/billing?account_id=10&period_key=2026-09-01&document_type=BILL
```

Composer validation and PHPStan passed in the RED run; the single PHPUnit failure was exactly the new H6D expectation. Existing negative-path Slim stack output remained expected test noise and was not a separate failure.

### Minimal GREEN

Production change is presentation-only in:

```text
app/Modules/Billing/views/billing.php
```

The history account cell now reuses the already-built `$accountLabels[(int) account_id]` map, with the existing `external_user_id` as fallback.

No new query, controller, schema, route, CSS, JS, dependency, Work, Financial, queue, retry, pacing, batching, transport or 429 architecture change.

Commit trace:

```text
H6D_INITIAL_GREEN_WRITE=4fc9bf519c1c02f61f1ff706afa056e19fc384ce
H6D_FINAL_GREEN_COMMIT=c712a72c6ae805b0d28e38f687ad6556c86cb82e
```

Forensic note: the initial full-file write introduced one accidental missing quote in existing `role="alert"` markup. It was detected before GREEN certification and corrected immediately. Comparing RED `d6d6f6f...` to final GREEN `c712a72...`, GitHub reports exactly one production file changed with `1 addition / 1 deletion`; therefore the net production diff is only the intended history-account presentation change.

### Automated GREEN — certified

```text
H6D_FINAL_CI_RUN=38995336544
H6D_FINAL_GREEN_HEAD=c712a72c6ae805b0d28e38f687ad6556c86cb82e
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
COMPOSER_VALIDATE=PASS
PHPSTAN=PASS
PHPUNIT=817 tests / 6852 assertions / 0 failures
REAL_MELI_HTTP=0
H6D_AUTOMATED_GREEN=YES
H6D_BROWSER_GREEN=NO
H6D_FULLY_CLOSED=NO
```

## Current disposition

```text
H6_STARTED=YES
H6A_FULLY_CLOSED=YES
H6B_FULLY_CLOSED=YES
H6C_FULLY_CLOSED=YES
H6D_PREFLIGHT=COMPLETE
H6D_RED=CONFIRMED
H6D_MINIMAL_GREEN=IMPLEMENTED
H6D_FINAL_AUTOMATED_GREEN=YES
H6D_BROWSER_GREEN=NO
H6D_FULLY_CLOSED=NO
BILLING_HUMAN_FIRST_CLOSURE=NOT_YET_DECLARED
NEXT_BILLING_WORK_STARTED=NO
STOP_SAFE=YES
```

## Exact resume point — H6D browser certification only

```text
NEXT=H6D REAL-BROWSER CERTIFICATION
AUTOMATED_GREEN_HEAD=c712a72c6ae805b0d28e38f687ad6556c86cb82e
NEW_IMPLEMENTATION_STARTED=NO
```

Required real-browser proof on `/billing` before closing H6D:

1. desktop 1440x900 PASS;
2. mobile 390x844 PASS;
3. `Períodos registrados` shows human account label `Cuenta principal`, not numeric-only `100` as the primary account cell;
4. row link/query preserves exact machine `account_id=10`, `period_key`, and `document_type`;
5. H6A labels remain `Factura` / `Nota crédito` with machine values preserved;
6. H6B technical cursor remains secondary/collapsed by default and keyboard-operable;
7. H6C selected summary remains human-first;
8. no page horizontal overflow;
9. mobile bottom navigation exactly 5 slots;
10. application JS errors = 0;
11. application HTTP 5xx = 0;
12. real MELI network calls = 0;
13. business side effects = 0;
14. working tree before/after CLEAN;
15. no backend/schema/routes/CSS/JS/Work/Financial/429 architecture changes.

On the first real browser defect: capture evidence, identify root cause, do not repair in the same certification run, STOP.

After browser PASS: update this checkpoint to `H6D FULLY CLOSED`, then perform one new Billing residual preflight. Do not assume another H6 subphase must exist. If no meaningful residual Human-First defect remains, declare Billing Human-First complete and move to the next planned product area.

## Frozen / prohibited

No broad redesign, new frontend framework, duplicate dataset/route/owner, Hostinger, cron, real MELI mutation, PR merge, Financial/Work/429 redesign, retry storms, child Work explosion, patch-chain CSS, UNKNOWN→0, or speculative ERP1 429 architecture.
