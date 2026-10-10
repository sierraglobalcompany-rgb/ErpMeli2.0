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

---

# 1. K6b-4 — TERMINAL B MISMATCH — CLOSED GREEN

RED:

```text
64c34b0e7281b9ff8aec50e28ccd83a1bcfe05ec
test(v3-k6b4): pin terminal B canonical fingerprint mismatch
```

RED QA:

```text
RUN=38070935380
JOB=114268024652
PHP=8.5.11
PHPSTAN=0
PHPUNIT=205 tests
ASSERTIONS=1407
FAILURES=1
MEMORY=22 MB
REAL_MELI_HTTP=0
```

Single intended failure was current Work `failed` instead of expected `done` because terminal Capture B deliberately failed closed.

Functional GREEN:

```text
3486ecc1783e40abf8321de5780535700253da2f
feat(v3-k6b4): mark terminal capture B mismatch attention
```

Fresh full QA:

```text
RUN=38071706793
JOB=114270259722
PHP=8.5.11
PHPSTAN=0
PHPUNIT=205/205 PASS
ASSERTIONS=1419
MEMORY=22 MB
REAL_MELI_HTTP=0
```

Noise audit from checkpoint `0673809...` to functional GREEN `3486ecc...`:

```text
1 existing production file only
app/Modules/Sales/Audit/SalesAuditHandler.php
+25/-2

no schema
no table
no column
no new class
no Work type
no Work status
no queue
no second run
no ConfirmRepository/ConfirmEngine
```

---

# 2. RUNTIME TRUTH NOW

Sales Audit proven flow:

```text
CAPTURE A
-> durable A observations
-> stable A remote_total
-> canonical A fingerprint
-> missing ? repairing : confirming
-> bounded one-child REPAIR
-> local VERIFY
-> confirming
-> independent Capture B traversal
-> terminal B integrity check
-> B canonical fingerprint
-> A/B mismatch -> attention
```

Terminal Capture B mismatch contract now GREEN:

```text
terminal B
-> require observationCount(runId,'B') == B traversal remote_total
-> canonicalFingerprint(runId, window, 'B')
-> compare against durable A canonical_count/set_hash
-> mismatch -> confirming -> attention
-> terminal B evidence remains durable
-> A observations remain unchanged
-> durable A fingerprint remains unchanged
-> current sales.audit Work -> done
-> no continuation
-> no order.sync fanout
```

The comparison and `attention` update execute inside the existing `completeCurrentClaim` transaction, so terminal B evidence + run transition + Work completion commit atomically.

No B fingerprint columns were added. B fingerprint remains derivable from `sales_audit_orders`.

---

# 3. DELIBERATE FAIL-CLOSED BOUNDARY STILL OPEN

A/B equality is intentionally **not implemented yet**.

Current equality behavior:

```text
B count/hash == durable A count/hash
-> mismatch UPDATE affects 0 rows
-> handler throws
-> completeCurrentClaim rolls back terminal page
-> current Work fails closed
-> NO false valid
```

This is temporary by design until K6b-5 proves `valid` with a RED.

Do not patch around this boundary.

---

# 4. A/B EVIDENCE CONTRACT

Schema remains:

```text
sales_audit_orders
PRIMARY KEY(audit_run_id, capture_pass, external_order_id)
capture_pass ENUM('A','B') NOT NULL DEFAULT 'A'
```

Repository primitives remain:

```text
recordObservation(..., capturePass='A')
observationCount(..., capturePass='A')
canonicalFingerprint(..., capturePass='A')
```

Durable `sales_audit_runs.remote_total`, `canonical_count`, `set_hash` are Capture A evidence.

Repair/missing/local verify SQL remains explicitly A-only.

Capture B traversal uses the same `sales.audit`, `SalesAuditHandler`, OAuth/MeliClient/orders.search path and source guards.

B `remote_total` is traversal execution state carried only in continuation Work payload.

---

# 5. DOCUMENTATION SYNC REQUIRED NEXT

`README.md` and `docs/ERP2_AUTHORITY.md` were last synchronized before K6b-3/K6b-4 runtime closure.

Before opening `valid`, perform one bounded documentation sync so living docs state:

```text
K6b-3 independent B traversal = GREEN
K6b-4 terminal mismatch -> attention = GREEN
A/B equality -> valid = still missing
```

This must be documentation-only. No production/schema/test changes.

---

# 6. EXACT NEXT MICROBLOCS

## K6b-DOC — next

When user says `continua`:

1. verify branch HEAD equals this checkpoint commit;
2. update only `README.md` and `docs/ERP2_AUTHORITY.md` to current K6b-4 GREEN truth;
3. remove obsolete statements that say terminal B is wholly unimplemented;
4. preserve the explicit equality fail-closed boundary;
5. audit diff as documentation-only;
6. checkpoint and STOP.

## K6b-5 — after K6b-DOC, not yet

RED only:

```text
terminal B equality
-> B count/hash == A count/hash
-> current Work done
-> confirming -> valid atomically
-> completed_at set
-> A/B evidence preserved until baseline lifecycle is separately defined
```

Do not implement GREEN for `valid` in the same RED checkpoint unless explicitly resumed afterward.

---

# 7. GATES

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary + K6a guards |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for current Sales paths |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — A capture/repair/verify + B traversal + terminal mismatch attention GREEN; equality/valid missing |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

---

# 8. STOP CONDITIONS

```text
STOP now until explicit user continua
NO valid yet
NO baseline lifecycle
NO Billing
NO Financial
NO merge
NO deploy
NO real Mercado Libre HTTP
NO remote writes
```
