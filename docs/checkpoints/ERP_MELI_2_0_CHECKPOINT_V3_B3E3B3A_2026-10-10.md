# ERP MELI 2.0 — CHECKPOINT V3-B3e3b3a

**Date:** 2026-10-10  
**Repository:** `sierraglobalcompany-rgb/ErpMeli2.0`  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Plan:** `docs/superpowers/plans/2026-10-10-v3-b-sales-audit.md`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Last verified functional SHA:** `4b9cb66588b41dcd85a5af2572ad6548f9368b18`  
**CURRENT_CHECKPOINT update SHA before this freeze:** `5f47f28c96956345be171327a9380f1419941e53`  
**Remote writes:** OFF  
**REAL_MELI_HTTP:** `0`

## Why this checkpoint exists

The user explicitly requested a preventive checkpoint and stop before starting the next runtime REPAIR wiring microblock. No B3e3b3b code or RED test has been started after the verified B3e3b3a GREEN.

## Closed and verified V3-B blocks

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
B3e3b3a   terminal same-ID repair sync recreation guard
```

## Recovery from the previous blocked chat

The branch had advanced beyond the older checkpoint. Audit recovered and verified:

### B3e3b1
- RED: `4f2c3fb72ed8e4fbe8c2b2160fc7930cd29ed33c`
- GREEN: `320105388ded5af6672a58ba37ece7f33c17cf13`
- QA run `38019232637`, job `114116291468`
- PHPStan 0
- PHPUnit 194/194
- 1284 assertions
- 22 MB
- `REAL_MELI_HTTP=0`

### B3e3b2
- RED: `a69680ba245e179912ca1df7555d44be98da6f91`
- GREEN: `1f8c4186eabf84c0fd4c8c38389f40401808fd49`
- QA run `38021108336`, job `114122074186`
- PHPStan 0
- PHPUnit 197/197
- 1308 assertions
- 22 MB
- `REAL_MELI_HTTP=0`

Contract: `SalesAuditRepairHandler::enqueueNextMissingOrder()` enqueues at most one deterministic missing `order.sync` candidate and reuses existing active Work dedupe.

### B3e3b3a
Problem found during progress-safety audit:
- `work_items.active_dedupe_key` only dedupes `pending/running` rows.
- A terminal `failed` row releases the active key.
- Without a guard, a later REPAIR action could recreate the same failed `order.sync` forever.

RED:
`714c7ec649200ce7a07fa5b867a719ea1e457230`

RED QA:
- run `38021276807`
- job `114122593884`
- PHPStan 0
- 198 tests
- exactly 1 failure
- failure proved 2 Work rows existed instead of 1 after terminal failed sync recreation.

GREEN commits:
- `fc251a8ad2815d1b6f1d2ce1d17a22fcc1463e4e` — expose latest logical Work state
- `4b9cb66588b41dcd85a5af2572ad6548f9368b18` — block terminal repair sync recreation

Fresh GREEN QA:
- run `38021388327`
- job `114122940722`
- PHP 8.5.11
- PHPStan 0
- PHPUnit **198/198 PASS**
- **1316 assertions**
- Memory 22 MB
- `REAL_MELI_HTTP=0`

## Current exact repair contract

`WorkRepository::latestLogicalState(scope,type,logicalIdentity)`:
- returns latest exact scoped logical Work `{id,status}`;
- uses existing SHA-256 dedupe identity;
- no schema change.

`SalesAuditRepairHandler::enqueueNextMissingOrder()`:
1. gets deterministic first missing canonical order;
2. no missing order → returns null;
3. no prior same logical sync → enqueues exactly one `order.sync`;
4. same logical sync `pending/running` → reuses existing Work id;
5. same logical sync terminal (`done/failed`) while local canonical gap remains → throws/fails closed;
6. never recreates terminal same-ID sync automatically;
7. no batch constant, scheduler, queue, priority or orchestration engine.

Existing repair Work identity remains exactly:

```text
type=order.sync
resource_key=<external_order_id>
logical_identity=order.sync:<external_order_id>
payload={order_id:<external_order_id>}
```

## Important Work semantics audited

- One `WorkRunner`, serial claim loop.
- `order.sync` may remain `pending` due to defer/retry after 429/5xx/transport.
- `deferCurrentClaim()` does not consume Work retry budget.
- `retryCurrentClaim()` consumes attempt and caps automatically.
- Active dedupe is only `pending/running`.
- Therefore REPAIR runtime must NOT blindly enqueue/chain a second candidate before the current missing order has actually appeared locally.

## Exact next microblock — NOT STARTED

### B3e3b3b — progress-safe REPAIR runtime RED

Start only when work resumes.

Scope:
1. define the smallest explicit repair-phase behavior inside existing `sales.audit` runtime flow;
2. RED: repair action with a missing candidate enqueues/reuses exactly one `order.sync`;
3. RED: while that same sync is `pending/running`, repair action must wait/defer, not spin and not advance to another candidate;
4. RED: terminal same-ID sync with local gap remaining must fail closed, not recreate it;
5. run remains `repairing`;
6. no VERIFY, second capture or CONFIRM yet;
7. no new Work type, repair table, scheduler, priority, domain queue, generic orchestration engine or arbitrary numeric batch;
8. minimal GREEN → fresh full QA → new checkpoint.

Potential minimal mechanism already identified for the next design pass:
- reuse existing `deferCurrentClaim()` for waiting because it does not consume retry attempts;
- do not invent timing/cadence without checking existing Work timing conventions first.

## Gates

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for active Sales paths |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — bounded missing-only repair primitives + terminal recreation guard proven; runtime REPAIR wiring next |
| G5 BILLING_CURSOR_TRUTH | BLOCKED pending C0 real sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

## Stop conditions

Do not:
- start B3e3b3b until explicitly resuming from this checkpoint;
- start VERIFY/CONFIRM yet;
- start F6A Billing Task2 before C0;
- merge;
- deploy;
- use real Mercado Libre HTTP unless separately authorized;
- perform remote writes;
- enable `meli_writes_enabled` before F16.

## Recovery instruction

On resume, first verify branch HEAD contains this checkpoint and that the last functional GREEN remains `4b9cb66588b41dcd85a5af2572ad6548f9368b18`. Then begin only **B3e3b3b RED**. Do not reconstruct or redo already closed B0–B3e3b3a work.
