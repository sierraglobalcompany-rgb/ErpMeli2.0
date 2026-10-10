# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Current verified functional SHA:** `4b9cb66588b41dcd85a5af2572ad6548f9368b18`  
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
B3e3b3a   block automatic recreation after terminal repair sync
```

## B3e3b3a evidence

RED commit:

```text
714c7ec649200ce7a07fa5b867a719ea1e457230
```

RED QA:

```text
RUN=38021276807
JOB=114122593884
PHP=8.5.11
PHPSTAN=0
PHPUNIT=198 tests
ASSERTIONS=1317
FAILURES=1
```

The single expected failure proved that a terminal failed `order.sync` released active dedupe and a later repair action recreated the same logical sync, producing 2 Work rows instead of 1.

GREEN commits:

```text
fc251a8ad2815d1b6f1d2ce1d17a22fcc1463e4e
feat(v3-b3e3b3a): expose latest logical work state

4b9cb66588b41dcd85a5af2572ad6548f9368b18
feat(v3-b3e3b3a): block terminal repair sync recreation
```

Fresh QA:

```text
RUN=38021388327
JOB=114122940722
PHP=8.5.11
PHPSTAN=0
PHPUNIT=198/198 PASS
ASSERTIONS=1316
MEMORY=22 MB
REAL_MELI_HTTP=0
```

## B3e3b3a contract

`WorkRepository::latestLogicalState()` now returns only the latest `{id,status}` for an exact scoped logical identity using the existing SHA-256 dedupe key. No schema or queue behavior changed.

`SalesAuditRepairHandler::enqueueNextMissingOrder()` now behaves as follows for the deterministic first missing candidate:

1. no missing candidate → returns null;
2. no prior same logical `order.sync` → enqueues exactly one;
3. latest same logical Work is `pending` or `running` → returns that existing Work id and creates nothing;
4. latest same logical Work is terminal (`done` or `failed`) while the canonical order is still missing locally → throws and fails closed instead of automatically recreating it;
5. no new table/status/queue/scheduler/priority/retry engine/batch constant was introduced.

This closes the identified automatic repair poison-loop path caused by terminal Work dedupe release.

## Current sequencing constraint

Runtime REPAIR is still **not wired** into `SalesWorkProcessor`/`sales.audit`. The next block must preserve progress ordering:

- enqueue/reuse one `order.sync`;
- do not advance to a second candidate while that sync remains active;
- do not recreate a terminal same-ID sync if the local gap remains;
- keep the audit run in `repairing`;
- do not start VERIFY/CONFIRM yet.

## Gates

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for active Sales paths |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — missing-only bounded repair primitives and terminal recreation guard proven; runtime REPAIR wiring next |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 real sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

## Exact next microblock

### B3e3b3b — progress-safe REPAIR runtime RED

Scope only:

1. design the smallest explicit `sales.audit` repair-phase payload/dispatch using the existing Work table and Work types;
2. RED: a repair-phase `sales.audit` action with a missing candidate must enqueue/reuse exactly one `order.sync` and must not enqueue a second candidate;
3. RED: while that same `order.sync` is `pending`/`running`, the repair-phase action must wait/defer rather than spin or complete as if repair progressed;
4. RED: a terminal same-ID sync with the local gap still present must fail closed, not recreate it;
5. keep the run `repairing` and do not start VERIFY/CONFIRM;
6. no new Work type, scheduler, priority, repair table, orchestration engine or numeric repair batch;
7. GREEN minimal → full QA → checkpoint.

## Stop conditions

- no VERIFY/CONFIRM yet;
- no Billing handler / F6A Task2 before C0;
- no merge;
- no deploy;
- no real Mercado Libre HTTP except separately authorized smoke;
- no remote writes;
- `meli_writes_enabled` remains OFF until F16.
