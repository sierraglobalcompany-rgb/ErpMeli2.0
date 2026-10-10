# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Current verified functional SHA:** `1f8c4186eabf84c0fd4c8c38389f40401808fd49`  
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
B3e3b2    enqueue one bounded repair candidate
```

## B3e3b2 evidence

RED commit:

```text
a69680ba245e179912ca1df7555d44be98da6f91
```

RED QA:

```text
RUN=38021040183
JOB=114121866694
PHPSTAN=0
TESTS=197
ERRORS=3
CAUSE=SalesAuditRepairHandler did not exist
```

All three new errors were exactly the missing class and no unrelated failure appeared.

GREEN functional commit:

```text
1f8c4186eabf84c0fd4c8c38389f40401808fd49
```

Fresh QA:

```text
RUN=38021108336
JOB=114122074186
PHP=8.5.11
PHPSTAN=0
PHPUNIT=197/197 PASS
ASSERTIONS=1308
MEMORY=22 MB
REAL_MELI_HTTP=0
```

## B3e3b2 contract

`SalesAuditRepairHandler::enqueueNextMissingOrder()` now:

1. asks `SalesAuditRepository::nextRepairingMissingCanonicalOrderId()` for one deterministic missing canonical ID;
2. returns null and creates no Work when none remains missing;
3. enqueues at most one `order.sync` per repair action;
4. reuses the existing Work identity exactly:

```text
type=order.sync
resource_key=<external_order_id>
logical_identity=order.sync:<external_order_id>
payload={order_id:<external_order_id>}
```

5. reuses `WorkRepository::enqueue()` active dedupe; an already active same logical identity resolves to the existing Work id;
6. adds no schema/table/status/queue/retry engine/numeric batch constant;
7. is not yet wired into `SalesWorkProcessor`/`sales.audit` runtime flow.

Authority and plan specify only missing-only repair with limited fan-out. No numeric bound is authorized, so the implementation remains structurally bounded to one candidate per action instead of inventing a batch size.

## Important sequencing risk for next block

Do not blindly chain another repair Work immediately after enqueuing `order.sync`. If the sync is rate-limited/retried, a next repair action could observe the same local gap, dedupe the same `order.sync`, and spin without progress.

Before runtime wiring, audit the existing Work ordering/retry semantics and design a minimal progress-safe continuation. Do not create a scheduler, repair queue, priority or generic orchestration engine.

## Gates

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for active Sales Audit path |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — bounded missing-only enqueue proven; progress-safe repair wiring next |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 real sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

## Exact next microblock

### B3e3b3 — progress-safe REPAIR wiring design + RED

Scope first:

1. audit current WorkRunner ordering, retry/defer and `order.sync` completion behavior;
2. choose the smallest mechanism that guarantees one missing order is synced before REPAIR advances to another candidate;
3. RED must demonstrate no repair poison/spin when the candidate `order.sync` remains active/pending;
4. keep one Work table and existing `sales.audit`/`order.sync` types only;
5. do not introduce a numeric repair batch, priority, scheduler, domain queue or repair table;
6. no VERIFY, second capture or CONFIRM yet;
7. GREEN only after the RED isolates the required progress contract; full QA → checkpoint.

## Stop conditions

- do not chain repair blindly before proving progress safety;
- no VERIFY/CONFIRM yet;
- no Billing handler / F6A Task2 before C0;
- no merge;
- no deploy;
- no real Mercado Libre HTTP except separately authorized smoke;
- no remote writes;
- `meli_writes_enabled` remains OFF until F16.
