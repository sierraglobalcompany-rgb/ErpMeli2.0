# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Current verified functional SHA:** `320105388ded5af6672a58ba37ece7f33c17cf13`  
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
B3e3b1    single deterministic repair candidate
```

## B3e3b1 recovery audit

The previous chat stopped after code was already committed but before `CURRENT_CHECKPOINT.md` was updated. Branch audit showed exactly three commits after the B3e3a functional SHA: B3e3a checkpoint, B3e3b1 RED, B3e3b1 GREEN. No hidden later product commit exists.

RED commit:

```text
4f2c3fb72ed8e4fbe8c2b2160fc7930cd29ed33c
```

RED run:

```text
RUN=38019043502
STATUS=FAILURE as expected
CAUSE=nextRepairingMissingCanonicalOrderId() did not yet exist
```

GREEN functional commit:

```text
320105388ded5af6672a58ba37ece7f33c17cf13
```

Fresh QA:

```text
RUN=38019232637
JOB=114116291468
PHP=8.5.11
PHPSTAN=0
PHPUNIT=194/194 PASS
ASSERTIONS=1284
MEMORY=22 MB
REAL_MELI_HTTP=0
```

## B3e3b1 contract

`SalesAuditRepository::nextRepairingMissingCanonicalOrderId()` now:

1. reuses the already verified repairing-phase missing-set derivation;
2. returns only the first deterministic missing canonical order ID;
3. returns null when no canonical order remains missing;
4. adds no queue/table/status/engine;
5. does not enqueue Work itself.

Authority/plan audit found no approved numeric fan-out constant. They require only `REPAIR sólo faltantes y fan-out limitado`. Therefore no arbitrary batch size is introduced. The next block uses the structurally bounded one-candidate primitive and the existing Work dedupe contract.

Existing repair identity remains:

```text
type=order.sync
resource_key=<external_order_id>
logical_identity=order.sync:<external_order_id>
payload={order_id:<external_order_id>}
```

## Gates

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for active Sales Audit path |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — one bounded repair candidate proven; enqueue wiring next |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 real sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

## Exact next microblock

### B3e3b2 — enqueue one repair candidate only

Scope only:

1. RED: for a scoped `repairing` run with missing canonical IDs, one repair action enqueues exactly one `order.sync` using the existing identity/payload contract;
2. RED: the chosen ID must equal `nextRepairingMissingCanonicalOrderId()`;
3. RED: an already-active `order.sync:<id>` must remain deduped by existing `WorkRepository::enqueue()` semantics;
4. RED: no second missing ID may be enqueued in the same action;
5. GREEN: minimum reuse of existing repository/WorkRepository; no repair queue/table/engine and no new numeric batch constant;
6. no VERIFY, second capture or CONFIRM yet;
7. full QA → checkpoint.

## Stop conditions

- fan-out remains structurally bounded to one candidate per repair action;
- no VERIFY/CONFIRM in B3e3b2;
- no Billing handler / F6A Task2 before C0;
- no merge;
- no deploy;
- no real Mercado Libre HTTP except separately authorized smoke;
- no remote writes;
- `meli_writes_enabled` remains OFF until F16.
