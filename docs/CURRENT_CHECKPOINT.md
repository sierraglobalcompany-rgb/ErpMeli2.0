# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Current verified SHA:** `ec7ee88e71c709039f4a1dfa5a5499dbcee8e445`  
**Remote writes:** OFF  
**REAL_MELI_HTTP:** `0`

## Current truth

V3-A is closed/green. V3-B Sales Audit is active and continues in small verified blocks.

Closed:

```text
B0        bigint-safe orders.search
B1        two-table Sales Audit schema
B2        MCO temporal contract + durable capturing run
B3a       durable observation primitive
B3b1      one validated/atomic CAPTURE page
B3b2a     429/5xx/transport/OAuth/permanent failure semantics
B3b2b1    sales.audit activated in SalesWorkProcessor
B3b2b2    obsolete orders reconciler deleted
```

## Active runtime

`SalesWorkProcessor` supports only:

```text
order.sync
sales.audit
```

Unknown work remains terminal `unsupported_work_type` and cannot poison-loop.

The obsolete reconciler has been removed, not archived:

```text
DELETE app/Modules/Sales/ReconcileOrders/ReconcileOrdersHandler.php
DELETE tests/Integration/ReconcileOrdersHandlerTest.php
DELETE tests/Integration/ReconcileMalformedWorkTest.php
```

`bin/work.php`, `SalesWorkProcessorTest`, `WorkCliEntrypointTest` and the generic poison-loop test now use only the active Sales Audit path. The current Git tree contains no `ReconcileOrders*` path.

## B3b2b2 evidence

Deletion commits:

```text
2650412881dadffa571e95253ed04c434d5e397c  delete product reconciler
d9bda02e5ed7e22993c290946266cfaf1edbdecd  delete reconciler tests
c41159198c4060508c50782c126576884b9646b1  remove legacy CLI assertions
ec7ee88e71c709039f4a1dfa5a5499dbcee8e445  remove legacy processor fixture
```

Fresh QA — run `38015240935`, job `114103934500`:

```text
PHP=8.5.11
PHPSTAN=0
PHPUNIT=174/174 PASS
ASSERTIONS=1152
MEMORY=22 MB
REAL_MELI_HTTP=0
```

The QA syntax scan contains no deleted reconciler source/test.

## Current Sales Audit guarantees

- exact scoped durable run;
- MCO → America/Bogota, unsupported site fail-closed;
- canonical month with ±1h seller-search guard-band;
- exact bigint order IDs;
- required zoned `date_created`;
- entire page validates before persistence;
- evidence + Work completion atomic;
- duplicates fail closed without overwrite;
- malformed page leaves zero partial evidence;
- no `order.sync` during CAPTURE;
- 429 defer without attempt burn;
- 5xx/transport bounded retry;
- first 401 refreshes once; second 401 terminal;
- permanent remote failures terminal;
- no replacement Work chains.

Current limitation: CAPTURE processes only one page. It does not yet persist/verify a stable remote `paging.total`, enqueue continuation pages or finalize CAPTURE.

## Gates

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for active Sales Audit path |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — one-page CAPTURE only |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 real sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

## Exact next microblock

### B3c1 — durable stable `remote_total` contract only

No handler pagination changes yet.

RED must prove repository behavior:

1. first observed remote total on a capturing run is stored;
2. observing the same total again is accepted idempotently;
3. a different later total is rejected and cannot overwrite the original total;
4. negative/invalid totals fail closed;
5. scope/run state remains enforced;
6. no new table/column/migration — reuse existing `sales_audit_runs.remote_total`.

Then minimum GREEN -> full QA -> checkpoint.

Only after B3c1:

```text
B3c2 = one-page continuation using stable total + next offset
```

## Stop conditions

- no VALIDATE/hash/REPAIR/VERIFY/CONFIRM yet;
- no Billing handler / F6A Task2 before C0;
- no merge;
- no deploy;
- no real Mercado Libre HTTP except separately authorized smoke;
- no remote writes;
- `meli_writes_enabled` remains OFF until F16.
