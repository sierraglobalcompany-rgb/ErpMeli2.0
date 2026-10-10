# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Current verified functional SHA:** `c52b2e4c7b8c248fb89b2b6b4899b437153e7ab8`  
**Remote writes:** OFF  
**REAL_MELI_HTTP:** `0`

## Current truth

V3-A is closed/green. V3-B Sales Audit is active and continues in small RED → GREEN → QA → checkpoint blocks.

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
B3c2      stable-total page continuation
```

## B3c2 contract

The active `SalesAuditHandler` now:

1. validates returned `paging.total`, `paging.offset` and `paging.limit`;
2. requires returned offset/limit to match the requested page;
3. fixes the first valid `remote_total` through `SalesAuditRepository::acceptRemoteTotal()`;
4. rejects later total drift before any evidence from that page can commit;
5. persists evidence, completes the current Work and enqueues the next page in one database transaction;
6. creates the next page only when `offset + limit < remote_total`;
7. computes the next offset from paging, never from result count or a short-page heuristic;
8. carries only `run_id`, `offset`, `limit` into the continuation and reuses the same company/account/run;
9. enqueues no `order.sync` during CAPTURE;
10. adds no table, column, scheduler, pagination engine or new Work type.

A total-drift page rolls back completely: no observations and no child continuation survive.

## B3c2 evidence

RED commit:

```text
e4f59b5d66c58b90df9da97709f55ec682f45c85
```

RED run `38016039856`, job `114106386902`:

```text
PHPSTAN=0
TESTS=178
FAILURES=2
CAUSES=remote_total not persisted; total drift still accepted
```

GREEN functional commit:

```text
c52b2e4c7b8c248fb89b2b6b4899b437153e7ab8
```

Fresh QA — run `38016138786`, job `114106702089`:

```text
PHP=8.5.11
PHPSTAN=0
PHPUNIT=178/178 PASS
ASSERTIONS=1179
MEMORY=22 MB
REAL_MELI_HTTP=0
```

## Current Sales Audit guarantees

- exact scoped durable run;
- MCO → America/Bogota, unsupported site fail-closed;
- canonical month with ±1h seller-search guard-band;
- exact bigint order IDs;
- required zoned `date_created`;
- page validates before persistence;
- stable `remote_total` first-writer contract;
- evidence + current Work completion + next-page enqueue are atomic;
- duplicate evidence and total drift fail closed;
- malformed/drifting page leaves zero partial evidence;
- no `order.sync` during CAPTURE;
- 429 defer without attempt burn;
- 5xx/transport bounded retry;
- first 401 refreshes once; second 401 terminal;
- permanent remote failures terminal;
- no legacy reconciler and no replacement Work chains.

Current limitation: terminal-page traversal integrity has not yet been independently proven against the durable evidence count, and CAPTURE still does not transition into VALIDATE/REPAIR/VERIFY/CONFIRM.

## Gates

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for active Sales Audit path |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — stable multipage continuation implemented; terminal traversal/validation pending |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 real sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

## Exact next microblock

### B3c3 — terminal CAPTURE traversal integrity only

Scope only:

1. RED: a terminal page must enqueue no further CAPTURE page;
2. RED: after terminal-page persistence, durable `sales_audit_orders` count for the run must equal stable `remote_total` before CAPTURE can be considered traversed;
3. RED: a count mismatch must fail closed/roll back the terminal page rather than silently accept incomplete coverage;
4. GREEN: minimum repository/handler change only; no new schema/status/engine;
5. keep the run in `capturing` during this microblock — do not begin local VALIDATE yet;
6. full QA → checkpoint.

Only after B3c3 may the next block begin local set VALIDATE and choose the existing `repairing`/`confirming` path.

## Stop conditions

- no VALIDATE/hash/REPAIR/VERIFY/CONFIRM in B3c3;
- no Billing handler / F6A Task2 before C0;
- no merge;
- no deploy;
- no real Mercado Libre HTTP except separately authorized smoke;
- no remote writes;
- `meli_writes_enabled` remains OFF until F16.
