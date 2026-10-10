# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Current verified functional SHA:** `305ba6fd7fe8df235c742433538ba52a6020b0b6`  
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
```

## B3d2 contract

At terminal CAPTURE, the existing `SalesAuditHandler` now:

1. validates terminal durable observation count equals stable `remote_total`;
2. inside the same existing Work completion transaction, calls `SalesAuditRepository::persistCanonicalFingerprint()`;
3. persists `canonical_count` + `set_hash` before current Work can commit as done;
4. derives canonical membership from persisted `remote_date_created`, so guard-band rows remain evidence but do not enter the canonical set;
5. if fingerprint persistence fails, the terminal-page observation rolls back and current Work fails closed with `meli_sales_audit_contract`;
6. leaves the run in `capturing`;
7. adds no schema, status, Work type, engine, scheduler or service.

## B3d2 evidence

RED commit:

```text
5f123711c685ee60c09ba5ce0d0e191f80a8e124
```

RED run `38017725349`, job `114111642713`:

```text
PHPSTAN=0
TESTS=184
FAILURES=2
CAUSES=
- terminal handler did not persist canonical fingerprint
- occupied fingerprint did not fail/rollback terminal Work
```

GREEN functional commit:

```text
305ba6fd7fe8df235c742433538ba52a6020b0b6
```

Fresh QA — run `38017833423`, job `114111967942`:

```text
PHP=8.5.11
PHPSTAN=0
PHPUNIT=184/184 PASS
ASSERTIONS=1232
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
- deterministic canonical count/hash is persisted atomically at terminal CAPTURE;
- fingerprint failure cannot silently complete terminal Work;
- run still remains `capturing` pending VALIDATE/REPAIR flow;
- no `order.sync` during CAPTURE;
- 429 defer, 5xx/transport bounded retry, one OAuth refresh retry;
- obsolete reconciler deleted.

## Gates

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for active Sales Audit path |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — CAPTURE + canonical fingerprint verified; local missing-set validation next |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 real sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

## Exact next microblock

### B3e1 — derive deterministic local missing-set only

Scope only:

1. inspect current `orders` schema and Sales repository/query ownership before coding;
2. RED: compare canonical remote audit IDs against local `orders` for the same company/account/month;
3. RED: return only remote canonical IDs missing locally;
4. RED: local orders outside the canonical month must not satisfy the audit set;
5. RED: result order is deterministic;
6. GREEN: smallest repository/query primitive; no Work enqueue;
7. no `order.sync` fan-out, no run status transition, no second capture, no CONFIRM;
8. full QA → checkpoint.

## Stop conditions

- no REPAIR fan-out in B3e1;
- no VERIFY/CONFIRM in B3e1;
- no Billing handler / F6A Task2 before C0;
- no merge;
- no deploy;
- no real Mercado Libre HTTP except separately authorized smoke;
- no remote writes;
- `meli_writes_enabled` remains OFF until F16.
