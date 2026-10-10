# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Current verified functional SHA:** `c06c46906f87c884e7c5ab21356fd48ca7612779`  
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
B3e2      guarded capturing → repairing transition
B3e3a     repairing-phase missing-set read
```

## B3e3a contract

`SalesAuditRepository::repairingMissingCanonicalOrderIds()` now:

1. requires the exact company/account/run scope in status `repairing`;
2. requires the account to remain connected and `remote_total`, `canonical_count`, `set_hash` to exist;
3. validates period/site/fingerprint before reading repair candidates;
4. derives the canonical MCO month from the repairing run itself;
5. returns only canonical remote audit IDs that are still missing from local `orders` for the same company/account/month;
6. returns IDs deterministically in ascending order;
7. a `capturing` run cannot use this repairing-phase read path;
8. wrong scope/non-repairing status fails closed;
9. `captureContext()` remains unchanged and capture-only;
10. enqueues no Work and changes no status/schema.

## B3e3a evidence

RED commit:

```text
9d72b466a02369d2852054364861553f68b03a18
```

RED run `38018669116`, job `114114587125`:

```text
PHPSTAN=0
TESTS=192
ERRORS=1
FAILURES=2
CAUSE=SalesAuditRepository::repairingMissingCanonicalOrderIds() did not exist
```

GREEN functional commit:

```text
c06c46906f87c884e7c5ab21356fd48ca7612779
```

Fresh QA — run `38018814214`, job `114115030412`:

```text
PHP=8.5.11
PHPSTAN=0
PHPUNIT=192/192 PASS
ASSERTIONS=1272
MEMORY=22 MB
REAL_MELI_HTTP=0
```

## Repair identity already fixed by existing product contract

```text
type=order.sync
resource_key=<external_order_id>
logical_identity=order.sync:<external_order_id>
payload={order_id:<external_order_id>}
```

`WorkRepository::enqueue()` already provides active-work dedupe through logical identity. No repair queue/table is justified.

## Gates

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for active Sales Audit path |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — REPAIR phase can now read missing IDs safely; bounded fan-out contract next |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 real sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

## Exact next microblock

### B3e3b — bounded repair fan-out contract

Before coding, search authority/plan/history for an already approved concrete fan-out bound. Do not invent a new number if one is already specified.

Scope only:

1. establish the existing/approved bound if documented;
2. RED: repairing-phase fan-out may enqueue only missing canonical IDs using the existing `order.sync` identity/payload contract;
3. RED: active duplicate `order.sync:<id>` remains deduped by existing Work semantics;
4. RED: a single fan-out action cannot enqueue more than the approved bound;
5. GREEN: minimum reuse of existing WorkRepository; no repair table/queue/engine;
6. no VERIFY, second capture or CONFIRM yet;
7. full QA → checkpoint.

## Stop conditions

- do not invent a numeric bound without repository authority/evidence;
- no VERIFY/CONFIRM in B3e3b;
- no Billing handler / F6A Task2 before C0;
- no merge;
- no deploy;
- no real Mercado Libre HTTP except separately authorized smoke;
- no remote writes;
- `meli_writes_enabled` remains OFF until F16.
