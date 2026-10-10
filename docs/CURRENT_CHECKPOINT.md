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

# 1. LAST FUNCTIONAL GREEN — K6c-0

Functional GREEN:

```text
436210d0ff576de8189dfb35434305de271b9ce0
feat(v3-k6c0): replace equivalent valid baseline
```

Fresh full QA for that functional SHA:

```text
RUN=38074658105
JOB=114278993275
PHP=8.5.11
PHPSTAN=0
PHPUNIT=207/207 PASS
ASSERTIONS=1460
MEMORY=22 MB
REAL_MELI_HTTP=0
```

K6c-0 behavior:

```text
new valid fingerprint == prior valid baseline fingerprint
-> new run remains newest valid baseline
-> prior equivalent valid run is deleted
-> prior A+B evidence is pruned by existing ON DELETE CASCADE
-> terminal B + valid transition + replacement + Work done are atomic
```

No baseline pointer/table/history engine/new state was added.

---

# 2. SALES AUDIT CORE TRUTH

```text
CAPTURE A
-> durable A evidence + canonical fingerprint
-> bounded REPAIR / local VERIFY
-> confirming
-> independent Capture B traversal
-> terminal B count/fingerprint
-> mismatch A/B -> attention + Work done atomically
-> equality A/B -> valid + completed_at + Work done atomically
```

`valid` means consistent/verified relative to seller-search and the known contract for that run, not an absolute guarantee of the complete historical Mercado Libre universe.

---

# 3. BASELINE AUTHORITY RULE — BINDING

```text
conservar el más reciente válido;
evidencia superseded equivalente se elimina.
Si aparece diferencia, conservar baseline anterior + run attention hasta resolver.
```

K6c-0 closed the equivalent replacement half. K6c-1 now proves the divergent-fingerprint half.

---

# 4. K6c-1 — DIVERGENT BASELINE — RED CONFIRMED

RED commit:

```text
04fbc19d1a4eb99eec63cb6318cecbcfc0f9ccae
test(v3-k6c1): prove divergent baseline attention
```

Test:

```text
tests/Integration/SalesAuditBaselineLifecycleTest.php

testNewInternallyConfirmedDifferentFingerprintPreservesPriorBaselineAndEndsAttention
```

Scenario:

```text
prior durable baseline:
  scope = company 1 / account 1 / 2026-10-01 / seller-search-v1
  status = valid
  A+B canonical set = {200000000100, 200000000101}

new independent run:
  same scope/contract
  A canonical set = {200000000100, 200000000102}
  local verification passes
  B independently confirms exactly the same new set
  therefore A == B internally
  but new fingerprint != prior valid baseline fingerprint
```

Required lifecycle contract:

```text
terminal Work = done
prior baseline remains valid
prior baseline A+B evidence remains durable
new run ends attention, not valid
new run A+B evidence remains durable for diagnosis
exactly one valid baseline remains
no continuation Work
no order.sync fanout
```

Current behavior proven by RED:

```text
prior baseline survives correctly
new A == new B
current code marks new run valid
prior baseline has different hash, so K6c-0 equivalent delete does not remove it
result = two conflicting valid truths
```

This is the exact missing behavior.

---

# 5. K6c-1 RED QA

```text
RUN=38075246021
JOB=114280747237
PHP=8.5.11
PHPSTAN=0
PHPUNIT=208 tests
ASSERTIONS=1475
FAILURES=1
MEMORY=22 MB
REAL_MELI_HTTP=0
```

Single intended failure:

```text
Tests\Integration\SalesAuditBaselineLifecycleTest::
testNewInternallyConfirmedDifferentFingerprintPreservesPriorBaselineAndEndsAttention

An internally confirmed run that differs from the valid baseline must end attention.
Expected: 'attention'
Actual:   'valid'
```

Failure line at RED SHA:

```text
tests/Integration/SalesAuditBaselineLifecycleTest.php:331
```

The usual Slim 404 output from `BootstrapTest::testStoragePathIsNotExposedAsApplicationRoute` appears in logs but is benign and not a failure.

---

# 6. RED NOISE AUDIT

Delta from prior checkpoint `f14d2c6f7150600f729e55341f892c042d631c4f` to RED `04fbc19d1a4eb99eec63cb6318cecbcfc0f9ccae`:

```text
1 test file modified only
tests/Integration/SalesAuditBaselineLifecycleTest.php
+174/-1
```

The one deletion is only the previous fixed transport response replaced by a parameterized test transport default; existing equivalent-baseline test behavior remains unchanged.

No:

```text
app code
schema
table/column
new production class
repository
engine
state
Work type/status
queue
cron
```

---

# 7. EXACT NEXT MICROBLOCK — K6c-1 GREEN ONLY

When user says `continua`:

1. verify branch HEAD equals this checkpoint commit;
2. preserve K6c-0 equivalent replacement GREEN;
3. inspect only the terminal B equality transaction and `sales_audit_runs` query needs;
4. implement the minimum guarded baseline comparison before committing the new run as valid;
5. same scope means:
   - same company_id;
   - same account_id;
   - same period_key;
   - same contract_version;
   - prior row status = valid;
   - prior id != current run;
6. if no prior valid baseline exists, keep current equality behavior -> valid;
7. if prior valid baseline exists and its canonical_count/set_hash equals the current run fingerprint, keep K6c-0 behavior -> current valid + prune prior equivalent baseline;
8. if prior valid baseline exists and its canonical_count/set_hash differs, current run must transition confirming -> attention, preserving both prior baseline and new A+B evidence;
9. keep Work completion atomic in the existing `completeCurrentClaim` transaction;
10. no continuation/order.sync fanout on terminal divergent attention;
11. do not add baseline pointer/table/history engine/new status;
12. run fresh full QA;
13. noise-audit RED -> GREEN;
14. checkpoint and STOP.

Preferred KISS direction:

```text
EXTEND the existing terminal B equality transaction
with one scoped read/guard of the prior valid baseline
then choose attention vs valid/replacement
```

Do not alter B-vs-A mismatch handling; that already ends attention.

---

# 8. OPEN G4 GAPS AFTER K6c-1 RED

```text
baseline lifecycle:
  - equivalent valid replacement GREEN
  - divergent fingerprint preservation/attention RED confirmed, GREEN next
Sales Audit start UX + duplicate active-run guard
exact order 404 final audit classification
```

Other domains/ops remain separate:

```text
sale_fee schema/persistence alignment before Financial
webhook_events lifecycle
MariaDB session timezone certification
Billing C0 sanitized MCO smoke
Financial no-double-count
Hosting/runtime/main protection
```

---

# 9. GATES

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary + K6a guards |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for current Sales paths |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — core A/B + equivalent baseline replacement GREEN; divergent baseline RED confirmed; start/404 remain |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

---

# 10. TOOLING NOTE

Authoritative branch:

```text
impl/v3-b-sales-audit-20261010
```

Auxiliary refs `tmp` and `impl/v3-b-sales-audit-20261010-red` remain accidental/non-authoritative older refs. Do not use them for continuation.

The user has declined Work-mode handoff. Continue through the GitHub connector unless the user later explicitly chooses otherwise.

---

# 11. STOP CONDITIONS

```text
STOP now until explicit user continua
NO K6c-1 GREEN yet
NO start UX
NO exact-order 404 work
NO Billing
NO Financial
NO merge
NO deploy
NO real Mercado Libre HTTP
NO remote writes
```
