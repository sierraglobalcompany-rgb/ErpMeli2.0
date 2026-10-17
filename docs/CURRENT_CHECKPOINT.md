# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=H6E BILLING DISABLED-ACCOUNT HISTORY HUMAN LABEL / RED CONFIRMED / GREEN NOT IMPLEMENTED / STOP-SAFE**

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
docs/ERP2_AUTHORITY.md
docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md
design-system/erp-meli-2-0/MASTER.md
docs/UX_HUMAN_FIRST_MASTER_AUDIT_AND_IMPROVEMENT_PLAN_2026-10-16.md
```

Historical ERP1 429 causality remains `NOT_PROVEN`. Do not encode guesses about FIFO, batching, gRPC, waits, retries, pacing or transport as ERP2 architecture without fresh evidence.

## Certified Human-First history

```text
H1_FIX1_SALE_DETAIL=GREEN
H2A_SKIP_TO_MAIN=GREEN
H2B_TYPOGRAPHY_TOUCH=GREEN
H3_DASHBOARD=GREEN
H4_SALES_LIST=FULLY_CLOSED
H5_CATALOG_INVENTORY=FULLY_CLOSED
H6A_BILLING_DOCUMENT_LABELS=FULLY_CLOSED
H6B_BILLING_TECHNICAL_CURSOR=FULLY_CLOSED
H6C_BILLING_SELECTED_ACCOUNT_IDENTITY=FULLY_CLOSED
H6D_BILLING_HISTORY_ACCOUNT_IDENTITY=FULLY_CLOSED
```

## H6A–H6D condensed trace

```text
H6A_FINAL_CI=38988788092
H6A_AUTOMATED=814 tests / 6839 assertions / 0 failures
H6A_BROWSER=PASS

H6B_GREEN=6fb24bfa47b144a69e11f626d3eee68d7bb9a160
H6B_FINAL_CI=38991387270
H6B_AUTOMATED=815 tests / 6843 assertions / 0 failures
H6B_BROWSER=PASS

H6C_GREEN=e10e550a89ce7380aa49fb05d82fe6b16cddab7f
H6C_FINAL_CI=38993236102
H6C_AUTOMATED=816 tests / 6848 assertions / 0 failures
H6C_BROWSER=PASS

H6D_RED=d6d6f6f951756d0407d15866b3d50f2f447651cb
H6D_FINAL_GREEN=c712a72c6ae805b0d28e38f687ad6556c86cb82e
H6D_FINAL_CI=38995336544
H6D_AUTOMATED=817 tests / 6852 assertions / 0 failures
H6D_BROWSER_CHECKOUT_HEAD=6149dfb0227ff28fbea07e0c6af17043da1e5388
H6D_BROWSER=PASS
H6D_FULLY_CLOSED=YES
```

H6D browser evidence was independently audited: uploaded wrapper ZIP outer hash differed from Antigravity original ZIP hash, but all `19/19` internal SHA-256 manifest entries matched independently; desktop/mobile, DOM/JSON, network, console and before/after DB snapshots were coherent. No application 5xx, no real MELI calls, no business side effects.

H6D closure documentation commit:

```text
H6D_CLOSURE_DOC_COMMIT=d0fa387d11164357b5095dc8cba48996bdd483c9
```

## H6E — disabled-account history human label — RED CONFIRMED

### Proven residual defect

The H6D implementation builds human account labels from connected accounts first, but Billing history intentionally includes persisted periods for accounts that may later become `disabled`.

Repository lifecycle truth proves this state is valid and persistent:

- disconnect changes account status to `disabled`;
- persisted Billing history prevents hard deletion of that disabled account;
- therefore a disabled account with Billing periods can legitimately remain in `Períodos registrados`.

Current controller history query retrieves only:

```text
a.external_user_id
```

for period rows, while `nickname` is loaded only in the separate connected-account selector query. Thus a disabled account with a real nickname falls back to numeric-only external identity in history.

Reproduced current output:

```html
<td data-label="Cuenta">100</td>
```

Expected Human-First output when persisted nickname exists:

```html
<td data-label="Cuenta">Cuenta histórica</td>
```

Machine truth must remain:

```text
account_id=10
```

in the period link/query.

### H6E RED

Test-only commit:

```text
H6E_RED_COMMIT=963bbe429b62b87e645f5b4bc6843a4c132951da
H6E_RED_CI_RUN=38996880293
```

Added only:

```text
tests/Integration/BillingDisabledAccountHumanLabelTest.php
```

Expected isolated RED confirmed in PHP 8.3 / 8.4 / 8.5.

PHP 8.3 full evidence:

```text
PHPSTAN=PASS
PHPUNIT_TESTS=818
PHPUNIT_ASSERTIONS=6854
PHPUNIT_FAILURES=1
ONLY_FAILURE=Tests\Integration\BillingDisabledAccountHumanLabelTest::testBillingHistoryKeepsHumanNicknameForDisabledAccountWithoutChangingMachineQuery
OBSERVED_HISTORY_LABEL=100
EXPECTED_HISTORY_LABEL=Cuenta histórica
MACHINE_QUERY_ACCOUNT_ID_10=PRESERVED
```

The existing Slim negative-path traces remain expected test output and are not separate failures.

### Root cause

```text
ROOT_CAUSE=Billing history query joins meli_accounts but selects external_user_id only; nickname is unavailable for disabled accounts because the separate account selector query filters status='connected'.
```

Minimal GREEN direction is already proven but **NOT IMPLEMENTED**:

1. add `a.nickname` to the existing Billing periods query;
2. when building the period account label map, use non-empty persisted nickname;
3. fallback truthfully to existing `external_user_id` when nickname is empty;
4. preserve period links and machine `account_id` exactly;
5. no new query, schema, route, CSS, JS, dependency, Work, Financial, queue, retry, pacing, batching, transport or 429 architecture change.

Important stop-state:

```text
H6E_RED=CONFIRMED
H6E_GREEN_IMPLEMENTED=NO
PRODUCTION_CHANGED_FOR_H6E=NO
H6E_AUTOMATED_GREEN=NO
H6E_BROWSER_GREEN=NO
H6E_FULLY_CLOSED=NO
NEXT_BILLING_WORK_STARTED=NO
STOP_SAFE=YES
```

## Exact resume point

```text
NEXT=H6E MINIMAL GREEN ONLY
RED_COMMIT=963bbe429b62b87e645f5b4bc6843a4c132951da
RED_CI=38996880293
```

On resume:

1. re-read `BillingController.php`, `billing.php`, and the H6E RED test at branch HEAD;
2. implement the root-cause fix only;
3. verify diff is minimal;
4. run full CI PHP 8.3 / 8.4 / 8.5 + PHPStan + PHPUnit;
5. only after automated GREEN decide whether real-browser evidence is necessary for this historical disabled-account case;
6. do not open another Billing block until H6E is closed or explicitly stopped.

## Frozen / prohibited

No broad redesign, new frontend framework, duplicate dataset/route/owner, Hostinger, cron, real MELI mutation, PR merge, Financial/Work/429 redesign, retry storms, child Work explosion, patch-chain CSS, UNKNOWN→0, or speculative ERP1 429 architecture.
