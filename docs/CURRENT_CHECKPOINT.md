# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED BY USER — DO NOT CONTINUE IMPLEMENTATION FROM THIS CHAT**  
**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Remote writes:** OFF  
**REAL_MELI_HTTP:** `0`

## Exact resume point

Last fully GREEN functional commit:

```text
81068b6e4cca808e3534e275ed01edc9e9204fb1
feat(v3-k5b): advance terminal capture directly to next durable state
```

Verified QA for that commit:

```text
RUN=38057589089
JOB=114229115845
PHP=8.5.11
PHPSTAN=0
PHPUNIT=199/199 PASS
ASSERTIONS=1355
MEMORY=22 MB
REAL_MELI_HTTP=0
```

Audit/checkpoint correction commit:

```text
4796f1805445d5853e500be76787eef31d005bc5
docs(checkpoint): audit K1-K5b and freeze source-truth gates
```

Current code-under-test RED commit at pause:

```text
35730c23fafd59b3fc7c6f737b21bcbcf87b8c91
test(v3-k6a1): prove old seller-search month becomes unavailable
```

Its workflow was still running when the user requested the pause:

```text
RUN=38060132448
JOB=114236531063
STATUS=in_progress at pause
```

The commit that contains this file is **documentation-only** and is expected to be the branch HEAD after the pause. When resuming, inspect its parent chain and the workflow above before changing code. Do not assume the RED result without reading CI/logs.

## What is already closed

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
B3e2      guarded post-CAPTURE state decision
B3e3a     repairing missing-set read
B3e3b1    deterministic single repair candidate
B3e3b2    enqueue one bounded repair candidate
B3e3b3a   block terminal same-order recreation
K1        collapse full missing-list work to deterministic LIMIT 1
K2        REPAIR runtime through existing sales.audit Work
K3        terminal repair child + persistent gap -> durable attention
K4        terminal CAPTURE -> repairing + one continuation when needed
K5        local VERIFY -> confirming only with zero canonical gaps
K5b       terminal CAPTURE fast-path -> repairing or confirming
```

## KISS audit result before pause

Architecture remains intentionally small:

```text
1 PHP/Slim app
1 MariaDB
1 work_items table
Work states: pending / running / done / failed
Sales Work types: order.sync / sales.audit
1 WorkRunner
1 MeliClient
1 Sales audit run state machine
```

Still **no** repair engine, recovery engine, priority queue, domain scheduler, repair/history table, child-state table, extra Work status or extra Sales Work type.

Important anti-ERP1 rules preserved:

- no queue-state churn such as running/waiting/ready incompatibilities;
- no generic retry/recovery engine;
- no historical backfill engine coupled to current work;
- no business truth dependent on retained Work rows;
- no retries forever for old historical gaps;
- Git keeps history; current tree does not keep obsolete parallel paths.

## Current runtime truth

1. CAPTURE never enqueues `order.sync` directly.
2. Terminal CAPTURE persists canonical fingerprint.
3. Post-CAPTURE has one durable decision:
   - missing canonical order -> `repairing` + one `sales.audit` continuation;
   - no missing canonical order -> `confirming`, no repair continuation.
4. REPAIR handles one deterministic missing order at a time.
5. Parent REPAIR defers while child `order.sync` is pending/running without burning attempts.
6. Terminal child + persistent gap is not recreated; run becomes `attention`.
7. No remaining canonical gap -> `repairing -> confirming` transactionally.
8. Work is execution state, not business history.

## Mercado Libre truth gate discovered by audit

Official Mercado Libre documentation revalidated on 2026-10-10 indicates seller order search is limited to approximately the last **12 months** and seller searches filter cancelled orders. Therefore seller search cannot be treated as all-time absolute truth.

Before independent CONFIRM/capture B or `valid`, two safeguards remain:

### K6a-1 — source horizon

A canonical month outside the supported seller-search horizon must become durable `unavailable` **before OAuth/HTTP**, not empty=complete and not retry forever.

Current RED added exactly this proof:

```text
month 2025-09
now 2026-10-10
-> expected run unavailable
-> Work done
-> zero OAuth token dependency
-> zero remote HTTP
-> zero audit evidence rows
```

No production GREEN for K6a-1 has been written yet.

### K6a-2 — short non-terminal page

Not started.

If:

```text
count(results) < paging.limit
AND offset + limit < total
```

fail closed rather than advance by requested limit, unless an official endpoint contract proves short non-terminal pages cannot occur.

No pagination engine is allowed.

## Exact sequence when resuming

1. Read branch HEAD and this checkpoint.
2. Inspect workflow `38060132448` and confirm the K6a-1 RED failure is the expected single cause.
3. If RED is clean, implement the smallest GREEN for K6a-1 only.
4. Full QA.
5. Audit/delete/merge any superseded helper created by the change.
6. Checkpoint.
7. Only then start K6a-2 as a separate RED.
8. Do **not** start capture B or `valid` until K6a-1 and K6a-2 are green.

## Gates at pause

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary; K6a source-truth hardening in progress |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for active Sales paths |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — CAPTURE + bounded REPAIR + local VERIFY wired; source horizon, short-page guard, capture B, final valid/baseline remain |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

## Stop conditions

- STOP after this checkpoint until explicit user instruction to continue.
- no production GREEN for K6a-1 in this paused turn;
- no K6a-2 yet;
- no capture B;
- no `valid`;
- no Billing handler before C0;
- no merge;
- no deploy;
- no real Mercado Libre HTTP unless separately authorized;
- no remote writes;
- `meli_writes_enabled` stays OFF until F16.
