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

# 1. LAST FULLY GREEN FUNCTIONAL STATE

```text
bdca863c85a0f6529f676cf78f3e495e6e7aab60
feat(v3-k6a2): reject short nonterminal audit pages
```

Verified QA:

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

K6a is closed:

```text
K6a-1 seller-search horizon -> unavailable before OAuth/HTTP
K6a-2 short non-terminal page -> fail closed
```

Current Sales Audit truth through K6a:

```text
CAPTURE
-> fingerprint A
-> missing ? repairing : confirming
-> bounded one-child REPAIR
-> local VERIFY
-> confirming
```

`confirming` is durable but runtime CONFIRM is still intentionally unimplemented.

---

# 2. K6b-0 — INDEPENDENT CONFIRM PERSISTENCE DESIGN — RED CONFIRMED

RED commit:

```text
091c5a19fe8f8c4a624854cb7e76176e1c61d266
test(v3-k6b0): prove independent confirm evidence needs A/B identity
```

CI evidence:

```text
RUN=38066686637
JOB=114255647932
PHP=8.5.11
PHPSTAN=0
PHPUNIT=202 tests
ASSERTIONS=1371
FAILURES=1
MEMORY=22 MB
REAL_MELI_HTTP=0
```

Single intended failure:

```text
Tests\Integration\SalesAuditConfirmEvidenceSchemaTest::
testSalesAuditOrdersCanPreserveIndependentCaptureAAndBForTheSameOrder

Failed asserting that an array has the key 'capture_pass'.
```

Interpretation:

```text
RED is clean.
No production/schema GREEN has been written.
No collateral test failure exists.
```

---

# 3. DESIGN DECISION PROVED FOR K6b

Authority requires first certification:

```text
capture A
-> repair
-> verify local
-> independent capture B
-> same canonical count/hash
-> valid
```

Current schema cannot preserve A and B independently because:

```text
sales_audit_orders PRIMARY KEY(audit_run_id, external_order_id)
```

The same external order cannot coexist as evidence from both traversals.

## Rejected alternatives

### Second `sales_audit_run` for B

Rejected for now because it creates more complexity:

```text
A<->B relation
second business lifecycle
orphan/cleanup semantics
ambiguous baseline linkage
more states/coordination
```

### Delete/overwrite A before B

Rejected because independent confirmation must preserve A until comparison completes.

### New confirmation table/history engine

Rejected by KISS: unnecessary extra persistence surface.

## Minimal selected representation

Extend the existing evidence table only:

```text
sales_audit_orders
+ capture_pass ENUM('A','B') NOT NULL DEFAULT 'A'

PRIMARY KEY(
  audit_run_id,
  capture_pass,
  external_order_id
)
```

Why this is minimal:

- one audit run;
- one evidence table;
- same `sales.audit` Work type;
- no new Work state;
- no new table;
- no second queue;
- no confirm engine;
- A remains intact while B is captured;
- same order ID may legitimately appear in A and B;
- `DEFAULT 'A'` keeps existing CAPTURE inserts compatible during the schema microblock.

Official Mercado Libre Orders Search documentation currently exposes a normal paginated seller search and no reusable snapshot identifier for proving the same result set later. Therefore B must be a genuinely separate traversal of the same source contract, not reuse A response state.

---

# 4. IMPORTANT CONFIRM RUNTIME CONSTRAINTS — NOT IMPLEMENTED YET

Do not implement these in the next schema-only GREEN unless explicitly reached by a later RED:

```text
confirming dispatch
B paging
B remote_total stability
B canonical fingerprint
A-vs-B comparison
valid
attention on mismatch
completed_at
baseline cleanup
```

Future CONFIRM should still reuse:

```text
sales.audit
MeliClient
orders.search
SalesAuditWindow
same source horizon rule
same short-page rule
same OAuth/429/retry semantics where applicable
```

Technical traversal state such as B offset/limit and first-seen B remote total may live in Work payload if proven sufficient; do not persist new business columns merely for execution state.

---

# 5. EXACT NEXT MICROBLOCK — K6b-1 ONLY

```text
K6b-1 — schema GREEN for independent A/B evidence
```

Scope:

1. edit pre-release `database/migrations/004_sales.sql` in place;
2. add `capture_pass ENUM('A','B') NOT NULL DEFAULT 'A'`;
3. change only the `sales_audit_orders` primary key to `(audit_run_id,capture_pass,external_order_id)`;
4. make `SalesAuditConfirmEvidenceSchemaTest` GREEN;
5. run full QA;
6. audit diff/noise;
7. verify existing CAPTURE still works unchanged because default is A;
8. update Authority/README only if GREEN confirms this contract;
9. checkpoint and STOP.

Do NOT in K6b-1:

```text
NO confirming runtime handler
NO SalesWorkProcessor confirming dispatch
NO valid transition
NO B network request
NO new table
NO second audit run
NO new Work type
NO Billing
NO Financial
NO webhook cleanup
NO sale_fee work
NO merge/deploy
NO real ML HTTP
NO remote writes
```

---

# 6. GATES

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary + K6a guards |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for current Sales paths |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — A capture/repair/verify + source guards green; B persistence RED confirmed |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

---

# 7. OPEN GAPS — DO NOT MIX INTO K6b-1

```text
CONFIRM runtime / independent B traversal
A-vs-B equal hash/count -> valid
mismatch -> durable attention
valid/baseline lifecycle
start UX + active-run guard
exact order 404 final audit classification
sale_fee schema alignment before Financial
webhook_events lifecycle
MariaDB session timezone certification
Billing C0
Hostinger/runtime/main protection
```

---

# 8. RESUME PROTOCOL

When user says `continua`:

```text
1. fetch branch HEAD
2. confirm K6b-0 RED commit + CI evidence above
3. read AGENTS + Authority + this checkpoint
4. implement K6b-1 schema GREEN only
5. full QA
6. DELETE/SIMPLIFY/REUSE/MERGE audit
7. sync README/Authority if contract becomes GREEN
8. checkpoint and STOP
```

Do not re-audit the whole project. Do not ask where we were.
