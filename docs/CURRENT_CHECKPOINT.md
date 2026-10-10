# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED — STOP AFTER THIS CHECKPOINT**  
**Date:** 2026-10-10  
**Repo:** `sierraglobalcompany-rgb/ErpMeli2.0`  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Last functional GREEN:** `1ad13133569380ac79a08f9307a9e7e3f4a0a940`  
**Functional message:** `refactor(sah1): move durable audit lifecycle to repository`  
**QA run:** `38085304978` — SUCCESS  
**Remote writes:** OFF  
**REAL_MELI_HTTP:** `0`

> This is the live continuity checkpoint. Git/code/schema/tests at the active branch remain higher authority than prose.

---

# 1. EXECUTION LAW

```text
1 microblock at a time
RED -> confirm intended failure -> minimal GREEN -> full QA -> noise audit -> checkpoint -> STOP
DELETE -> SIMPLIFY -> REUSE -> MERGE -> EXTEND -> ADD
Correct -> Simple -> Stable -> Maintainable -> Efficient -> Scalable
```

Authority order:

```text
1. code/schema at active branch HEAD
2. tests/CI at the relevant SHA
3. this checkpoint
4. AGENTS.md
5. docs/ERP2_AUTHORITY.md
6. recent explicit user decisions
7. historical plans/checkpoints/handoffs/issues/PRs
```

For external contracts:

```text
official current Mercado Libre documentation
+ controlled real API evidence
> internal documentation
> assumptions
```

No merge, deploy, destructive cleanup, or remote Mercado Libre writes without explicit user authorization.

---

# 2. PLATFORM / ARCHITECTURE — FROZEN

Supported PHP contract remains:

```text
PHP 8.3
PHP 8.4
PHP 8.5
composer require.php = >=8.3 <8.6
```

Architecture remains deliberately small:

```text
1 PHP/Slim application
1 MariaDB
1 Work table
1 WorkRunner
1 MeliClient
1 sales.audit Work type
1 SalesAuditHandler
1 SalesAuditRepository
1 SalesAuditRepairHandler
```

Do not add by inertia:

```text
second queue
queue per domain
domain scheduler
RetryEngine
RepairEngine
RecoveryEngine
SalesAuditStateMachine
ConfirmRepository
BaselineService
FinalizerEngine
generic transaction layer
history/page/repair/confirm tables
new audit states
```

`WorkRepository::completeCurrentClaim()` remains the atomic boundary:

```text
BEGIN
-> lock current running Work claim
-> persist business effects
-> mark Work done
-> COMMIT
```

`WorkRepository` and `SalesAuditRepository` continue sharing the same PDO. Preserve that property.

---

# 3. SALES AUDIT CORE — GREEN

Durable flow remains:

```text
Capture A
-> durable A evidence
-> canonical fingerprint A
-> compare with local
-> bounded one-child repair only for missing orders
-> verify local
-> Capture B independently
-> compare A vs B
-> baseline lifecycle
```

Source contract remains:

```text
source = seller Orders Search
monthly membership = order.date_created
MCO business timezone = America/Bogota
month = [first local day 00:00, next local month 00:00)
sort is not membership truth
source is mutable; A and B are independent captures in the same audit run
historical horizon ~= 12 months
outside horizon -> unavailable before OAuth/HTTP
short non-terminal page -> fail closed
```

`valid` remains relative to the known seller-search contract, not an absolute guarantee of Mercado Libre's entire historical universe.

Baseline lifecycle remains:

```text
no prior valid baseline
-> current internally confirmed run becomes valid

prior equivalent valid baseline
-> current becomes valid
-> old equivalent valid run is deleted
-> old evidence pruned by FK cascade

prior divergent valid baseline
-> prior baseline remains valid
-> current becomes attention
-> current evidence preserved
```

SAH-0 remains closed:

```text
remote/source contract incoherence -> meli_sales_audit_contract
internal audit lifecycle/persistence failure -> sales_audit_state
```

Do not collapse those categories and do not classify by exception-message text.

---

# 4. SAH-1 SIMPLIFY — GREEN / CLOSED

Functional commit:

```text
1ad13133569380ac79a08f9307a9e7e3f4a0a940
refactor(sah1): move durable audit lifecycle to repository
```

Goal achieved:

> `SalesAuditHandler` now orchestrates; `SalesAuditRepository` owns the durable Sales Audit state transitions that SAH-1 targeted.

Moved to the EXISTING `SalesAuditRepository`:

```text
markUnavailable(...)
-> owns the durable source-horizon transition to status=unavailable

finalizeConfirmation(...)
-> computes Capture B canonical fingerprint
-> A/B mismatch -> attention
-> divergent prior valid baseline -> attention
-> confirming -> valid + completed_at
-> deletes superseded equivalent valid baseline
```

`SalesAuditHandler` now calls those domain operations instead of preparing the corresponding durable SQL itself.

The handler still owns only the intended orchestration concerns:

```text
payload/context
source-horizon decision
OAuth
orders.search
normalization
retry/defer/failure orchestration
page traversal
Work continuation/enqueue orchestration
```

No new repository/service/class/interface/DTO/enum/table/column/state/Work type/config/env/cron/package/transaction layer was introduced.

---

# 5. SAH-1 BEHAVIOR PRESERVATION — VERIFIED

SAH-1 was a pure ownership refactor. The following were intentionally unchanged:

```text
same Capture A evidence
same Capture B evidence
same fingerprint algorithm
same status set
same A/B mismatch semantics
same baseline comparison/pruning
same completed_at semantics
same FK cascade pruning
same Work completion semantics
same completeCurrentClaim transaction boundary
same shared PDO
same retry/defer paths
same OAuth paths
same HTTP behavior/count contract
same SAH-0 error classification
same source-horizon behavior
remote writes OFF
REAL_MELI_HTTP=0
```

The source-horizon persistence failure keeps its existing separate classification:

```text
sales_audit_source_unavailable
```

SAH-1 did NOT change that semantic contract.

---

# 6. QA / CI — GREEN

Workflow:

```text
RUN = 38085304978
HEAD = 1ad13133569380ac79a08f9307a9e7e3f4a0a940
STATUS = success
REAL_MELI_HTTP = 0
```

Jobs:

```text
PHP 8.3 -> JOB 114310462178 -> SUCCESS
PHP 8.4 -> JOB 114310462015 -> SUCCESS
PHP 8.5 -> JOB 114310462207 -> SUCCESS
```

Each job completed the repository `composer qa` step successfully.

`composer qa` still means:

```text
lint
-> PHPStan analyse
-> PHPUnit
```

No test files changed in SAH-1, so the test suite remains the same suite as the previous 209-test / 1487-assertion GREEN baseline.

---

# 7. NOISE AUDIT — CLEAN

Diff from the prior docs-only checkpoint `f177b3d9fa2acfc12016898381d4653f473a8387` to functional GREEN `1ad13133569380ac79a08f9307a9e7e3f4a0a940`:

```text
2 existing production files modified

SalesAuditHandler.php
+4 / -103

SalesAuditRepository.php
+114 / -0
```

Net effect:

```text
one durable ownership location
less SQL/lifecycle detail in the handler
no parallel implementation
no dead compatibility path
no schema/config/test/doc churn in the functional commit
```

This satisfies the SAH-1 KISS objective: fewer places to understand or modify durable Sales Audit truth.

---

# 8. DUPLICATE ACTIVE AUDIT RUN GAP — NEXT

The schema/start path can still permit more than one active audit run for the same:

```text
company_id
account_id
period_key
contract_version
```

Active statuses remain:

```text
capturing
repairing
confirming
```

The WorkRunner global lock prevents concurrent runner execution but does NOT prevent duplicate audit starts, duplicate HTTP work, duplicate evidence, or baseline competition by start order.

This is **SAH-2**.

Guardrail:

> Solve duplicate creation at the audit start/create boundary. Do not build another orchestration system.

Do not decide the final mechanism before the RED proves the exact current failure.

---

# 9. CURRENT GAPS / ORDER

Frozen immediate order:

```text
NOW: SAH-2 duplicate active-run guard RED/GREEN
THEN: Start Audit contract + UX/API
THEN: current/future period semantics
THEN: exact-order 404 semantics/classification
THEN: G4 adversarial/noise/docs closure
```

After G4:

```text
small DOC-CLEAN / issue hygiene when appropriate
Billing C0 real sanitized
Billing Task2
sale_fee alignment before Financial
Financial no-double-count
```

Separate hardening, do not mix into SAH-2:

```text
Sales detail multi-account scope
webhook seller multi-company scope
webhook explicit timestamp timezone
webhook_events retention
MariaDB session UTC
```

External gates remain real but do not block safe local microblocks:

```text
Issue #3 Hostinger/runtime/main protection
Issue #5 dedicated Mercado Libre ERP2 app/OAuth reality
```

Open historical issues/PRs are not automatically roadmap authority.

---

# 10. GATES

```text
G1 REMOTE_TRUTH: PASS for implemented boundary
G2 WORK_SAFETY: PASS
G3 RATE_SAFETY: PASS for current Sales
G4 SALES_AUDIT_TRUTH: IN PROGRESS
   core Capture A/repair/verify/Capture B/A-B/baseline/SAH-0/SAH-1 GREEN
   SAH-2 + Start contract + current/future semantics + exact-order 404 + final closure pending
G5 BILLING_CURSOR_TRUTH: BLOCKED ON C0
G6 FINANCIAL_NO_DOUBLE_COUNT: NOT STARTED
G7 WRITE_FAIL_CLOSED: PASS
G8 HOSTING_REALITY: NOT CERTIFIED
```

CI does not certify Mercado Libre real API, Hostinger, Billing C0, or production readiness.

---

# 11. EXACT NEXT MICROBLOCK — SAH-2 ACTIVE-RUN GUARD

**STOP NOW.**

Next microblock is:

```text
SAH-2 — duplicate active Sales Audit run guard
```

Goal:

> Prevent a second active audit run for the same company/account/period/contract from being created or started while an existing run is `capturing`, `repairing`, or `confirming`, using the smallest correct mechanism at the start/create boundary.

When work resumes:

1. verify repo and branch;
2. verify branch HEAD and inspect only unexplained delta;
3. verify functional GREEN ancestor `1ad13133569380ac79a08f9307a9e7e3f4a0a940` and QA run `38085304978`;
4. inspect every real call site of `SalesAuditRepository::createCapturingRun()` and the current start boundary;
5. write one minimal RED proving that duplicate active creation is currently possible for the same company/account/period/contract;
6. confirm the RED fails only for the intended missing guard;
7. apply DELETE -> SIMPLIFY -> REUSE -> MERGE -> EXTEND -> ADD;
8. implement the minimum guard in the existing start/create path;
9. do not add a service, engine, queue, Work type, audit status, generic lock manager, or state machine;
10. do not implement Start UX/API yet unless required only to expose the existing start boundary for the test;
11. do not mix current/future month semantics, exact-order 404, Billing, Financial, webhook, account-scope, DB UTC, docs cleanup, or Git hygiene;
12. preserve remote writes OFF and REAL_MELI_HTTP=0;
13. run full PHP 8.3/8.4/8.5 QA;
14. noise audit;
15. checkpoint;
16. STOP.

Do not merge/deploy or enable remote Mercado Libre writes without explicit user authorization.
