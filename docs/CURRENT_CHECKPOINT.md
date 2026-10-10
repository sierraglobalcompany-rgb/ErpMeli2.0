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
be56e3c11e121054299dd008beb736e69f4cb9db
test(v3-k6b1): align sales schema contract with A/B evidence
```

This SHA includes the K6b-1 production schema commit:

```text
34c48f6970224a6fda8c5cf4f5407021dc0b3fcd
feat(v3-k6b1): preserve independent audit capture evidence
```

Verified full QA:

```text
RUN=38067480529
JOB=114257963448
PHP=8.5.11
PHPSTAN=0
PHPUNIT=202/202 PASS
ASSERTIONS=1373
MEMORY=22 MB
REAL_MELI_HTTP=0
```

Important intermediate QA failure was understood and corrected without production expansion:

```text
34c48f6... schema GREEN candidate
-> only SalesSchemaTest had stale exact-column expectation
-> production behavior/schema itself matched the new K6b contract
-> test contract updated in be56e3c...
-> fresh full QA GREEN
```

---

# 2. K6b-0 — INDEPENDENT CONFIRM PERSISTENCE DESIGN — RED CLOSED

RED commit:

```text
091c5a19fe8f8c4a624854cb7e76176e1c61d266
test(v3-k6b0): prove independent confirm evidence needs A/B identity
```

RED evidence:

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
SalesAuditConfirmEvidenceSchemaTest::
testSalesAuditOrdersCanPreserveIndependentCaptureAAndBForTheSameOrder

Failed asserting that an array has the key 'capture_pass'.
```

The RED proved that the old identity:

```text
PRIMARY KEY(audit_run_id, external_order_id)
```

could not preserve independent A and B observations for the same order.

---

# 3. K6b-1 — MINIMAL A/B EVIDENCE SCHEMA — CLOSED GREEN

Pre-release schema was edited in place, not by adding a migration-number chain.

Current `sales_audit_orders` contract:

```text
audit_run_id BIGINT UNSIGNED NOT NULL
capture_pass ENUM('A','B') NOT NULL DEFAULT 'A'
external_order_id VARCHAR(32) NOT NULL
remote_date_created DATETIME(6) NOT NULL

PRIMARY KEY(
  audit_run_id,
  capture_pass,
  external_order_id
)
```

Why this is the selected KISS representation:

```text
1 audit run
1 evidence table
same sales.audit Work type
no new Work status
no second queue
no second business run
no confirm/history table
A and B can coexist
DEFAULT A preserves current CAPTURE inserts
```

Noise audit of production schema commit `321fa357... -> 34c48f6...`:

```text
1 production file changed
3 lines of diff
+2 / -1
no handler change
no repository change
no Work change
no new table
no new state
```

The only follow-up commit aligned a superseded exact-schema test expectation; no production code changed there.

---

# 4. CURRENT SALES AUDIT RUNTIME TRUTH

Closed path:

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

Current safeguards already GREEN:

```text
bigint-safe IDs
zoned date_created
MCO canonical month
stable remote_total
terminal observed-count integrity
short non-terminal page fail-closed
seller-search 12-month horizon -> unavailable before OAuth/HTTP
terminal repair child + persistent gap -> attention
no automatic same-child recreation
```

`confirming` remains a durable state, but **CONFIRM runtime is not implemented yet**.

Current `SalesWorkProcessor` still dispatches only:

```text
capturing -> SalesAuditHandler
repairing -> SalesAuditRepairHandler
```

A `sales.audit` claim for `confirming` still fails as `sales_audit_state`. Do not patch this without the next RED.

---

# 5. CONFIRM DESIGN FROZEN AFTER K6b-1

Authority objective remains:

```text
capture A
-> repair
-> verify local
-> independent capture B
-> same canonical count/hash
-> valid
```

Minimal design after proving A/B persistence:

1. B uses the same `sales.audit` Work type.
2. B writes `sales_audit_orders.capture_pass='B'`.
3. A evidence remains untouched during B.
4. Do **not** add `confirm_count` or `confirm_hash` columns by default.
5. At B terminal, derive B canonical count/hash from durable B observations and compare directly to A's existing `canonical_count`/`set_hash`.
6. B traversal `remote_total` stability is execution state, not business history; first-seen B total may travel in the continuation Work payload if RED proves that sufficient.
7. A/B mismatch must fail closed to durable `attention`; never overwrite A then call the run valid.
8. Equal A/B canonical count+hash is necessary before `valid`, but `valid` is not implemented yet.

This avoids:

```text
second audit run
A<->B relation table
second fingerprint columns unless proven necessary
confirm engine
history table
second queue
new Work type
new Work status
```

---

# 6. EXACT NEXT MICROBLOCK — K6b-2 ONLY

```text
K6b-2 — pass-aware evidence primitives RED
```

Scope must remain repository/test level only unless RED proves a tiny GREEN is safe:

1. prove `recordObservation` can target pass B while current callers remain pass A;
2. prove observation count/fingerprint queries can be scoped by capture pass;
3. A queries must never accidentally include B evidence;
4. preserve existing A behavior by default or explicit A parameter with minimal caller churn;
5. no network/runtime dispatch in this microblock;
6. RED first, verify single intended cause;
7. if GREEN is small, implement it, full QA, noise audit, checkpoint and STOP.

Preferred KISS direction:

```text
extend existing SalesAuditRepository primitives with capture pass
rather than create SalesAuditConfirmRepository
```

Do NOT in K6b-2:

```text
NO confirming dispatch
NO B HTTP traversal
NO valid transition
NO attention-on-mismatch runtime
NO new table/columns unless new RED proves necessity
NO second audit run
NO new Work type
NO Billing
NO Financial
NO sale_fee
NO webhook cleanup
NO merge/deploy
NO real ML HTTP
NO remote writes
```

---

# 7. DOCUMENTATION DRIFT WARNING

`README.md` remains the master product map, but its operational-status block predates K6a/K6b and is currently stale. Stable philosophy/architecture sections remain valid.

Before any larger CONFIRM runtime implementation, sync the README operational Sales Audit section so it reflects:

```text
K6a-1 GREEN
K6a-2 GREEN
K6b-0 RED closed
K6b-1 A/B schema GREEN
current next point K6b-2
```

Do not let stale README field lists override current schema + this checkpoint.

`docs/ERP2_AUTHORITY.md` also still documents the pre-K6b `sales_audit_orders` field list. Code/schema + this checkpoint are authoritative until that small documentation sync is performed.

---

# 8. GATES

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary + K6a guards |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for current Sales paths |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — A capture/repair/verify + source guards + A/B persistence schema GREEN; B traversal/compare/valid missing |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

---

# 9. OPEN GAPS — DO NOT MIX INTO K6b-2

```text
pass-aware evidence repository primitives
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

# 10. RESUME PROTOCOL

When user says `continua`:

```text
1. fetch branch HEAD
2. verify last GREEN SHA be56e3c... and CI run 38067480529
3. read AGENTS + Authority + this checkpoint
4. sync stale README/Authority operational schema text when a safe small docs edit is available
5. execute K6b-2 only
6. RED first
7. minimal GREEN only if scope stays repository-level
8. full QA
9. DELETE/SIMPLIFY/REUSE/MERGE audit
10. checkpoint and STOP
```

Do not re-audit the whole project. Do not ask where we were.
