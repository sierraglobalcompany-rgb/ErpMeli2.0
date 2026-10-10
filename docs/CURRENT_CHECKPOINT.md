# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED — STOP AFTER THIS CHECKPOINT**  
**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Master map:** `README.md`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Engineering law:** `AGENTS.md`  
**Remote writes:** OFF  
**REAL_MELI_HTTP:** `0`

## Execution constraint

```text
1 microblock at a time
RED -> confirm intended failure -> minimal GREEN -> full QA -> noise audit
checkpoint after 1-2 microblocks maximum
STOP after checkpoint when context grows
```

## Authority order

```text
1. code/schema at branch HEAD
2. tests/CI at relevant SHA
3. this checkpoint
4. ERP2_AUTHORITY.md
5. recent explicit user decisions
6. README.md master map
7. historical handoffs/plans
```

External contracts:

```text
current official Mercado Libre docs + controlled real evidence > assumptions
```

---

# 1. LAST FUNCTIONAL GREEN

```text
364256b1cc9135f33c780005c398c60d95e5882a
feat(v3-k6b2): isolate audit evidence by capture pass
```

Fresh verified QA:

```text
RUN=38068906148
JOB=114262117720
PHP=8.5.11
PHPSTAN=0
PHPUNIT=203/203 PASS
ASSERTIONS=1388
MEMORY=22 MB
REAL_MELI_HTTP=0
```

Documentation was then synchronized without production changes through:

```text
bc43338ccff63f12716794f9a29387b28ae857cc
docs(checkpoint): close K6b documentation sync
```

`README.md`, `docs/ERP2_AUTHORITY.md` and the prior checkpoint reflect K6a + K6b-2 truth.

---

# 2. K6b-3 — CONFIRMING DISPATCH / FIRST B PAGE — RED CONFIRMED

RED commit:

```text
1fab5dbbda7dee4649e10318f7872b0ef6a37028
test(v3-k6b3): prove confirming dispatch starts independent capture B
```

CI evidence:

```text
RUN=38069661480
JOB=114264310611
PHP=8.5.11
PHPSTAN=0
PHPUNIT=204 tests
ASSERTIONS=1393
FAILURES=1
MEMORY=22 MB
REAL_MELI_HTTP=0
```

Single intended failure:

```text
SalesWorkProcessorTest::
testConfirmingRunDispatchesOneIndependentCaptureBPageAndPreservesCaptureA

Expected Work status: done
Actual Work status:   failed
```

Current production cause is exactly the known processor gap:

```text
capturing -> SalesAuditHandler
repairing -> SalesAuditRepairHandler
confirming -> fallback sales_audit_state -> failed
```

No production code or schema changed in the RED.

RED diff from checkpoint `bc43338...`:

```text
1 test file changed
tests/Integration/SalesWorkProcessorTest.php
no app code
no schema
no docs except this checkpoint
```

The test setup is somewhat repetitive; during GREEN, simplify test-only setup if it lowers net noise without obscuring behavior. Do not refactor unrelated production code.

---

# 3. CONTRACT PROVED / EXPECTED BY K6b-3 RED

A `sales.audit` Work whose durable run is `confirming` must process one independent Capture B page using existing infrastructure.

First-page B contract under test:

```text
same sales.audit Work type
same orders.search operation
same OAuth/MeliClient path
same canonical month/source contract
one remote page per Work
A evidence remains untouched
new observations persist as capture_pass='B'
run remains confirming on non-terminal B page
no order.sync fanout
```

For a non-terminal first B page:

```text
initial payload:
{run_id, offset, limit}

continuation payload:
{run_id, offset, limit, remote_total}
```

`remote_total` here is traversal execution state for B, not new durable business history. This avoids adding another column/table/run.

The RED response intentionally uses:

```text
paging.total=2
paging.offset=0
paging.limit=1
1 result
```

Therefore GREEN must make exactly one HTTP request, persist exactly one B observation, complete the current Work and enqueue exactly one B continuation at offset 1 carrying `remote_total=2`.

---

# 4. CURRENT A/B EVIDENCE CONTRACT — GREEN

`database/migrations/004_sales.sql`:

```text
sales_audit_orders

 audit_run_id
 capture_pass ENUM('A','B') NOT NULL DEFAULT 'A'
 external_order_id
 remote_date_created

PRIMARY KEY(audit_run_id, capture_pass, external_order_id)
```

Repository primitives:

```text
recordObservation(runId, orderId, dateCreated, capturePass='A')
observationCount(runId, capturePass='A')
canonicalFingerprint(runId, window, capturePass='A')
```

`persistCanonicalFingerprint()` remains A-only durable writer.

Repair/missing/verify SQL is explicitly `capture_pass='A'`.

No second run, table, queue, Work type, Work status, ConfirmRepository or ConfirmEngine exists.

---

# 5. SALES AUDIT RUNTIME GREEN BEFORE K6b-3

```text
CAPTURE A
-> durable A observations
-> stable source total
-> canonical A fingerprint
-> missing ? repairing : confirming
-> bounded one-child REPAIR
-> local VERIFY
-> confirming
```

Green safeguards:

```text
bigint-safe seller-search IDs
zoned date_created
MCO canonical month
remote_total drift guard
terminal A observation-count integrity
12-month source horizon -> unavailable before OAuth/HTTP
short non-terminal page -> fail closed
bounded one-child repair
terminal child + persistent gap -> attention
no automatic same-child recreation
A/B evidence identity independent
A/B observation count independent
A/B canonical fingerprint independent
repair/verify SQL A-only
```

---

# 6. CONFIRM DESIGN FROZEN

Target:

```text
capture A
-> repair
-> verify local
-> independent capture B
-> same canonical count/hash
-> valid
```

Accepted KISS constraints:

1. reuse `sales.audit`;
2. B writes `capture_pass='B'`;
3. A remains untouched;
4. no `confirm_count` / `confirm_hash` columns by default;
5. terminal B derives `canonicalFingerprint(...,'B')`;
6. compare against A `canonical_count/set_hash`;
7. B total stability may travel in Work payload;
8. mismatch -> durable `attention`;
9. equality required before `valid`.

Rejected absent new evidence:

```text
second audit run
A<->B relation table
confirm table
ConfirmRepository
ConfirmEngine
second queue
new Work type
new Work status
```

---

# 7. EXACT NEXT MICROBLOCK — K6b-3 GREEN ONLY

When user says `continua`:

1. fetch branch HEAD and this checkpoint;
2. confirm RED SHA `1fab5db...` and CI run `38069661480`;
3. inspect `SalesAuditHandler` and `SalesWorkProcessor` only as needed;
4. design the smallest reuse of existing capture logic for `confirming`;
5. do **not** duplicate source-horizon, OAuth, error, pagination or normalization logic into a second engine;
6. make RED test GREEN;
7. run full QA;
8. audit DELETE/SIMPLIFY/REUSE/MERGE;
9. simplify duplicated test setup if safe;
10. checkpoint and STOP.

Preferred direction:

```text
reuse/extend existing SalesAuditHandler behavior with a capture-pass/mode boundary
rather than create SalesAuditConfirmHandler with duplicated remote machinery
```

But do not force this if a smaller correct solution emerges from the RED.

Do NOT include terminal compare or `valid` in K6b-3 GREEN. The RED deliberately uses a non-terminal B page so that terminal semantics remain a separate microblock.

---

# 8. NEXT AFTER K6b-3 GREEN — NOT YET

Only after K6b-3 full GREEN + checkpoint:

```text
K6b-4 RED
-> terminal B integrity
-> B canonical fingerprint
-> compare A vs B
-> mismatch -> attention
```

`valid` should remain separate unless that RED proves it can be safely atomic and smaller without hiding behavior.

---

# 9. GATES

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary + K6a guards |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for current Sales paths |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — A path + source guards + A/B persistence/primitives GREEN; K6b-3 RED confirmed; B runtime still missing |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

---

# 10. STOP CONDITIONS

```text
STOP now until explicit user continua
NO K6b-3 production GREEN in this checkpoint
NO terminal B compare
NO valid
NO baseline lifecycle
NO Billing Task 2
NO Financial
NO merge
NO deploy
NO real ML HTTP
NO remote writes
```
