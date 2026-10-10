# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Current verified functional SHA:** `e7d1e65fe60305698f344a0e2875bd4dd0f4a6a2`  
**Previous pause checkpoint:** `0929060ca4a548373eb273b17b607eb65a7701e1`  
**Remote writes:** OFF  
**REAL_MELI_HTTP:** `0`

## Audit after pause

The apparent blockage was only timing: B3c3 QA completed after the pause checkpoint was written. No product commit exists after `e7d1e65f...`; the branch then only added the pause checkpoint document.

Verified B3c3 run:

```text
RUN=38016478682
JOB=114107765815
PHP=8.5.11
PHPSTAN=0
PHPUNIT=180/180 PASS
ASSERTIONS=1198
MEMORY=22 MB
REAL_MELI_HTTP=0
```

The pause checkpoint itself also passed QA:

```text
CHECKPOINT_SHA=0929060ca4a548373eb273b17b607eb65a7701e1
RUN=38016523951
STATUS=SUCCESS
```

Therefore B3c3 is now CLOSED/GREEN.

## Closed V3-B blocks

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
B3c3      terminal traversal integrity by durable observed count
```

## Verified B3c3 contract

At terminal CAPTURE:

1. terminal-page observations are inserted inside the existing Work completion transaction;
2. durable `sales_audit_orders` count is compared with the stable `remote_total`;
3. exact equality allows terminal Work completion;
4. mismatch throws inside the transaction, rolling back terminal-page observations and preventing silent incomplete capture;
5. prior committed pages remain intact;
6. run deliberately remains `capturing`;
7. no schema, status, engine, scheduler, table or new Work type was added.

## Current Sales Audit guarantees

- exact scoped durable run;
- MCO → America/Bogota; unsupported site fail-closed;
- canonical month with ±1h seller-search guard-band;
- exact bigint order IDs;
- required zoned `date_created`;
- full page validation before persistence;
- stable `remote_total` first-writer contract;
- exact returned paging offset/limit validation;
- one-page-at-a-time continuation from stable total;
- evidence + Work completion + continuation atomic;
- duplicate evidence and total drift fail closed;
- terminal durable observation count must equal remote total;
- malformed/drifting/incomplete terminal pages cannot silently commit partial evidence;
- no `order.sync` during CAPTURE;
- 429 defer without attempt burn;
- 5xx/transport bounded retry;
- first 401 refreshes once; second 401 terminal;
- permanent remote failures terminal;
- obsolete reconciler deleted.

## Gates

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for active Sales Audit path |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — CAPTURE traversal integrity verified; local canonical-set validation next |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 real sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

## Exact next microblock

### B3d1 — derive canonical local set fingerprint

Scope only:

1. RED: canonical membership is derived from persisted `remote_date_created`, not persisted as `in_period`;
2. RED: guard-band observations outside the canonical month are excluded;
3. RED: `canonical_count` equals canonical member count;
4. RED: `set_hash` is SHA-256 of canonical external order IDs in deterministic sorted order;
5. RED: empty canonical set has deterministic SHA-256 fingerprint;
6. GREEN: smallest change in existing Sales Audit code; no new table/column/status/Work type;
7. no REPAIR fan-out, no second capture, no CONFIRM in B3d1;
8. full QA -> checkpoint.

## Stop conditions

- no REPAIR/VERIFY/CONFIRM in B3d1;
- no Billing handler / F6A Task2 before C0;
- no merge;
- no deploy;
- no real Mercado Libre HTTP except separately authorized smoke;
- no remote writes;
- `meli_writes_enabled` remains OFF until F16.
