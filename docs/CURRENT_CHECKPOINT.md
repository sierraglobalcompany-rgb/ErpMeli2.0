# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Current verified functional SHA:** `e70c926440f6c52a37cd227f1618a832e7b7d755`  
**Remote writes:** OFF  
**REAL_MELI_HTTP:** `0`

## Current truth

V3-A is closed/green. V3-B Sales Audit remains active and is being executed in small RED → GREEN → QA → checkpoint blocks.

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
B3c1      stable durable remote_total contract
```

## Active runtime

`SalesWorkProcessor` supports only:

```text
order.sync
sales.audit
```

Unknown work remains terminal `unsupported_work_type` and cannot poison-loop.

The obsolete reconciler remains deleted from product/tests; Git preserves its history.

## B3c1 contract

`SalesAuditRepository::acceptRemoteTotal()` now enforces:

1. `remote_total` must be non-negative;
2. only the exact `company_id + account_id + run_id` capturing run using `seller-search-v1` is eligible;
3. the first observed total wins atomically only while `remote_total IS NULL`;
4. the same total may be observed again idempotently;
5. a different later total returns `false` and cannot overwrite the first value;
6. unavailable/wrong-scope/non-capturing runs fail closed;
7. no schema, table, column, lock service or migration was added.

Concurrency behavior is deliberately simple: competing first writers race on `remote_total IS NULL`; at most one update wins and later callers read the stored winner.

## B3c1 evidence

RED commit:

```text
2e6c752620189a7f2fbe218f7337311ef82ddc85
```

RED run `38015469684`, job `114104639153`:

```text
PHPSTAN=0
TESTS=176
ERRORS=2
CAUSE=SalesAuditRepository::acceptRemoteTotal() did not exist
```

GREEN functional commit:

```text
e70c926440f6c52a37cd227f1618a832e7b7d755
```

Fresh QA — run `38015557896`, job `114104910661`:

```text
PHP=8.5.11
PHPSTAN=0
PHPUNIT=176/176 PASS
ASSERTIONS=1160
MEMORY=22 MB
REAL_MELI_HTTP=0
```

## Current Sales Audit guarantees

- exact scoped durable run;
- MCO → America/Bogota, unsupported site fail-closed;
- canonical month with ±1h seller-search guard-band;
- exact bigint order IDs;
- required zoned `date_created`;
- entire page validates before persistence;
- evidence + Work completion atomic;
- duplicate evidence fails closed without overwrite;
- malformed page leaves zero partial evidence;
- no `order.sync` during CAPTURE;
- 429 defer without attempt burn;
- 5xx/transport bounded retry;
- first 401 refreshes once; second 401 terminal;
- permanent remote failures terminal;
- no replacement Work chains;
- first valid `paging.total` can now be fixed durably and later drift detected.

Current limitation: the active handler still processes only one page. It does not yet use `acceptRemoteTotal()`, enqueue a continuation page or finalize CAPTURE.

## Gates

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for active Sales Audit path |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — stable total primitive ready; multipage capture not yet wired |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 real sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

## Exact next microblock

### B3c2 — one-page continuation using stable total + next offset

Scope only:

1. RED: first CAPTURE page fixes `paging.total` through `acceptRemoteTotal()`;
2. RED: non-terminal valid page atomically enqueues exactly one next `sales.audit` Work for the same run;
3. RED: next page must carry only `run_id`, `offset`, `limit` and reuse the same run/scope;
4. RED: total drift on a later page must fail closed and create no replacement/child work;
5. RED: terminal page creates no continuation;
6. GREEN: minimum changes inside existing `SalesAuditHandler`; no pagination engine/new table/new status;
7. full QA -> checkpoint.

Do **not** mark CAPTURE complete yet in B3c2. Capture completion/VALIDATE begins only after the complete expected-set traversal contract is independently proven.

## Stop conditions

- no VALIDATE/hash/REPAIR/VERIFY/CONFIRM in B3c2;
- no Billing handler / F6A Task2 before C0;
- no merge;
- no deploy;
- no real Mercado Libre HTTP except separately authorized smoke;
- no remote writes;
- `meli_writes_enabled` remains OFF until F16.
