# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Current verified functional SHA:** `6493b4fbe841177896f279e5acb8de53d6d1d265`  
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
```

## B3e2 contract

`SalesAuditRepository::transitionToRepairingIfMissing()` now:

1. requires the exact scoped connected `capturing` run;
2. derives the canonical MCO month from the run;
3. performs one atomic `UPDATE ... EXISTS`;
4. requires `remote_total`, `canonical_count` and `set_hash` to exist;
5. transitions only if at least one canonical remote audit ID is still missing from local `orders` for the same company/account/month;
6. a fingerprinted run with no missing local IDs remains `capturing` and returns false;
7. a run without fingerprint remains `capturing` and returns false;
8. wrong scope/status fails closed through the existing scoped context contract;
9. enqueues no Work and touches no handler;
10. adds no table, column, class, engine or scheduler.

## B3e2 evidence

RED commit:

```text
59a8da7f23db03e4565c341e0fc12b72fe8469f6
```

RED run `38018360173`, job `114113626109`:

```text
PHPSTAN=0
TESTS=189
ERRORS=3
CAUSE=SalesAuditRepository::transitionToRepairingIfMissing() did not exist
```

GREEN functional commit:

```text
6493b4fbe841177896f279e5acb8de53d6d1d265
```

Fresh QA — run `38018470446`, job `114113966535`:

```text
PHP=8.5.11
PHPSTAN=0
PHPUNIT=189/189 PASS
ASSERTIONS=1254
MEMORY=22 MB
REAL_MELI_HTTP=0
```

## Repair fan-out audit

Existing webhook/order-sync contract already defines the identity to reuse later:

```text
type=order.sync
resource_key=<external_order_id>
logical_identity=order.sync:<external_order_id>
payload={order_id:<external_order_id>}
```

`WorkRepository::enqueue()` already provides active-work dedupe through its logical identity hash/database unique key. No new repair queue or repair table is justified.

Current phase gap: after B3e2 transitions a run to `repairing`, `missingCanonicalOrderIds()` deliberately cannot be reused because it is scoped through `captureContext()` and must not broaden that capture contract to repairing runs. The next block will close only this phase-specific read gap.

## Gates

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for active Sales Audit path |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — REPAIR transition verified; repairing-phase missing-set read next; fan-out not yet wired |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 real sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

## Exact next microblock

### B3e3a — repairing-phase missing-set read only

Scope only:

1. RED: a scoped `repairing` run returns the deterministic canonical IDs still missing locally;
2. RED: a `capturing` run cannot use the repairing-phase read path;
3. RED: wrong scope/non-repairing status fails closed;
4. GREEN: smallest phase-specific repository read; do not broaden `captureContext()`;
5. no `order.sync` enqueue yet;
6. no handler wiring, no VERIFY, no second capture, no CONFIRM;
7. full QA → checkpoint.

## Stop conditions

- no repair fan-out in B3e3a;
- no VERIFY/CONFIRM in B3e3a;
- no Billing handler / F6A Task2 before C0;
- no merge;
- no deploy;
- no real Mercado Libre HTTP except separately authorized smoke;
- no remote writes;
- `meli_writes_enabled` remains OFF until F16.
