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

# 1. LAST FUNCTIONAL GREEN — K6b-3

```text
3240dfd23a9d5d79dcc1818004721fd23faad1ae
feat(v3-k6b3): dispatch confirming through capture B
```

Fresh full QA:

```text
RUN=38070168398
JOB=114265772278
PHP=8.5.11
PHPSTAN=0
PHPUNIT=204/204 PASS
ASSERTIONS=1400
MEMORY=22 MB
REAL_MELI_HTTP=0
```

K6b-3 runtime truth:

```text
confirming
-> same sales.audit Work type
-> same SalesAuditHandler
-> independent Capture B pages
-> B writes capture_pass='B'
-> A remains untouched
-> one page per Work
-> B remote_total travels only in continuation payload
-> terminal B deliberately fail-closed pending comparison semantics
```

No second run/table/queue/Work type/ConfirmRepository/ConfirmEngine exists.

---

# 2. K6b-4 — TERMINAL B MISMATCH — RED CONFIRMED

Initial RED commit:

```text
5b6bf514175c018c21fce8a0afec889ac7d9b9d3
test(v3-k6b4): prove terminal capture B mismatch becomes attention
```

The first push had delayed Actions registration. The same RED was strengthened, not changed in intent, by pinning the exact canonical B hash:

```text
64c34b0e7281b9ff8aec50e28ccd83a1bcfe05ec
test(v3-k6b4): pin terminal B canonical fingerprint mismatch
```

CI evidence:

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

Single intended failure:

```text
Tests\Integration\SalesAuditConfirmMismatchTest::
testTerminalCaptureBMismatchCompletesWorkAndMovesRunToAttention

Expected current Work status: done
Actual current Work status:   failed
```

This matches the deliberate K6b-3 terminal boundary:

```text
terminal B
-> SalesAuditHandler throws
   "Sales audit confirmation terminal comparison is not implemented."
-> completeCurrentClaim callback rolls back terminal B page
-> current Work becomes failed
```

No production code/schema changed in K6b-4 RED.

Noise audit from checkpoint `e8e149a...` to RED `64c34b0...`:

```text
1 file added only
tests/Integration/SalesAuditConfirmMismatchTest.php
+180/-0

no app code
no schema
no table
no Work type
no Work status
no queue
```

---

# 3. K6b-4 RED CONTRACT

The test models a real accumulated Capture B traversal:

```text
A durable canonical set:
{200000000099}

B page 1 already durable:
{200000000100}

terminal B Work:
offset=1
limit=1
remote_total=2

terminal response adds:
{200000000101}
```

Required GREEN behavior:

1. terminal B must only be accepted when durable B observation count equals B traversal `remote_total`;
2. derive canonical B fingerprint from existing `canonicalFingerprint(runId, window, 'B')`;
3. compare B canonical count/hash against durable A `canonical_count/set_hash`;
4. this RED intentionally has A/B mismatch;
5. mismatch must transition durable run `confirming -> attention`;
6. transition and current Work completion must be atomic;
7. terminal B observations must remain durable after accepted mismatch;
8. Capture A observations and durable A fingerprint must remain unchanged;
9. no continuation Work and no `order.sync` fanout;
10. no new table/column/state/Work type/queue/engine.

The RED also pins the exact B canonical hash for:

```text
200000000100\n200000000101
```

so GREEN must use the real canonical B set, not only a total-count comparison.

---

# 4. A/B PERSISTENCE CONTRACT ALREADY GREEN

Schema:

```text
sales_audit_orders
PRIMARY KEY(audit_run_id, capture_pass, external_order_id)
capture_pass ENUM('A','B') NOT NULL DEFAULT 'A'
```

Repository primitives:

```text
recordObservation(..., capturePass='A')
observationCount(..., capturePass='A')
canonicalFingerprint(..., capturePass='A')
```

`persistCanonicalFingerprint()` remains durable Capture A writer.

Repair/missing/verify SQL remains explicitly A-only.

`sales_audit_runs.remote_total`, `canonical_count`, `set_hash` remain Capture A durable evidence.

---

# 5. EXACT NEXT MICROBLOCK — K6b-4 GREEN ONLY

When user says `continua`:

1. fetch branch HEAD and this checkpoint;
2. confirm RED SHA `64c34b0...` and run `38070935380`;
3. inspect only `SalesAuditHandler` + `SalesAuditRepository` as needed;
4. implement the minimum terminal-B mismatch path;
5. preserve the existing transaction boundary of `completeCurrentClaim`;
6. require `observationCount(runId,'B') === remote_total` before terminal comparison;
7. derive B canonical fingerprint using existing primitive;
8. compare against durable A fingerprint without adding B fingerprint columns;
9. mismatch -> `attention` atomically with Work `done`;
10. full QA;
11. DELETE/SIMPLIFY/REUSE/MERGE noise audit;
12. checkpoint and STOP.

Preferred KISS direction:

```text
reuse existing repository primitives
+ one minimal durable A-fingerprint read / guarded transition primitive only if needed
```

Do not create a ConfirmRepository/ConfirmHandler/second run/table/history engine.

---

# 6. NOT IN K6b-4 GREEN

```text
NO valid transition yet
NO equality-success terminal behavior yet
NO baseline lifecycle
NO Sales Audit start UX/active-run guard
NO exact order 404 final classification
NO sale_fee alignment
NO webhook cleanup
NO Billing Task 2 before C0 sanitized smoke
NO Financial
NO merge
NO deploy
NO real Mercado Libre HTTP
NO remote writes
```

After K6b-4 GREEN + checkpoint, the next expected microblock is:

```text
K6b-5 RED
-> terminal B equality
-> A count/hash == B count/hash
-> confirming -> valid atomically
```

Do not implement K6b-5 inside K6b-4 unless a new RED proves combining them is materially smaller and clearer; default remains separate.

---

# 7. GATES

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary + K6a guards |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for current Sales paths |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — Capture A/repair/local verify/B traversal GREEN; terminal B mismatch RED confirmed; valid missing |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

---

# 8. STOP CONDITIONS

```text
STOP now until explicit user continua
NO K6b-4 production GREEN in this checkpoint
NO valid
NO Billing
NO Financial
NO merge
NO deploy
NO real ML HTTP
NO remote writes
```
