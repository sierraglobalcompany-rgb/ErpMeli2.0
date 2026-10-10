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

# 1. LAST FUNCTIONAL GREEN — K6b-4

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

K6b-4 runtime truth:

```text
CAPTURE A
-> repair / local verify
-> confirming
-> independent Capture B traversal
-> terminal B requires durable B count == B remote_total
-> canonicalFingerprint(...,'B')
-> compare with durable A canonical_count/set_hash
-> mismatch -> attention
-> Work done atomically
```

A and B evidence remains durable. No B fingerprint columns, second run, second queue, ConfirmRepository or ConfirmEngine exist.

---

# 2. K6b-DOC — CLOSED

Documentation sync commits:

```text
6159164313f61871cc22e0f18e2ca3d5f8eda28c
docs(v3-k6b): sync authority through terminal B mismatch

a1f516472a41c8f1cd25a79259e6f83ab2bd0237
docs(v3-k6b): sync master map through terminal B mismatch
```

Documentation truth now states:

```text
K6b-3 independent Capture B traversal = GREEN
K6b-4 terminal B count/fingerprint + mismatch -> attention = GREEN
A/B equality -> valid = NOT IMPLEMENTED
```

Noise audit from previous checkpoint `902c60f...` to README sync `a1f5164...`:

```text
README.md                +59/-59
docs/ERP2_AUTHORITY.md   +32/-26

100% documentation
no app code
no schema
no tests
no table/column
no Work type/status
no queue/engine
```

No functional QA was rerun for the documentation-only sync. The latest functional QA remains run `38071706793` on `3486ecc...`.

---

# 3. RUNTIME TRUTH NOW

Proven Sales Audit flow:

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
-> stable B remote_total in Work continuation payload
-> terminal B durable count/fingerprint
-> mismatch A/B -> attention
```

Capture B reuses:

```text
same sales.audit Work type
same SalesAuditHandler
same OAuth/MeliClient/orders.search read path
same source-horizon guard
same page-contract/short-page guard
```

No second architecture exists for CONFIRM.

---

# 4. DELIBERATE FAIL-CLOSED BOUNDARY

A/B equality is intentionally not implemented.

Current equality behavior:

```text
B count/hash == A count/hash
-> mismatch guarded UPDATE affects 0 rows
-> handler throws
-> completeCurrentClaim rolls back terminal B page
-> Work fails closed
-> run does NOT become valid
```

This prevents false `valid` until K6b-5 proves the success path by RED.

Do not patch around this boundary.

---

# 5. EXACT NEXT MICROBLOCK — K6b-5 RED ONLY

When user says `continua`:

1. verify branch HEAD equals this checkpoint commit;
2. inspect only terminal B comparison path + relevant test fixtures;
3. add one focused RED for equality A/B;
4. model a terminal B with durable evidence and `observationCount(B) == remote_total`;
5. make canonical B count/hash exactly equal durable A count/hash;
6. require current Work `done`;
7. require run `confirming -> valid` atomically;
8. require `completed_at` to be set;
9. require A/B evidence preserved;
10. require no continuation and no `order.sync` fanout;
11. confirm one intended failure only;
12. checkpoint and STOP.

K6b-5 RED should prove the missing equality-success contract only.

Do not implement GREEN in the same checkpoint unless explicitly resumed afterward.

Preferred KISS direction for the later GREEN:

```text
extend the existing terminal B guarded transition
no new repository/handler/table/column/state/Work type/queue/engine
```

---

# 6. NOT IN K6b-5 RED

```text
NO baseline lifecycle
NO pruning of prior valid runs
NO Sales Audit start UX / active-run guard
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

---

# 7. GATES

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary + K6a guards |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for current Sales paths |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — A capture/repair/verify + B traversal + mismatch attention GREEN; equality/valid missing |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

---

# 8. STOP CONDITIONS

```text
STOP now until explicit user continua
NO K6b-5 GREEN yet
NO valid yet
NO baseline lifecycle
NO Billing
NO Financial
NO merge
NO deploy
NO real Mercado Libre HTTP
NO remote writes
```
