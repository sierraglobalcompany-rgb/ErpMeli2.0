# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Last fully verified functional SHA:** `c52b2e4c7b8c248fb89b2b6b4899b437153e7ab8`  
**Latest functional SHA awaiting final QA:** `e7d1e65fe60305698f344a0e2875bd4dd0f4a6a2`  
**Remote writes:** OFF  
**REAL_MELI_HTTP:** `0`

## Current truth

V3-A is closed/green. V3-B Sales Audit is active and executed in small RED → GREEN → QA → checkpoint blocks.

Closed and fully verified:

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
```

B3c3 has completed RED and the minimum GREEN code is committed, but its final QA was still running when work was explicitly paused by the user. Therefore B3c3 must **not** yet be called closed/green until that exact run is checked.

## B3c2 verified contract

The active `SalesAuditHandler` already guarantees:

1. returned `paging.total`, `paging.offset` and `paging.limit` are validated;
2. returned offset/limit must match the requested page;
3. the first valid `remote_total` is fixed durably through `SalesAuditRepository::acceptRemoteTotal()`;
4. later total drift fails closed before evidence from that page commits;
5. evidence, current Work completion and next-page enqueue share one database transaction;
6. continuation exists only when `offset + limit < remote_total`;
7. next offset comes from paging math, not result count or short-page inference;
8. continuation carries only `run_id`, `offset`, `limit` and reuses the same company/account/run;
9. CAPTURE enqueues no `order.sync`;
10. no pagination engine, scheduler, table, column or new Work type was added.

Verified B3c2 QA:

```text
FUNCTIONAL_SHA=c52b2e4c7b8c248fb89b2b6b4899b437153e7ab8
RUN=38016138786
JOB=114106702089
PHP=8.5.11
PHPSTAN=0
PHPUNIT=178/178 PASS
ASSERTIONS=1179
MEMORY=22 MB
REAL_MELI_HTTP=0
```

Checkpoint SHA after B3c2:

```text
8fe08d81b137416c4f2c231f093b3e53a98af30a
```

Its own QA run `38016239501` / job `114107017368` completed successfully.

## B3c3 — terminal CAPTURE traversal integrity

### RED

RED commit:

```text
f3cb2f419c2d135fd7f6d9036c7f159e53d50fb3
```

RED run `38016356450`, job `114107375826`:

```text
PHPSTAN=0
TESTS=180
ASSERTIONS=1192
FAILURES=1
```

The single expected failure was:

```text
SalesAuditContinuationTest::testTerminalPageWithIncompleteDurableEvidenceRollsBackAndFailsClosed
Failed asserting that true is false.
```

This isolated the exact missing invariant: the handler accepted a terminal page even when durable evidence count was lower than stable `remote_total`.

The terminal-complete control case passed.

### GREEN code committed

Repository addition:

```text
9b433828c46a52a5075516f9d0eec28e59982c6b
feat(v3-b3c3): count durable audit observations
```

Adds only `SalesAuditRepository::observationCount(int $runId): int` using `COUNT(*)` on `sales_audit_orders`.

Handler change:

```text
e7d1e65fe60305698f344a0e2875bd4dd0f4a6a2
feat(v3-b3c3): fail closed on incomplete terminal capture
```

Minimum behavior now intended:

1. observations for the terminal page are inserted inside the existing Work completion transaction;
2. only when `next_offset === null`, durable observation count is compared with stable `remote_total`;
3. exact equality allows the terminal Work to complete;
4. mismatch throws inside the transaction, rolling back terminal-page observations and preventing silent incomplete coverage;
5. prior pages remain intact;
6. the run deliberately remains `capturing` — VALIDATE/REPAIR/VERIFY/CONFIRM have **not** started;
7. no schema/status/engine/new Work type was added.

### QA status at pause

Fresh QA run for `e7d1e65f...`:

```text
RUN=38016478682
JOB=114107765815
STATUS=IN_PROGRESS at explicit user pause
REAL_MELI_HTTP=0
```

Do not infer success. The **first action on resume** is to inspect this exact run/job and record its final result. If it failed, repair only B3c3. If it passed, then B3c3 may be closed and checkpointed as verified.

## Current Sales Audit guarantees already verified through B3c2

- exact scoped durable run;
- MCO → America/Bogota, unsupported site fail-closed;
- canonical month with ±1h seller-search guard-band;
- exact bigint order IDs;
- required zoned `date_created`;
- full page validation before persistence;
- stable `remote_total` first-writer contract;
- evidence + Work completion + continuation atomic;
- duplicate evidence and total drift fail closed;
- malformed/drifting pages leave zero partial evidence;
- no `order.sync` during CAPTURE;
- 429 defer without attempt burn;
- 5xx/transport bounded retry;
- first 401 refreshes once; second 401 terminal;
- permanent remote failures terminal;
- obsolete reconciler deleted.

B3c3 terminal-count integrity exists in code at `e7d1e65f...` but is **pending final QA verification**.

## Gates

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for verified boundary through B3c2 |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for active Sales Audit path |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — B3c3 GREEN implemented, final QA pending |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 real sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

## Exact resume point

### First action — verify B3c3 QA only

Inspect:

```text
RUN=38016478682
JOB=114107765815
HEAD=e7d1e65fe60305698f344a0e2875bd4dd0f4a6a2
```

If PASS:

1. record PHPStan/test/assertion counts;
2. mark B3c3 closed;
3. create a verified checkpoint;
4. only then design the next microblock for local set VALIDATE.

If FAIL:

1. diagnose only the B3c3 failure;
2. RED/repair minimally;
3. rerun QA;
4. do not broaden scope.

## Stop conditions

- STOP NOW per user instruction;
- do not begin VALIDATE/hash/REPAIR/VERIFY/CONFIRM yet;
- no Billing handler / F6A Task2 before C0;
- no merge;
- no deploy;
- no real Mercado Libre HTTP except separately authorized smoke;
- no remote writes;
- `meli_writes_enabled` remains OFF until F16.
