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
364256b1cc9135f33c780005c398c60d95e5882a
feat(v3-k6b2): isolate audit evidence by capture pass
```

Verified full QA:

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
PHP=8.5.11
PHPSTAN=0
PHPUNIT=203 tests
ASSERTIONS=1377
FAILURES=1
MEMORY=22 MB
REAL_MELI_HTTP=0
```

Single intended RED failure:

```text
SalesAuditEvidencePassTest::
testEvidencePrimitivesSeparateCapturePassesWithoutChangingDefaultA

Failed asserting that false is true.
```

Cause proved:

```text
recordObservation(..., 'B') was still treated as A
-> same external_order_id collided with A identity
```

No collateral failures existed.

---

# 2. K6b-1 — A/B EVIDENCE SCHEMA — CLOSED GREEN

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

Why this remains the selected KISS representation:

```text
1 audit run
1 evidence table
same sales.audit Work type
no new Work status
no second queue
no second business run
no confirm/history table
A and B can coexist
DEFAULT A preserves current CAPTURE callers
```

---

# 3. K6b-2 — PASS-AWARE EVIDENCE PRIMITIVES — CLOSED GREEN

Implemented only inside existing `SalesAuditRepository`.

## `recordObservation`

Current contract:

```text
recordObservation(runId, orderId, dateCreated, capturePass='A')
```

Properties:

- existing callers remain A by default;
- B can store the same external order independently;
- duplicate identity is per `(run, pass, order)`;
- unsupported pass fails closed;
- no new repository/class/table/state.

## `observationCount`

Current contract:

```text
observationCount(runId, capturePass='A')
```

A and B counts are independent.

Existing CAPTURE terminal integrity remains A by default.

## `canonicalFingerprint`

Reusable read primitive now exists:

```text
canonicalFingerprint(runId, window, capturePass='A')
→ canonical_count
→ set_hash
```

It:

- filters by capture pass;
- filters canonical window;
- sorts external IDs deterministically;
- hashes the canonical serialization exactly once.

`persistCanonicalFingerprint()` now reuses this primitive for pass A instead of duplicating the fingerprint query.

## Repair remains A-only

All repository SQL that drives missing-set / repair / local verify is now explicitly scoped to:

```text
capture_pass='A'
```

This prevents future B observations from contaminating:

```text
post-CAPTURE missing decision
repair candidate selection
repair completion / confirming transition
```

## Noise audit

RED -> GREEN production diff:

```text
1 production file changed
SalesAuditRepository.php only
+59 / -28
no schema change
no handler change
no Work change
no new class
no new table
no new run status
no new Work type
no network behavior
```

Net growth is justified by one second-consumer primitive (`canonicalFingerprint`) plus shared capture-pass validation; existing fingerprint SQL was merged into the reusable primitive rather than duplicated.

---

# 4. CURRENT SALES AUDIT RUNTIME TRUTH

Closed runtime path:

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

Current safeguards GREEN:

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
A/B evidence identity independent
A/B count/fingerprint primitives independent
repair SQL insulated from B evidence
```

`confirming` is durable but CONFIRM runtime is still intentionally unimplemented.

Current `SalesWorkProcessor` still dispatches only:

```text
capturing -> SalesAuditHandler
repairing -> SalesAuditRepairHandler
```

A `sales.audit` claim for `confirming` still fails as `sales_audit_state`.

Do not patch that state without the next RED.

---

# 5. CONFIRM DESIGN STILL FROZEN

Authority objective:

```text
capture A
-> repair
-> verify local
-> independent capture B
-> same canonical count/hash
-> valid
```

Minimal accepted design:

1. B uses the same `sales.audit` Work type.
2. B writes `capture_pass='B'` into the same `sales_audit_orders` table.
3. A evidence remains untouched while B runs.
4. Do not add `confirm_count` or `confirm_hash` columns by default.
5. At B terminal derive B count/hash with `canonicalFingerprint(..., 'B')`.
6. Compare B directly with A's existing `canonical_count` / `set_hash`.
7. B traversal remote-total stability is execution state, not business history; payload is preferred unless evidence proves otherwise.
8. Mismatch must become durable `attention`.
9. Equality is necessary before `valid`, but `valid` runtime is still not implemented.

Rejected unless new evidence appears:

```text
second audit run
A<->B relation table
confirm repository
confirm engine
history table
second queue
new Work type
new Work status
```

---

# 6. DOCUMENTATION DRIFT — MUST BE CLOSED BEFORE CONFIRM GREEN

`README.md` remains the master product map, but its operational Sales Audit block still predates K6a/K6b.

`docs/ERP2_AUTHORITY.md` also still lists the pre-K6b `sales_audit_orders` identity.

This is known drift, not code truth.

Because code/schema + tests + this checkpoint outrank those stale sections, no contract is ambiguous now; however before a larger CONFIRM runtime GREEN, perform one small documentation-sync microblock that updates only current facts and removes superseded field lists.

Required sync facts:

```text
K6a-1 GREEN: old seller-search period -> unavailable before OAuth/HTTP
K6a-2 GREEN: short non-terminal page -> fail closed
K6b-1 GREEN: capture_pass A/B schema
K6b-2 GREEN: pass-aware record/count/fingerprint + A-only repair SQL
next runtime point: confirming
```

Do not turn docs into a changelog. Replace stale truth; Git keeps history.

---

# 7. EXACT NEXT MICROBLOCK

Before runtime production changes:

```text
K6b-DOC — sync README + Authority current Sales Audit truth
```

Then, in a separate microblock:

```text
K6b-3 — CONFIRM first-page / confirming-dispatch RED only
```

K6b-3 RED should prove, without implementing `valid` yet:

1. a `sales.audit` Work whose durable run state is `confirming` does not fail as unknown state;
2. it performs the independent B traversal through existing Mercado Libre read path;
3. observations go only to pass B;
4. A observations are untouched;
5. one page per Work remains true;
6. pagination uses the existing short-page/source-horizon protections;
7. no second Work type, engine, table or run is introduced.

Do NOT combine K6b-DOC and K6b-3 GREEN in one large block.

---

# 8. GATES

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

# 9. OPEN GAPS — DO NOT MIX INTO NEXT BLOCK

```text
README/Authority current-truth sync
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
2. verify K6b-2 GREEN SHA 364256b... + CI run 38068906148
3. read AGENTS + this checkpoint
4. perform K6b-DOC only: replace stale README/Authority Sales Audit facts
5. checkpoint if documentation edit is substantial
6. only after docs truth is synchronized, open K6b-3 RED
7. do not implement valid yet
```

No whole-project re-audit. Do not ask where we were.
