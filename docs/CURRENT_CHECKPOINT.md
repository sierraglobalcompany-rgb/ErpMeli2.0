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

Runtime already GREEN:

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

# 2. K6b-5 — TERMINAL B EQUALITY -> VALID — RED CONFIRMED

Initial RED:

```text
d0f0cd5ac25c27c05c99e5e311693baa6d18f2b3
test(v3-k6b5): prove terminal capture B equality becomes valid
```

The RED was strengthened before checkpoint because the first `completed_at` assertion only rejected `false`, not SQL `NULL`:

```text
34fe4fe2446512849eeb20e34ec0f17e2743e79a
test(v3-k6b5): require completed_at for valid audit
```

Final RED CI evidence:

```text
RUN=38072597459
JOB=114272935153
PHP=8.5.11
PHPSTAN=0
PHPUNIT=206 tests
ASSERTIONS=1429
FAILURES=1
MEMORY=22 MB
REAL_MELI_HTTP=0
```

Single intended failure:

```text
Tests\Integration\SalesAuditConfirmValidTest::
testTerminalCaptureBEqualityCompletesWorkAndMovesRunToValid

Expected current Work status: done
Actual current Work status:   failed
```

Failure line:

```text
tests/Integration/SalesAuditConfirmValidTest.php:135
```

This proves the exact missing behavior: terminal Capture B with canonical count/hash equal to durable Capture A still hits the deliberate fail-closed boundary instead of completing `valid`.

No production code or schema changed.

---

# 3. K6b-5 RED CONTRACT

The test models:

```text
A canonical evidence:
{200000000100, 200000000101}

A canonical_count = 2
A set_hash = SHA-256("200000000100\n200000000101")

B page 1 already durable:
{200000000100}

terminal B Work:
offset=1
limit=1
remote_total=2

terminal response adds:
{200000000101}

Therefore:
B observationCount = 2
B canonical_count = 2
B set_hash == A set_hash
```

Required GREEN behavior:

1. terminal B integrity must still require `observationCount(runId,'B') === remote_total`;
2. derive B fingerprint using existing `canonicalFingerprint(runId, window, 'B')`;
3. equality means both canonical count and hash equal durable A fingerprint;
4. equality must transition durable run `confirming -> valid`;
5. set `completed_at` to a non-NULL/non-empty durable timestamp;
6. current `sales.audit` Work must become `done` in the same transaction;
7. terminal B observations must remain durable;
8. A observations and durable A fingerprint must remain unchanged;
9. no continuation `sales.audit` Work;
10. no `order.sync` fanout;
11. no new table/column/state/Work type/queue/engine.

Mismatch behavior from K6b-4 must remain unchanged:

```text
A/B mismatch -> attention
```

---

# 4. RED NOISE AUDIT

Delta from previous checkpoint `b73555a...` to final RED `34fe4fe...`:

```text
1 file added only
tests/Integration/SalesAuditConfirmValidTest.php
+194/-0

no app code
no schema
no table/column
no Work type/status
no queue
no engine
```

The test duplicates some setup from `SalesAuditConfirmMismatchTest` deliberately for this RED checkpoint. Do not refactor both tests before GREEN unless simplification is clearly smaller and does not blur the behavior boundary.

---

# 5. EXACT NEXT MICROBLOCK — K6b-5 GREEN ONLY

When user says `continua`:

1. verify active branch HEAD equals this checkpoint commit;
2. confirm RED SHA `34fe4fe...`, run `38072597459`, one intended failure;
3. inspect only terminal B branch in `SalesAuditHandler` and existing guarded mismatch transition;
4. implement the minimum equality-success path;
5. preserve terminal integrity check and canonical B fingerprint primitive;
6. preserve mismatch -> `attention` behavior;
7. equality -> `valid` + non-NULL `completed_at`;
8. keep transition + terminal B evidence + Work completion atomic inside existing `completeCurrentClaim` transaction;
9. no new repository/handler/table/column/state/Work type/queue/engine;
10. run fresh full QA;
11. noise audit RED -> GREEN;
12. checkpoint and STOP.

Preferred KISS direction:

```text
extend the existing terminal B guarded transition
reuse current transaction and durable A fingerprint
```

Do not open baseline lifecycle in K6b-5 GREEN.

---

# 6. RUNTIME TRUTH BEFORE GREEN

Proven flow today:

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
-> equality A/B -> currently fail closed (RED proves missing valid path)
```

Capture B reuses the same:

```text
sales.audit Work type
SalesAuditHandler
OAuth/MeliClient/orders.search path
source-horizon guard
page-contract/short-page guard
```

---

# 7. NOT IN K6b-5 GREEN

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

# 8. GATES

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary + K6a guards |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for current Sales paths |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — A capture/repair/verify + B traversal + mismatch attention GREEN; equality->valid RED confirmed |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

---

# 9. TOOLING NOTE

Active authoritative branch remains only:

```text
impl/v3-b-sales-audit-20261010
```

Two auxiliary refs (`tmp` and `impl/v3-b-sales-audit-20261010-red`) were created accidentally during tooling interaction and point only to the prior documentation checkpoint. They are non-authoritative and must not be used for continuation. The available connector does not expose branch deletion; clean them later when a deletion-capable Git tool is available.

---

# 10. STOP CONDITIONS

```text
STOP now until explicit user continua
NO K6b-5 production GREEN yet
NO baseline lifecycle
NO Billing
NO Financial
NO merge
NO deploy
NO real Mercado Libre HTTP
NO remote writes
```
