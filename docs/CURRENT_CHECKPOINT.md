# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED BY USER — DO NOT CONTINUE IMPLEMENTATION UNTIL EXPLICITLY RESUMED**  
**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Master map:** `README.md`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Engineering law:** `AGENTS.md`  
**Remote writes:** OFF  
**REAL_MELI_HTTP:** `0`

## User execution constraint

Keep future work deliberately small:

```text
1 microblock at a time
RED -> confirm intended failure -> minimal GREEN -> full QA -> noise audit
checkpoint after 1-2 microblocks maximum
STOP after checkpoint when context is growing
```

Do not chain several phases in one long run.

## Authority order when resuming

```text
1. code/schema at branch HEAD
2. tests/CI at the relevant functional SHA
3. this CURRENT_CHECKPOINT.md
4. docs/ERP2_AUTHORITY.md
5. recent explicit user decisions
6. README.md master map
7. historical handoffs/plans
```

For external contracts:

```text
current official documentation + controlled real evidence > assumptions
```

## Exact frozen point

Latest fully GREEN functional commit before this checkpoint:

```text
bdca863c85a0f6529f676cf78f3e495e6e7aab60
feat(v3-k6a2): reject short nonterminal audit pages
```

Verified full QA:

```text
RUN=38066150484
JOB=114254090388
PHP=8.5.11
PHPSTAN=0
PHPUNIT=201/201 PASS
ASSERTIONS=1370
MEMORY=22 MB
REAL_MELI_HTTP=0
```

Noise audit RED->GREEN K6a-2:

```text
1 production file changed
+3 lines
0 deletions
no schema change
no new Work type
no new engine
```

## K6a-1 — seller-search source horizon — CLOSED GREEN

GREEN commit:

```text
53f1de7c44c6491df0c158b5c3b7f2914d59c00b
feat(v3-k6a1): fail closed outside seller-search horizon
```

Verified full QA:

```text
RUN=38065828713
JOB=114253159334
PHP=8.5.11
PHPSTAN=0
PHPUNIT=200/200 PASS
ASSERTIONS=1362
MEMORY=22 MB
REAL_MELI_HTTP=0
```

Durable behavior now implemented:

```text
canonicalStartUtc < nowUtc - 12 months
-> sales_audit_runs.status = unavailable
-> current sales.audit Work = done
-> zero OAuth dependency
-> zero Mercado Libre HTTP
-> zero audit-order evidence
-> no retry forever
-> no false complete
```

The transition is persisted atomically with completion of the current Work claim.

No alternate historical source was added.

## K6a-2 — short non-terminal seller-search page — CLOSED GREEN

RED commit:

```text
9e5399d23bb7ff7445254f8f588400e7e00395b3
test(v3-k6a2): fail closed on short nonterminal audit page
```

RED evidence:

```text
RUN=38065990464
JOB=114253625854
PHPSTAN=0
PHPUNIT=201 tests
ASSERTIONS=1364
FAILURES=1
```

Single intended failure:

```text
SalesAuditCaptureHandlerTest::
testShortNonTerminalPageFailsClosedWithoutEvidenceOrContinuation

Failed asserting that true is false.
```

GREEN commit:

```text
bdca863c85a0f6529f676cf78f3e495e6e7aab60
feat(v3-k6a2): reject short nonterminal audit pages
```

Implemented rule:

```text
offset + limit < remote_total
AND count(results) < limit
-> fail closed as meli_sales_audit_contract
-> no durable observations from that page
-> no continuation skipping unknown positions
-> run remains capturing
```

No pagination engine was introduced.

## Closed path through K6a

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
K1        repair candidate collapsed to deterministic ORDER BY ... LIMIT 1
K2        REPAIR runtime through existing sales.audit Work
K3        terminal repair child + persistent gap -> durable attention
K4        terminal CAPTURE -> repairing + one continuation when needed
K5        local VERIFY -> confirming only with zero canonical gaps
K5b       terminal CAPTURE fast-path -> repairing or confirming
K6a-1     seller-search horizon -> unavailable before OAuth/HTTP
K6a-2     short non-terminal page -> fail closed
```

## Current runtime truth

1. `sales.audit` is the single Sales Audit Work type.
2. CAPTURE persists durable seller-search observations and canonical fingerprint.
3. CAPTURE never enqueues `order.sync` directly.
4. Post-CAPTURE:
   - canonical gap -> `repairing` + one `sales.audit` continuation;
   - no canonical gap -> `confirming`.
5. REPAIR processes one deterministic missing order at a time.
6. Parent REPAIR defers while the same child `order.sync` is pending/running.
7. Terminal child + persistent gap is never auto-recreated; run becomes `attention`.
8. Zero remaining canonical gaps -> `repairing -> confirming` transactionally.
9. Old source period outside supported seller-search horizon -> `unavailable` before OAuth/HTTP.
10. Short non-terminal source page -> fail closed rather than skipping positions.
11. Work is execution state, not business history.

## Important truth about `confirming`

`confirming` exists as a durable run state, but **CONFIRM runtime is NOT implemented yet**.

Current `SalesWorkProcessor` only dispatches:

```text
capturing -> SalesAuditHandler
repairing -> SalesAuditRepairHandler
```

A `sales.audit` claim for a run already in `confirming` currently falls into:

```text
sales_audit_state
```

and fails the Work claim.

This is intentional unfinished scope, not a regression to patch casually.

## Schema fact that must be respected before CONFIRM

Current `sales_audit_runs` has only one set of source fingerprint fields:

```text
remote_total
canonical_count
set_hash
```

Current `sales_audit_orders` is keyed by:

```text
(audit_run_id, external_order_id)
```

Therefore an **independent capture B cannot be implemented by blindly reusing/overwriting capture A evidence** without first defining a correct minimal persistence contract.

Do not add a second table, history engine or new Work type by reflex. Prove the minimum representation with RED/design first.

## Exact next microblock — K6b-0 only

**Do not implement a full CONFIRM flow immediately.**

Next block is strictly:

```text
K6b-0 — independent CONFIRM design + RED
```

Goals:

1. inspect existing `confirming` state, schema and current tests;
2. define the smallest correct representation of independent capture B;
3. preserve capture A evidence until comparison is complete;
4. reuse the same `sales.audit` Work type;
5. RED must prove that a confirming run cannot become `valid` without an independent second seller-search traversal and equal canonical count/hash;
6. mismatch must fail closed to durable `attention` (or another already-approved terminal state only if Authority requires it);
7. no `valid` GREEN in the same block unless the persistence contract is already proven minimal and the block remains small;
8. checkpoint and STOP after K6b-0 RED/design if implementation would expand scope.

### KISS constraints for K6b-0

Do not introduce by default:

```text
sales.audit.confirm Work type
confirm engine
second queue
scheduler
page table
history table
repair table
capture-history engine
priority
extra Work status
```

Any schema addition requires proof that capture A and capture B cannot be compared correctly with fewer pieces.

## Gates at pause

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary and K6a source-truth guards |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for active Sales paths |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — CAPTURE + bounded REPAIR + local VERIFY + horizon + short-page guards green; independent CONFIRM/valid still missing |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

## Open gaps — do not mix into K6b-0

```text
independent capture B / CONFIRM
valid/baseline lifecycle
start UX + active-run guard
exact order 404 final audit classification
sale_fee schema alignment before Financial
webhook_events lifecycle/retention audit
MariaDB session timezone certification in G8
Billing C0 cursor/206 smoke
Hostinger/runtime/main protection
```

## Do not touch next

```text
NO Billing handler before C0
NO Financial
NO sale_fee alignment inside CONFIRM
NO webhook_events cleanup
NO Hostinger work inside CONFIRM
NO new queue
NO new scheduler
NO generic history/recovery engine
NO merge
NO deploy
NO real Mercado Libre batch
NO remote writes
```

## Resume protocol

When user says `continua`:

```text
1. fetch branch HEAD
2. read this checkpoint + AGENTS + ERP2_AUTHORITY
3. audit only delta since functional SHA bdca863...
4. optionally sync stale operational section of README before code
5. execute K6b-0 only
6. RED first
7. checkpoint and STOP before a larger CONFIRM implementation
```

Do not re-audit the whole project and do not ask where we were.
