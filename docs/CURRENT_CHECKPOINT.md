# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED — STOP AFTER THIS CHECKPOINT**  
**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**RED test HEAD before this docs-only checkpoint:** `8d796ac6fc074829037c3dd5c67d93951a71d791`  
**Remote writes:** OFF  
**REAL_MELI_HTTP:** `0`

## Execution law

```text
1 microblock at a time
RED -> confirm intended failure -> minimal GREEN -> full QA -> noise audit -> checkpoint
DELETE -> SIMPLIFY -> REUSE -> MERGE -> EXTEND -> ADD
```

Authority order:

```text
1. code/schema at branch HEAD
2. tests/CI at relevant SHA
3. this checkpoint
4. docs/ERP2_AUTHORITY.md
5. recent explicit user decisions
6. README.md
7. historical handoffs/plans
```

---

# 1. PLATFORM BASELINE — GREEN

ERP MELI 2.0 remains certified on:

```text
PHP 8.3
PHP 8.4
PHP 8.5
composer require.php = >=8.3 <8.6
```

Last pre-SAH all-GREEN matrix:

```text
functional SHA = 1931746b264866f5d45d8017ca79af8dc606d0a1
RUN = 38079245005
PHPUNIT = 209/209 PASS
ASSERTIONS = 1483
PHPSTAN = 0
REAL_MELI_HTTP = 0
```

Do not reopen PHP compatibility without new evidence.

---

# 2. SALES AUDIT — CURRENT DURABLE TRUTH

Last functional Sales Audit GREEN remains:

```text
0c164793e12f3f289b2f16e93e1bda265b49eff2
feat(v3-k6c1): guard divergent valid baseline
```

Core flow:

```text
Capture A
-> durable A evidence + canonical fingerprint
-> bounded repair / local verify
-> independent Capture B
-> A/B mismatch -> attention
-> A/B equality -> baseline comparison
```

Baseline lifecycle GREEN:

```text
no prior valid baseline
-> current internally confirmed run becomes valid

prior equivalent valid baseline
-> current run becomes valid
-> prior equivalent valid run removed
-> superseded A+B evidence pruned by FK cascade

prior divergent valid baseline
-> prior valid baseline preserved
-> current run becomes attention
-> current evidence preserved for diagnosis
```

`valid` remains relative to seller-search + known run contract, not an absolute guarantee of the complete Mercado Libre historical universe.

---

# 3. SALESAUDITHANDLER FORENSIC AUDIT — DESIGN FROZEN

Do not replace or explode the Sales Audit design.

Keep:

```text
1 Work type: sales.audit
1 Work engine
1 SalesAuditHandler for remote Capture A/B orchestration
1 SalesAuditRepository for durable audit truth
1 SalesAuditRepairHandler for bounded repair orchestration
same sales_audit_orders table with capture_pass A/B
```

Do not add:

```text
ConfirmRepository
BaselineService
SalesAuditStateMachine
FinalizerEngine
extra queue
extra Work type/status
extra audit run state
history table
baseline pointer
transaction abstraction
```

Target ownership:

```text
SalesAuditHandler
= payload/context + OAuth + orders.search + normalize + retry/defer + orchestration

SalesAuditRepository
= sales_audit_runs / sales_audit_orders durable truth
  fingerprints
  state transitions
  baseline compare/prune

SalesAuditRepairHandler
= bounded one-child repair orchestration

WorkRepository
= Work claim/transaction/completion/retry/defer/fail
```

`WorkRepository::completeCurrentClaim()` remains the atomic boundary:

```text
BEGIN
-> lock current running Work claim
-> persist business effects
-> mark Work done
-> COMMIT
```

WorkRepository and SalesAuditRepository share the same PDO in production. Preserve that property.

---

# 4. DEFECT PROVEN — INTERNAL FAILURE MISCLASSIFIED AS MELI CONTRACT

Current `SalesAuditHandler` final persistence catch is too broad.

Today it maps both categories to:

```text
meli_sales_audit_contract
```

But they are not equivalent.

Remote/source contract examples:

```text
remote_total drift
duplicate remote order evidence
incomplete terminal remote evidence
unusable/malformed remote page
```

These may remain:

```text
meli_sales_audit_contract
```

Internal lifecycle/persistence examples:

```text
fingerprint persistence no longer applicable
run transition no longer applicable
baseline lifecycle persistence failure
DB/integrity failure inside terminal lifecycle
```

These must not blame Mercado Libre. Reuse existing:

```text
sales_audit_state
```

No new exception hierarchy or error taxonomy is justified yet.

---

# 5. SAH-0 RED — CLOSED / INTENDED FAILURE CONFIRMED

RED test commit:

```text
8d796ac6fc074829037c3dd5c67d93951a71d791
test(sah0): classify internal audit persistence as state failure
```

Only file changed:

```text
tests/Integration/SalesAuditTerminalFingerprintTest.php
```

Noise from prior checkpoint `83cbf8a9f6a5748a6ff94af2184c2617abae4bed`:

```text
1 existing test file modified
+16 / -3
no production code
no schema/table/column
no Work type/status/queue
no audit state
no engine/service/repository added
```

Strengthened case:

```text
testFingerprintPersistenceFailureRollsBackTerminalObservationAndFailsAsAuditState
```

The test now proves, in this order:

```text
handler returns false
Work becomes failed
terminal observation is rolled back
run remains capturing
prior canonical_count remains unchanged
prior set_hash remains unchanged
THEN last_error_code must equal sales_audit_state
```

Therefore the RED isolates error classification while confirming existing transaction rollback remains correct.

---

# 6. SAH-0 RED MATRIX — EXACT RESULT

Workflow:

```text
RUN = 38080589248
HEAD = 8d796ac6fc074829037c3dd5c67d93951a71d791
REAL_MELI_HTTP = 0
```

Jobs:

```text
PHP 8.3.35 -> JOB 114296504570
PHP 8.4.26 -> JOB 114296504683
PHP 8.5.11 -> JOB 114296504712
```

All three produced the same intended result:

```text
PHPSTAN = 0
PHPUNIT = 209 tests
ASSERTIONS = 1487
FAILURES = 1 intended
MEMORY = 22 MB
```

Only failure:

```text
Tests\Integration\SalesAuditTerminalFingerprintTest::
testFingerprintPersistenceFailureRollsBackTerminalObservationAndFailsAsAuditState
```

Exact mismatch:

```text
Expected: sales_audit_state
Actual:   meli_sales_audit_contract
```

Location:

```text
tests/Integration/SalesAuditTerminalFingerprintTest.php:264
```

The usual benign Slim 404 trace from `BootstrapTest::testStoragePathIsNotExposedAsApplicationRoute` still appears but is not a second failure.

RED cause confirmed:

> rollback/atomicity are already correct; only the broad final catch misclassifies an internal Sales Audit lifecycle/persistence failure as a Mercado Libre contract failure.

---

# 7. DUPLICATE ACTIVE RUN GAP — FROZEN FOR SAH-2, NOT NOW

Schema still permits multiple active runs for the same:

```text
company_id
account_id
period_key
contract_version
```

Active states:

```text
capturing
repairing
confirming
```

Global WorkRunner named lock prevents current parallel processing, but does not prevent duplicate audit starts and duplicated remote work/evidence.

Do not solve this in SAH-0 or SAH-1.

---

# 8. CURRENT GAPS / ORDER

Sales Audit order is frozen:

```text
SAH-0 GREEN
-> SAH-1 SIMPLIFY: move durable lifecycle/baseline SQL ownership into SalesAuditRepository
-> SAH-2 active-run guard RED/GREEN
-> Start UX
-> exact order 404 final audit classification
-> final G4 adversarial/noise/docs closure as needed
```

Other separate future gaps:

```text
sale_fee schema/persistence alignment before Financial
webhook_events lifecycle / timestamp timezone hardening
MariaDB session timezone certification
Billing C0 sanitized MCO smoke
Financial no-double-count
Hosting/runtime/main protection
```

Do not mix these into current Sales Audit cleanup.

Gates:

```text
G1 REMOTE_TRUTH: PASS for implemented boundary + current guards
G2 WORK_SAFETY: PASS
G3 RATE_SAFETY: PASS current Sales
G4 SALES_AUDIT_TRUTH: IN PROGRESS — core A/B + baseline lifecycle GREEN; handler cleanup/start/404 remain
G5 BILLING_CURSOR_TRUTH: BLOCKED on C0
G6 FINANCIAL_NO_DOUBLE_COUNT: NOT STARTED
G7 WRITE_FAIL_CLOSED: PASS
G8 HOSTING_REALITY: NOT CERTIFIED
```

---

# 9. EXACT RESUME POINT

**STOP NOW.**

Next microblock is **SAH-0 GREEN ONLY**.

When work resumes:

1. verify branch HEAD equals the docs-only checkpoint commit created from this file;
2. confirm RED ancestor `8d796ac6fc074829037c3dd5c67d93951a71d791` and run `38080589248`;
3. inspect only the final persistence path in `SalesAuditHandler`;
4. make the smallest change that distinguishes internal lifecycle/persistence failure from remote-contract incoherence;
5. internal lifecycle/persistence failure must result in `sales_audit_state`;
6. remote-contract failures must retain `meli_sales_audit_contract`;
7. preserve current transaction rollback and Work atomicity;
8. do not perform SAH-1 repository refactor yet;
9. do not implement active-run guard yet;
10. do not add exceptions/services/engines unless the RED proves they are unavoidable;
11. run the full PHP 8.3/8.4/8.5 matrix;
12. require all 209 tests GREEN and PHPStan zero;
13. noise audit;
14. checkpoint and STOP.

Do not merge/deploy or enable remote Mercado Libre writes without explicit user authorization.
