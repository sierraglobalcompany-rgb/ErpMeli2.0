# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Current verified functional SHA:** `9c5791f09341fd384ae120719c00e5f04719492c`  
**Remote writes:** OFF  
**REAL_MELI_HTTP:** `0`

## Current truth

V3-A is closed/green. V3-B Sales Audit remains active and is executed in small RED → GREEN → QA → checkpoint blocks.

Closed and verified:

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
B3d1      canonical local set fingerprint primitive
B3d2      terminal CAPTURE fingerprint wiring
B3e1      deterministic local missing-set primitive
```

## B3e1 contract

`SalesAuditRepository::missingCanonicalOrderIds()` now:

1. requires the exact scoped `capturing` run;
2. refuses to operate until `remote_total`, `canonical_count` and `set_hash` exist;
3. derives the canonical MCO window from the run itself;
4. considers only remote audit evidence whose `remote_date_created` belongs to the canonical month;
5. considers a local order present only when the same external ID exists for the same company/account and local `orders.date_created` is inside the same canonical month;
6. therefore a same-ID local row outside the month does not incorrectly satisfy the historical audit;
7. guard-band remote observations outside the month are not repair candidates;
8. returns only missing external IDs in deterministic ascending order;
9. changes no Work, no run status and enqueues nothing;
10. adds no table, column, class, engine or scheduler.

## B3e1 evidence

RED commit:

```text
07059b6066b3b7b004d67dc3925502e94e2730d5
```

RED run `38018068832`, job `114112708451`:

```text
PHPSTAN=0
TESTS=186
ERRORS=1
FAILURES=1
CAUSE=SalesAuditRepository::missingCanonicalOrderIds() did not exist
```

GREEN functional commit:

```text
9c5791f09341fd384ae120719c00e5f04719492c
```

Fresh QA — run `38018180908`, job `114113062510`:

```text
PHP=8.5.11
PHPSTAN=0
PHPUNIT=186/186 PASS
ASSERTIONS=1242
MEMORY=22 MB
REAL_MELI_HTTP=0
```

## Current Sales Audit guarantees

- exact scoped durable run;
- MCO → America/Bogota; unsupported site fail-closed;
- canonical month with ±1h seller-search guard-band;
- exact bigint order IDs and zoned `date_created`;
- full page validation before persistence;
- stable `remote_total` and coherent paging;
- one-page continuation with atomic evidence/work/next-page write;
- terminal durable observed count must equal stable remote total;
- canonical fingerprint persists atomically at terminal CAPTURE;
- deterministic missing-local diff exists and is month-scoped on both remote and local truth;
- no repair Work has been enqueued yet;
- run remains `capturing`;
- 429 defer, 5xx/transport bounded retry, one OAuth refresh retry;
- obsolete reconciler deleted.

## Gates

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for active Sales Audit path |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — CAPTURE and local missing-set VALIDATE primitive verified; REPAIR transition/fan-out not yet implemented |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 real sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

## Exact next microblock

### B3e2 — REPAIR transition contract only

Before coding, inspect the existing run statuses and Work idempotency contract. Keep this block smaller than fan-out.

Scope only:

1. RED: a fingerprinted `capturing` run with one or more missing canonical local IDs may transition atomically to `repairing`;
2. RED: a run without a fingerprint or with no missing IDs must not be blindly moved to `repairing`;
3. RED: wrong scope/status fails closed;
4. GREEN: minimum repository transition primitive only;
5. do not enqueue `order.sync` yet;
6. no VERIFY, second capture or CONFIRM;
7. full QA → checkpoint.

## Stop conditions

- no repair fan-out in B3e2;
- no VERIFY/CONFIRM in B3e2;
- no Billing handler / F6A Task2 before C0;
- no merge;
- no deploy;
- no real Mercado Libre HTTP except separately authorized smoke;
- no remote writes;
- `meli_writes_enabled` remains OFF until F16.
