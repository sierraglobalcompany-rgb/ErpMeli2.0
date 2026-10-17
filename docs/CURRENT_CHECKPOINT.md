# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=H6D BILLING HISTORY HUMAN ACCOUNT LABEL FULLY CLOSED / BILLING RESIDUAL PREFLIGHT NEXT / STOP-SAFE**

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

## H6A — document labels

```text
RED=024cb33ec82b8b59576e03b990f8c3b8ca7976cc
PRODUCTION_GREEN=cd49a6dd4636507e2621b082e99ad7fb1f740c37
TEST_ONLY_FIX=f6648e6357b90432acb7ba66142a24b5fd04ebe7
FINAL_CI=38988788092
AUTOMATED=814 tests / 6839 assertions / 0 failures
BROWSER_CHECKOUT=a38c5c6958de3b79308ca813e7842637b0914297
FULLY_CLOSED=YES
```

Truth preserved: visible `Factura` / `Nota crédito`; machine `BILL` / `CREDIT_NOTE` unchanged.

## H6B — technical cursor progressive disclosure

```text
RED=2b970371472426f37f9ddc5626e6ea001aae4470
GREEN=6fb24bfa47b144a69e11f626d3eee68d7bb9a160
FINAL_CI=38991387270
AUTOMATED=815 tests / 6843 assertions / 0 failures
BROWSER_CHECKOUT=63fa92d4b16b1590e7a245810ea695634ed3f708
FULLY_CLOSED=YES
```

Truth preserved: progress and last reading primary; exact cursor available only under native `Detalles técnicos` disclosure.

## H6C — selected account human identity

```text
RED=c7192affa7edf164c30c3468e220f321771312a9
GREEN=e10e550a89ce7380aa49fb05d82fe6b16cddab7f
FINAL_CI=38993236102
AUTOMATED=816 tests / 6848 assertions / 0 failures
BROWSER=PASS
FULLY_CLOSED=YES
```

Truth preserved: selected summary uses existing nickname (`Cuenta principal`) while internal `account_id=10` remains exact selector/query machine value.

## H6D — history human account label — FULLY CLOSED

### Proven residual defect

`Períodos registrados` displayed numeric-only external identity `100` as primary account copy even though the same Billing page already knew the human nickname `Cuenta principal`.

### TDD / implementation trace

```text
H6D_RED=d6d6f6f951756d0407d15866b3d50f2f447651cb
H6D_RED_CI=38995007737
H6D_RED_RESULT=EXPECTED ISOLATED FAILURE
H6D_INITIAL_GREEN_WRITE=4fc9bf519c1c02f61f1ff706afa056e19fc384ce
H6D_FINAL_GREEN=c712a72c6ae805b0d28e38f687ad6556c86cb82e
H6D_FINAL_CI=38995336544
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
PHPSTAN=PASS
PHPUNIT=817 tests / 6852 assertions / 0 failures
```

Net RED→GREEN production diff: one file, `1 addition / 1 deletion`; only the history account cell changed to reuse the existing human account-label map, with `external_user_id` as truthful fallback. Period links still preserve `account_id`, `period_key`, and `document_type` exactly.

The initial full-file GREEN write accidentally dropped one quote in existing `role="alert"` markup. It was detected before certification and corrected immediately. The final RED→GREEN diff proves the accidental transcription is absent from the net product change.

### Real-browser certification — independently audited PASS

Antigravity browser checkout:

```text
HEAD=6149dfb0227ff28fbea07e0c6af17043da1e5388
AUTOMATED_GREEN_ANCESTOR=c712a72c6ae805b0d28e38f687ad6556c86cb82e
BROWSER=HeadlessChrome/155.0.8059.84 via CDP
DESKTOP=1440x900 PASS
MOBILE=390x844 PASS
WORKING_TREE_BEFORE=CLEAN
WORKING_TREE_AFTER=CLEAN
```

Independent evidence audit:

```text
UPLOADED_ZIP_SHA256=4fde1c268bd8c9cfcde9d0f275a4c90f78cdf077fe59457572cc8ad76560ca4f
ANTIGRAVITY_ORIGINAL_ZIP_SHA256=23f11a9dc87cc4823b6aa7ee08969a96aa5564514ffbb209649063b8e0faf3df
OUTER_ZIP_HASH_MATCH=NO
INTERNAL_MANIFEST_ENTRIES=19
INTERNAL_MANIFEST_HASH_MATCHES=19/19
```

The outer uploaded wrapper hash differs from Antigravity's locally generated ZIP hash, so the outer ZIP is not used as integrity authority. Every internal manifest entry was independently recalculated and matched.

Accepted browser facts:

```text
HISTORY_ACCOUNT_LABEL=Cuenta principal
HISTORY_NUMERIC_ONLY_PRIMARY_LABEL=NO
HISTORY_ACCOUNT_QUERY_ID_10=PASS
HISTORY_PERIOD_QUERY_2026_09_01=PASS
HISTORY_DOCUMENT_QUERY_BILL=PASS
CREDIT_NOTE_HISTORY_ROW=PASS
H6A_REGRESSION=PASS
H6B_REGRESSION=PASS
H6C_REGRESSION=PASS
PAGE_HORIZONTAL_OVERFLOW=NO
MOBILE_NAV=5 slots PASS
CONSOLE_APP_ERRORS=0
UNCAUGHT_JS_EXCEPTIONS=0
APPLICATION_5XX=0
REAL_MELI_NETWORK_CALLS=0
BUSINESS_SIDE_EFFECTS=0
```

Raw evidence also shows:

- desktop and mobile history cells visibly render `Cuenta principal`;
- BILL link preserves `/billing?account_id=10&period_key=2026-09-01&document_type=BILL`;
- CREDIT_NOTE link preserves account `10`, period `2026-08-01`, document `CREDIT_NOTE`;
- selected summary remains `Cuenta principal · 2026-09-01 · Factura`;
- account selector remains machine value `10` with human option `100 — Cuenta principal`;
- technical cursor remains hidden when collapsed, exact `987654321` when opened, and hidden again after keyboard close;
- DB before/after snapshots are exact matches: one account, two billing periods, zero billing details/work items/API usage/orders;
- one incidental `/favicon.ico` 404 only; no application 5xx.

Checkpoint commit `6149dfb0227ff28fbea07e0c6af17043da1e5388` CI `38995663997` also passed PHP 8.3 / 8.4 / 8.5.

```text
H6D_BROWSER_GREEN=YES
H6D_FULLY_CLOSED=YES
```

## Current disposition

```text
H6_STARTED=YES
H6A_FULLY_CLOSED=YES
H6B_FULLY_CLOSED=YES
H6C_FULLY_CLOSED=YES
H6D_FULLY_CLOSED=YES
BILLING_HUMAN_FIRST_CLOSURE=NOT_YET_DECLARED
NEXT_IMPLEMENTATION_STARTED=NO
STOP_SAFE=YES
```

## Exact resume point — Billing residual truth-map only

```text
NEXT=BILLING HUMAN-FIRST RESIDUAL PREFLIGHT / TRUTH MAP
NEW_IMPLEMENTATION_STARTED=NO
```

Before any new RED or product change:

1. Re-read current Billing view, controller and Billing UI tests at the new branch HEAD.
2. Compare the remaining first-layer UI against the canonical Human-First skill and Design Master.
3. Prove a meaningful residual defect from current source truth; do not assume there must be H6E.
4. If no meaningful defect remains, declare Billing Human-First complete instead of manufacturing work.
5. Preserve exact persisted/API semantics, authorization, account IDs, period contract and machine document values.
6. Do not touch Financial/Work/429 pacing, retry, queue, batching or transport architecture without fresh evidence.
7. If a real defect is proven: RED → root cause → minimal GREEN → full automated QA → browser gate → checkpoint.

## Frozen / prohibited

No broad redesign, new frontend framework, duplicate dataset/route/owner, Hostinger, cron, real MELI mutation, PR merge, Financial/Work/429 redesign, retry storms, child Work explosion, patch-chain CSS, UNKNOWN→0, or speculative ERP1 429 architecture.
