# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Current verified functional SHA:** `fe167945954a5f1bb38f5dce4197546e23c09152`  
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
```

## B3d1 contract

`SalesAuditRepository::persistCanonicalFingerprint()` now:

1. requires the exact scoped `capturing` run using `seller-search-v1`;
2. verifies the supplied canonical window belongs to the run/site/period;
3. derives membership from persisted `remote_date_created` with `[canonicalStartUtc, canonicalEndUtc)`;
4. excludes guard-band observations outside the month without persisting `in_period`;
5. reads canonical external order IDs in deterministic ascending order;
6. serializes IDs with `\n` and computes SHA-256;
7. persists only existing `canonical_count` + `set_hash` while both are still NULL;
8. empty canonical set hashes the empty string deterministically;
9. leaves run status `capturing`;
10. adds no table, column, status, Work type, handler or scheduler.

## B3d1 evidence

RED commit:

```text
529d967c78b1c8c80c34b7b89a832f0856c520bf
```

RED run `38017401760`, job `114110635306`:

```text
PHPSTAN=0
TESTS=182
ERRORS=2
CAUSE=SalesAuditRepository::persistCanonicalFingerprint() did not exist
```

GREEN functional commit:

```text
fe167945954a5f1bb38f5dce4197546e23c09152
```

Fresh QA — run `38017522499`, job `114111008904`:

```text
PHP=8.5.11
PHPSTAN=0
PHPUNIT=182/182 PASS
ASSERTIONS=1214
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
- malformed, drifting or incomplete terminal pages fail closed;
- canonical membership is derived locally from `remote_date_created`;
- deterministic canonical count/hash primitive exists;
- no `order.sync` during CAPTURE;
- 429 defer, 5xx/transport bounded retry, one OAuth refresh retry;
- obsolete reconciler deleted.

## Gates

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for active Sales Audit path |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — canonical fingerprint primitive verified; terminal wiring next |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 real sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

## Exact next microblock

### B3d2 — wire canonical fingerprint into terminal CAPTURE

Scope only:

1. RED: a valid terminal page atomically persists `canonical_count` + `set_hash` before Work completion;
2. RED: guard-band observations remain excluded when fingerprint is produced through the handler;
3. RED: any fingerprint persistence failure rolls back terminal-page observations and prevents Work completion;
4. GREEN: minimum call from existing `SalesAuditHandler` inside the existing completion transaction;
5. run remains `capturing`;
6. no REPAIR fan-out, no status transition, no second capture, no CONFIRM;
7. full QA → checkpoint.

## Stop conditions

- no REPAIR/VERIFY/CONFIRM in B3d2;
- no Billing handler / F6A Task2 before C0;
- no merge;
- no deploy;
- no real Mercado Libre HTTP except separately authorized smoke;
- no remote writes;
- `meli_writes_enabled` remains OFF until F16.
