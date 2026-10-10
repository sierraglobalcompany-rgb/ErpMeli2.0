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

K6b-2 RED was:

```text
a6acfc9339d221685e4eba3cfa6a33875707d00b
test(v3-k6b2): prove audit evidence primitives isolate A and B
```

RED evidence:

```text
RUN=38068771263
JOB=114261715811
PHPSTAN=0
PHPUNIT=203 tests
ASSERTIONS=1377
FAILURES=1
```

Single intended failure proved that `recordObservation(...,'B')` was still colliding with pass A. No collateral failure existed.

---

# 2. SALES AUDIT TRUTH NOW GREEN

Runtime currently proven:

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
repair/verify SQL explicitly A-only
```

---

# 3. CURRENT A/B EVIDENCE CONTRACT

`database/migrations/004_sales.sql`:

```text
sales_audit_orders

 audit_run_id
 capture_pass ENUM('A','B') NOT NULL DEFAULT 'A'
 external_order_id
 remote_date_created

PRIMARY KEY(audit_run_id, capture_pass, external_order_id)
```

Current repository primitives:

```text
recordObservation(runId, orderId, dateCreated, capturePass='A')
observationCount(runId, capturePass='A')
canonicalFingerprint(runId, window, capturePass='A')
```

`persistCanonicalFingerprint()` remains the durable A fingerprint writer and reuses `canonicalFingerprint` instead of duplicating SQL.

All repair/missing/verify queries are anchored to `capture_pass='A'`.

No second run, table, queue, Work type, Work status, confirm repository or confirm engine exists.

---

# 4. SOURCE CONTRACT K6a — CLOSED

Current seller-search truth:

```text
approximately 12 months of seller history
seller search filters canceled orders
not an absolute historical snapshot of Mercado Libre
```

K6a-1 GREEN:

```text
period outside supported source horizon
-> unavailable
-> Work done
-> before OAuth
-> before HTTP
```

K6a-2 GREEN:

```text
count(results) < paging.limit
AND offset + limit < total
-> fail closed
```

Do not reopen without new official or controlled real evidence.

---

# 5. K6b-DOC — CLOSED

Documentation drift was removed before opening CONFIRM runtime.

Updated:

```text
README.md
-> master map now reflects K6a + K6b GREEN truth
-> stale K6a RED/current-pause block removed
-> current functional SHA/QA updated
-> next resume point is K6b-3

docs/ERP2_AUTHORITY.md
-> sales_audit_orders A/B schema corrected
-> source guards added
-> A/B primitives added
-> immediate execution order corrected to CONFIRM path
```

Noise audit from previous checkpoint through README/Authority sync:

```text
documentation only
README.md
docs/ERP2_AUTHORITY.md
no production code
no schema
no tests
no Work/runtime behavior
```

Git stores superseded documentation history; no duplicate “old” docs were created.

---

# 6. CONFIRM DESIGN FROZEN

Objective:

```text
capture A
-> repair
-> verify local
-> independent capture B
-> same canonical count/hash
-> valid
```

Minimal accepted design:

1. same `sales.audit` Work type;
2. B writes `capture_pass='B'` into the same table;
3. A remains untouched;
4. no `confirm_count` / `confirm_hash` columns by default;
5. terminal B derives fingerprint using `canonicalFingerprint(...,'B')`;
6. compare B to A `canonical_count/set_hash`;
7. first-seen B remote total is execution state, preferably Work payload;
8. mismatch must become durable `attention`;
9. equality is required before `valid`.

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

# 7. EXACT NEXT MICROBLOCK — K6b-3 RED ONLY

```text
K6b-3 — confirming dispatch + first independent B page
```

Create RED that proves:

1. a `sales.audit` claim whose durable run state is `confirming` must not fail as `sales_audit_state`;
2. it uses the existing Mercado Libre read path / `orders.search`;
3. the returned observations persist only as pass B;
4. existing pass A evidence is unchanged;
5. one page per Work remains true;
6. source-horizon and short-page rules are reused, not reimplemented as a second engine;
7. no second Work type, queue, table, audit run or coordinator engine is introduced.

K6b-3 is **RED only first**. Confirm the single intended failure before production GREEN.

Do NOT implement in the same large jump:

```text
valid
baseline lifecycle
Billing Task 2
Financial
```

---

# 8. CURRENT PROCESSOR GAP

`SalesWorkProcessor` currently handles:

```text
capturing -> SalesAuditHandler
repairing -> SalesAuditRepairHandler
```

A run in:

```text
confirming
```

still fails as unsupported `sales_audit_state`.

That is the intended next RED target, not a production bug to patch without TDD.

---

# 9. GATES

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary + K6a guards |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for current Sales paths |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — A capture/repair/verify + source guards + A/B persistence/primitives GREEN; B runtime/compare/valid missing |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

---

# 10. OPEN GAPS — DO NOT MIX INTO K6b-3

```text
CONFIRM B runtime
A-vs-B compare -> attention/valid
valid/baseline lifecycle
start UX + active-run guard
exact order 404 final audit classification
sale_fee schema/persistence alignment before Financial
webhook_events lifecycle
MariaDB session timezone certification
Billing C0
Hostinger/runtime/main protection
```

---

# 11. RESUME PROTOCOL

When user says `continua`:

```text
1. fetch branch HEAD
2. verify functional GREEN 364256b... + QA run 38068906148
3. read AGENTS + this checkpoint; README/Authority are now synchronized
4. inspect SalesWorkProcessor + SalesAuditHandler + current tests only
5. create K6b-3 confirming/first-B-page RED
6. run CI and prove one intended failure
7. checkpoint if context grows; otherwise minimal GREEN in the next bounded step
8. do not implement valid yet
```

Do not re-audit the whole project. Do not ask where we were. Do not open Billing/Financial.
