# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED — STOP AFTER THIS CHECKPOINT**  
**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Last functional GREEN:** `23be87e355b11b58070735e9997af1154a5dd6b6`  
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

Supported contract remains:

```text
PHP 8.3
PHP 8.4
PHP 8.5
composer require.php = >=8.3 <8.6
```

Do not reopen PHP compatibility without new evidence.

---

# 2. SALES AUDIT — CURRENT DURABLE TRUTH

Core flow remains:

```text
Capture A
-> durable A evidence + canonical fingerprint
-> bounded repair / local verify
-> independent Capture B
-> A/B mismatch -> attention
-> A/B equality -> baseline comparison
```

Baseline lifecycle remains GREEN:

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

Last baseline lifecycle functional commit before SAH cleanup:

```text
0c164793e12f3f289b2f16e93e1bda265b49eff2
feat(v3-k6c1): guard divergent valid baseline
```

`valid` remains relative to seller-search + the known audit contract, not an absolute guarantee of the complete Mercado Libre historical universe.

---

# 3. SALESAUDITHANDLER FORENSIC DESIGN — FROZEN

Keep the current architecture:

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

# 4. SAH-0 RED — CLOSED

RED commit:

```text
8d796ac6fc074829037c3dd5c67d93951a71d791
test(sah0): classify internal audit persistence as state failure
```

Test:

```text
Tests\Integration\SalesAuditTerminalFingerprintTest::
testFingerprintPersistenceFailureRollsBackTerminalObservationAndFailsAsAuditState
```

The RED proved, before its final failing assertion:

```text
handler returns false
Work becomes failed
terminal observation is rolled back
run remains capturing
prior canonical_count remains unchanged
prior set_hash remains unchanged
```

Then it required:

```text
last_error_code = sales_audit_state
```

RED matrix:

```text
RUN = 38080589248
HEAD = 8d796ac6fc074829037c3dd5c67d93951a71d791
REAL_MELI_HTTP = 0

PHP 8.3.35 -> JOB 114296504570
PHP 8.4.26 -> JOB 114296504683
PHP 8.5.11 -> JOB 114296504712

PHPSTAN = 0
PHPUNIT = 209 tests
ASSERTIONS = 1487
FAILURES = 1 intended
MEMORY = 22 MB
```

Exact intended mismatch:

```text
Expected: sales_audit_state
Actual:   meli_sales_audit_contract
```

Conclusion from RED:

> Transaction rollback/atomicity was already correct. The defect was only that an internal Sales Audit lifecycle/persistence failure was being blamed on Mercado Libre contract data.

---

# 5. SAH-0 GREEN — CLOSED

Functional GREEN commit:

```text
23be87e355b11b58070735e9997af1154a5dd6b6
fix(sah0): distinguish audit state from remote contract
```

Only production file changed:

```text
app/Modules/Sales/Audit/SalesAuditHandler.php
```

Noise audit versus prior checkpoint `496a391123a696a96fa4fd59e467a68b8dbd4702`:

```text
1 existing production file modified
+15 / -6
no schema/table/column
no Work type/status/queue
no audit run state
no service/repository/engine/class added
```

Minimal implementation:

```text
use existing SPL UnexpectedValueException
```

Explicit remote-contract incoherence authored by the handler now throws `UnexpectedValueException` for:

```text
remote_total drift during Capture A
remote_total drift during Capture B
duplicate remote order evidence
incomplete terminal Capture A evidence
incomplete terminal Capture B evidence
```

Final classification now separates:

```text
UnexpectedValueException
-> meli_sales_audit_contract

RuntimeException
-> sales_audit_state
```

The existing separate `normalizePage()` catch is unchanged and continues to classify malformed/unusable remote pages as:

```text
meli_sales_audit_contract
```

Binding error rule after SAH-0:

```text
REMOTE / SOURCE CONTRACT PROBLEM
-> meli_sales_audit_contract

INTERNAL AUDIT LIFECYCLE / PERSISTENCE PROBLEM
-> sales_audit_state
```

Examples of internal state failures now covered by the latter path:

```text
fingerprint persistence no longer applicable
run transition no longer applicable
baseline lifecycle persistence failure
DB/integrity failure inside terminal lifecycle
```

No message-string inspection was introduced. No custom exception hierarchy was introduced.

`recordObservation()` still returns false only for duplicate-key evidence and rethrows non-duplicate PDO failures, therefore database failures remain internal state failures.

`acceptRemoteTotal()` still returns false for durable remote-total disagreement but throws when the run state itself is unavailable, preserving the same distinction.

Transaction boundary and rollback semantics remain unchanged:

```text
WorkRepository::completeCurrentClaim()
BEGIN
-> terminal observation/lifecycle work
-> Work done
-> COMMIT
```

Any runtime failure inside that transaction rolls back business effects and the Work completion before the outer failure classification is persisted.

---

# 6. SAH-0 GREEN MATRIX — VERIFIED

Workflow:

```text
RUN = 38081132232
HEAD = 23be87e355b11b58070735e9997af1154a5dd6b6
REAL_MELI_HTTP = 0
STATUS = success
```

Jobs:

```text
PHP 8.3.35 -> JOB 114298097273 -> SUCCESS
PHP 8.4.26 -> JOB 114298097281 -> SUCCESS
PHP 8.5.11 -> JOB 114298097116 -> SUCCESS
```

Fresh logs from all three jobs confirm:

```text
PHPSTAN = 0 errors
PHPUNIT = 209 / 209 PASS
ASSERTIONS = 1487
FAILURES = 0
MEMORY = 22 MB
```

The usual benign Slim 404 trace from `BootstrapTest::testStoragePathIsNotExposedAsApplicationRoute` is still emitted during the suite but is expected and does not represent a failure.

Therefore SAH-0 RED -> GREEN is closed.

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

Global WorkRunner locking prevents current parallel processing, but it does not prevent duplicate audit starts and duplicate remote work/evidence.

Do not solve this in SAH-1.

---

# 8. CURRENT GAPS / ORDER

Sales Audit order is frozen:

```text
SAH-1 SIMPLIFY
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
G4 SALES_AUDIT_TRUTH: IN PROGRESS — core A/B + baseline lifecycle + SAH-0 classification GREEN; handler ownership/start/404 remain
G5 BILLING_CURSOR_TRUTH: BLOCKED on C0
G6 FINANCIAL_NO_DOUBLE_COUNT: NOT STARTED
G7 WRITE_FAIL_CLOSED: PASS
G8 HOSTING_REALITY: NOT CERTIFIED
```

---

# 9. EXACT NEXT MICROBLOCK — SAH-1 SIMPLIFY ONLY

**STOP NOW.**

Next microblock is **SAH-1 SIMPLIFY ONLY**.

Goal:

> Move durable Sales Audit lifecycle/baseline SQL ownership out of `SalesAuditHandler` and into the existing `SalesAuditRepository`, without changing behavior, states, schema, Work semantics, or architecture.

When work resumes:

1. verify branch HEAD equals this docs-only checkpoint commit;
2. verify functional GREEN ancestor `23be87e355b11b58070735e9997af1154a5dd6b6` and run `38081132232`;
3. inspect direct `sales_audit_runs` SQL still owned by `SalesAuditHandler`;
4. move only coherent durable lifecycle operations into existing `SalesAuditRepository`;
5. likely minimum ownership targets are the existing unavailable transition and terminal confirmation/baseline lifecycle block, but inspect before deciding exact method boundaries;
6. prefer a small number of repository methods with domain names over exposing raw SQL fragments;
7. keep `SalesAuditHandler` responsible for orchestration only;
8. preserve the same shared PDO and `WorkRepository::completeCurrentClaim()` transaction boundary;
9. preserve all current behavior and error classification from SAH-0;
10. do not add a new repository/service/state machine/finalizer/transaction abstraction;
11. do not add schema/table/column/state/Work type/status/queue;
12. do not implement the active-run guard yet;
13. do not implement Start UX or exact-order 404 classification yet;
14. run full PHP 8.3/8.4/8.5 QA;
15. require 209/209 tests GREEN and PHPStan zero unless a deliberately added characterization test changes the count;
16. perform noise audit;
17. checkpoint and STOP.

Do not merge/deploy or enable remote Mercado Libre writes without explicit user authorization.
