# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED — STOP AFTER THIS CHECKPOINT**  
**Date:** 2026-10-10  
**Repo:** `sierraglobalcompany-rgb/ErpMeli2.0`  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Last verified GREEN head:** `d9b1d7e954137353832ec4772a3805237ac5a2ec`  
**SAH-2 production change:** `46bc609829a5193b5cc5a09ecc8b3e2df570a737` — `fix(sah2): enforce one active audit run per scope`  
**QA run:** `38088781355` — SUCCESS  
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

No merge, deploy, destructive cleanup, or remote Mercado Libre writes without explicit user authorization.

---

# 2. PLATFORM / ARCHITECTURE — FROZEN

Supported PHP:

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
generic lock manager
history/page/repair/confirm tables
new audit states
```

`WorkRepository::completeCurrentClaim()` remains the atomic Work/business completion boundary, and Work/SalesAudit repositories keep sharing the same PDO.

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
MCO timezone = America/Bogota
historical horizon ~= 12 months
outside horizon -> unavailable before OAuth/HTTP
short non-terminal page -> fail closed
```

Baseline lifecycle remains:

```text
no prior valid baseline
-> current confirmed run becomes valid

prior equivalent valid baseline
-> current valid
-> old equivalent valid deleted with evidence cascade

prior divergent valid baseline
-> prior valid preserved
-> current attention preserved
```

SAH-0 remains closed:

```text
remote/source contract incoherence -> meli_sales_audit_contract
internal audit lifecycle/persistence failure -> sales_audit_state
```

SAH-1 remains closed: durable Sales Audit lifecycle SQL belongs to `SalesAuditRepository`; `SalesAuditHandler` orchestrates.

---

# 4. SAH-2 ACTIVE-RUN GUARD — GREEN / CLOSED

Problem proven:

> Before SAH-2, `SalesAuditRepository::createCapturingRun()` could create a second active audit run for the same company/account/period/contract.

Active identity:

```text
company_id
account_id
period_key
contract_version
```

Active statuses:

```text
capturing
repairing
confirming
```

Terminal statuses:

```text
valid
attention
unavailable
```

## RED evidence

Initial RED commit:

```text
882f291db0e0e31a45bcb7b056b004e51191c5c2
test(sah2): prove duplicate active audit run gap
```

RED CI proved the intended failure only:

```text
PHPStan = 0 errors
PHPUnit = 210 tests / 1489 assertions
Failures = 1
failure = second active run was created for the same scope
```

The RED did not fail from syntax, setup, migration, or unrelated behavior.

## GREEN mechanism

Reuse the already-proven Work active-dedupe pattern at the database boundary.

`database/migrations/004_sales.sql` now adds only:

```text
active_contract_version VARCHAR(32)
  GENERATED/PERSISTENT as:
    contract_version when status is capturing/repairing/confirming
    NULL otherwise

UNIQUE uq_sales_audit_runs_active
  (company_id, account_id, period_key, active_contract_version)
```

Consequences:

```text
capturing  -> blocks same active scope
repairing  -> blocks same active scope
confirming -> blocks same active scope
valid      -> allows future replacement run
attention  -> allows future replacement run
unavailable-> allows future replacement run
```

The guard is enforced atomically by MariaDB. There is no SELECT-then-INSERT race.

No new table, service, engine, lock manager, state, Work type, route, controller, config, package, cron, or retry path was added.

## Important simplification

`SalesAuditRepository` was intentionally NOT changed.

The database duplicate violation currently remains the native PDO/MariaDB integrity error (`SQLSTATE 23000`, driver code `1062`). Translating that into a user-facing/domain HTTP response is deferred to the real Start Audit boundary, where there will be an actual consumer. Do not add exception translation earlier just for abstraction.

ERP2 is still pre-release, so `004_sales.sql` was edited in place; no synthetic `006/007` migration was created.

---

# 5. SAH-2 TEST CONTRACT — GREEN

Dedicated coverage:

```text
tests/Integration/SalesAuditActiveRunGuardTest.php
```

Verifies:

```text
capturing blocks duplicate
repairing blocks duplicate
confirming blocks duplicate
valid permits replacement
attention permits replacement
unavailable permits replacement
uq_sales_audit_runs_active exists and is UNIQUE
index columns are exactly:
company_id, account_id, period_key, active_contract_version
```

`SalesSchemaTest` also verifies the generated column is part of the canonical pre-release Sales schema.

---

# 6. QA / CI — GREEN

Verified head:

```text
d9b1d7e954137353832ec4772a3805237ac5a2ec
```

Workflow:

```text
RUN = 38088781355
STATUS = success
REAL_MELI_HTTP = 0
```

Jobs:

```text
PHP 8.3 -> JOB 114320745692 -> SUCCESS
PHP 8.4 -> JOB 114320745663 -> SUCCESS
PHP 8.5 -> JOB 114320745486 -> SUCCESS
```

Fresh QA evidence:

```text
lint = PASS
PHPStan = 0 errors
PHPUnit = 212 / 212 tests
Assertions = 1502
```

No real Mercado Libre HTTP and no remote writes were enabled.

---

# 7. NOISE AUDIT — CLEAN

Final diff from prior checkpoint `a1d3656d126fe7de3da263a29471ef92544cffd5` to GREEN `d9b1d7e954137353832ec4772a3805237ac5a2ec`:

```text
1 production file modified
  database/migrations/004_sales.sql
  +3 / -0

1 dedicated test file added
  tests/Integration/SalesAuditActiveRunGuardTest.php

1 existing schema test updated
  tests/Integration/SalesSchemaTest.php
  +2 / -0
```

No final diff remains in `SalesAuditFoundationTest.php`; the initial RED characterization was deliberately consolidated into the dedicated SAH-2 test.

No unrelated production code changed.

---

# 8. GATES

```text
G1 REMOTE_TRUTH: PASS for implemented boundary
G2 WORK_SAFETY: PASS
G3 RATE_SAFETY: PASS for current Sales
G4 SALES_AUDIT_TRUTH: IN PROGRESS
   Capture A/repair/verify/Capture B/A-B/baseline/SAH-0/SAH-1/SAH-2 GREEN
   Start Audit + current/future semantics + exact-order 404 + final closure pending
G5 BILLING_CURSOR_TRUTH: BLOCKED ON C0
G6 FINANCIAL_NO_DOUBLE_COUNT: NOT STARTED
G7 WRITE_FAIL_CLOSED: PASS
G8 HOSTING_REALITY: NOT CERTIFIED
```

External gates remain:

```text
Issue #3 Hostinger/runtime/main protection
Issue #5 dedicated Mercado Libre ERP2 app/OAuth reality
```

Historical draft PRs/issues remain tracking debt, not roadmap authority.

`docs/ERP2_AUTHORITY.md` still contains an older immediate-order section that predates baseline/SAH-0/SAH-1/SAH-2. Do not follow that stale subsection over this checkpoint/code. Full doc cleanup remains deferred until G4 closure.

---

# 9. CURRENT GAPS / FROZEN ORDER

Immediate order is now:

```text
NOW CLOSED: SAH-2 duplicate active-run guard
NEXT: Start Audit contract + UX/API
THEN: current/future period semantics
THEN: exact-order 404 semantics/classification
THEN: G4 adversarial/noise/docs closure
```

After G4:

```text
small DOC-CLEAN / issue hygiene
Billing C0 real sanitized
Billing Task2
sale_fee alignment before Financial
Financial no-double-count
```

Separate hardening, do not mix into next block:

```text
Sales detail multi-account scope
webhook seller multi-company scope
webhook explicit timestamp timezone
webhook_events retention
MariaDB session UTC
```

---

# 10. EXACT NEXT MICROBLOCK — START AUDIT CONTRACT + UX/API

**STOP NOW.**

Next microblock is:

```text
Start Audit contract + UX/API
```

Goal:

> Expose the smallest safe authenticated start boundary for a Sales Audit using the existing `SalesAuditRepository`, `WorkRepository`, CSRF/auth/tenancy patterns and the existing `sales.audit` Work type, without creating a new orchestration layer.

When work resumes:

1. verify repo/branch/head and this checkpoint;
2. verify GREEN ancestor `d9b1d7e954137353832ec4772a3805237ac5a2ec` and QA `38088781355`;
3. inspect current Sales UI, auth/CSRF/admin patterns, Work enqueue contract, and every current call site of `createCapturingRun()`;
4. define the smallest real user-facing start contract before adding a route;
5. write the minimal RED for that boundary;
6. confirm failure is only the missing Start Audit behavior;
7. DELETE -> SIMPLIFY -> REUSE -> MERGE -> EXTEND -> ADD;
8. reuse `SalesAuditRepository::createCapturingRun()` and existing `WorkRepository`; do not add StartService/engine/queue/Work type/state machine;
9. preserve the SAH-2 MariaDB UNIQUE as the race-safe last line of defense;
10. decide duplicate-start HTTP/user behavior at this actual consumer boundary, not in speculative infrastructure;
11. do not mix current/future-period policy unless the RED proves it is inseparable from the start contract;
12. do not mix exact-order 404, Billing, Financial, webhook hardening, DB UTC, docs cleanup, or Git hygiene;
13. remote writes OFF; REAL_MELI_HTTP=0;
14. full PHP 8.3/8.4/8.5 QA;
15. noise audit;
16. checkpoint;
17. STOP.

Do not merge/deploy or enable remote Mercado Libre writes without explicit user authorization.
