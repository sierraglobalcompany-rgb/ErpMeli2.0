# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED — STOP AFTER THIS CHECKPOINT**  
**Date:** 2026-10-10  
**Repo:** `sierraglobalcompany-rgb/ErpMeli2.0`  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Last verified GREEN head:** `b88b8654f0cd16e6ba612084d1067cabc6948ee4`  
**Start Audit functional boundary:** `1b3df5b802359b358592154dddfc65424e6af96a` + `1906c89c386c708ae5ab598840be0ec03949ae89` + `2444909ceb376214f0a00b784de492550203c080`  
**QA run:** `38089427123` — SUCCESS  
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
StartAuditService
generic transaction layer
generic lock manager
history/page/repair/confirm tables
new audit states
```

`WorkRepository::completeCurrentClaim()` remains the atomic Work/business completion boundary. Work and Sales Audit repositories continue sharing the same PDO.

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

Closed hardening already preserved:

```text
SAH-0:
remote/source contract incoherence -> meli_sales_audit_contract
internal audit lifecycle/persistence failure -> sales_audit_state

SAH-1:
durable lifecycle SQL belongs to SalesAuditRepository
SalesAuditHandler remains remote orchestration

SAH-2:
one active run per company/account/period/contract enforced atomically by MariaDB
```

---

# 4. SAH-2 ACTIVE-RUN GUARD — GREEN / CLOSED

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

Canonical schema mechanism in `database/migrations/004_sales.sql`:

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
valid      -> allows replacement
attention  -> allows replacement
unavailable-> allows replacement
```

The guard is concurrency-safe at the database boundary. No SELECT-then-INSERT race, lock manager, service, new table, new state, or new Work type was introduced.

SAH-2 production change:

```text
46bc609829a5193b5cc5a09ecc8b3e2df570a737
fix(sah2): enforce one active audit run per scope
```

SAH-2 verified QA:

```text
RUN 38088781355
PHP 8.3 / 8.4 / 8.5 = SUCCESS
212 tests / 1502 assertions
PHPStan = 0
```

---

# 5. START AUDIT CONTRACT + UX/API — GREEN / CLOSED

This block exposed the first real user-facing start boundary without adding an orchestration layer.

## Contract

Endpoint:

```text
POST /sales/audits
```

Required boundary:

```text
authenticated user
selected company in session
role = admin for selected company
valid CSRF
connected Mercado Libre account belonging to selected company
period_key shaped as YYYY-MM-01
existing SalesAuditWindow validation remains authoritative for a real MCO month/site
```

Important: **current/future-period policy is NOT decided in this block.** A syntactically and calendrically valid current/future month is not newly rejected here. That is the next isolated microblock.

## Atomic start

The HTTP boundary reuses only existing pieces:

```text
SalesListController
SalesAuditRepository::createCapturingRun()
WorkRepository::enqueue()
Csrf
existing session tenancy/admin membership
existing sales.audit Work type
same PDO
```

The controller opens one PDO transaction and performs:

```text
1. create sales_audit_runs row in capturing
2. enqueue initial sales.audit Work
3. COMMIT
```

Initial Work contract:

```text
scope_key    = company:<company_id>:account:<account_id>
type         = sales.audit
resource_key = <run_id>
logical id   = sales.audit:<run_id>:0:50
payload      = {run_id:<run_id>, offset:0, limit:50}
status       = pending
```

If either durable run creation or enqueue fails, the transaction rolls back. This avoids an orphan active run with no Work item.

No Mercado Libre HTTP occurs while starting the audit. Start only persists local state and queues existing Work.

## HTTP behavior

```text
success                         -> 303 Location: /sales
duplicate active same scope     -> 409
not admin / no valid membership -> 403
invalid CSRF                    -> 419
invalid input/account scope     -> 422
```

The SAH-2 MariaDB UNIQUE remains the race-safe final defense. At this real consumer boundary, native MariaDB duplicate `SQLSTATE 23000 / 1062` is translated to HTTP `409` with a safe Spanish message.

## UX

`GET /sales` remains available to valid company members.

For company admins only it now exposes a small `Auditoría histórica` form containing:

```text
connected Mercado Libre account selector
month input in canonical YYYY-MM-01 form
CSRF token
Iniciar auditoría button
```

Ordinary members do not receive the audit-start form.

No separate dashboard, wizard, scheduler, StartAuditService, JavaScript workflow, or new route family was added.

---

# 6. START AUDIT TDD EVIDENCE

## RED

Commit:

```text
672d9ff8143acd8a7e772911e323d6ee7df808be
test(sah3): define Sales Audit start HTTP contract
```

RED run:

```text
RUN 38089110850
PHPStan = 0 errors
PHPUnit = 213 tests / 1503 assertions
Failures = exactly 1
expected = 303
actual = 404 on POST /sales/audits
```

The RED failed only because the Start Audit route/boundary did not exist.

## GREEN production

```text
1b3df5b802359b358592154dddfc65424e6af96a
feat(sah3): add atomic Sales Audit start boundary

1906c89c386c708ae5ab598840be0ec03949ae89
feat(sah3): expose Sales Audit start form

2444909ceb376214f0a00b784de492550203c080
feat(sah3): route Sales Audit starts through Sales UI

117ea64b6a1ca0ecb83a0d8ea5aa996010f4415d
chore(sah3): restore Routes trailing newline
```

Dedicated final coverage:

```text
tests/Integration/SalesAuditHttpStartRouteTest.php
```

It verifies:

```text
admin can start tenant-bound audit
run + initial Work contract
duplicate active start -> 409 and no extra run/work
member start -> 403 and no state
invalid CSRF -> 419 and no state
foreign-company account -> 422 and no state
admin sees Start Audit form
member does not see Start Audit form
```

---

# 7. QA / CI — GREEN

Verified GREEN head:

```text
b88b8654f0cd16e6ba612084d1067cabc6948ee4
```

Workflow:

```text
RUN = 38089427123
STATUS = success
REAL_MELI_HTTP = 0
```

Jobs:

```text
PHP 8.3 -> JOB 114322625074 -> SUCCESS
PHP 8.4 -> JOB 114322625117 -> SUCCESS
PHP 8.5 -> JOB 114322624920 -> SUCCESS
```

Fresh QA evidence from PHP 8.4 job:

```text
lint = PASS
PHPStan = 0 errors
PHPUnit = 218 / 218 tests
Assertions = 1538
```

The existing intentional Slim 404 diagnostic emitted by `BootstrapTest::testStoragePathIsNotExposedAsApplicationRoute()` is still visible in CI output but the test passes. It is pre-existing noise, not a SAH-3 failure, and was not mixed into this block.

No real Mercado Libre HTTP and no remote writes were enabled.

---

# 8. NOISE AUDIT — CLEAN

Final diff from prior checkpoint `1fcace89c6c807577cc9cbee736cf7b6eec9015d` to GREEN `b88b8654f0cd16e6ba612084d1067cabc6948ee4`:

```text
app/Core/Http/Routes.php
  +7 / -0
  only POST /sales/audits route

app/Modules/Sales/ViewSales/SalesListController.php
  Start Audit boundary + admin-role reuse + connected-account data for form

app/Modules/Sales/ViewSales/views/list.php
  minimal admin-only audit form

tests/Integration/SalesAuditHttpStartRouteTest.php
  dedicated Start Audit contract coverage
```

The temporary missing trailing newline in `Routes.php` was detected and corrected before final GREEN. Final route diff is exactly `+7 / -0`.

No changes to:

```text
database schema
SalesAuditRepository
SalesAuditHandler
SalesAuditRepairHandler
WorkRepository
WorkRunner
MeliClient
Mercado Libre operations/config
Billing
Financial
webhook behavior
```

---

# 9. GATES

```text
G1 REMOTE_TRUTH: PASS for implemented boundary
G2 WORK_SAFETY: PASS
G3 RATE_SAFETY: PASS for current Sales
G4 SALES_AUDIT_TRUTH: IN PROGRESS
   Capture A/repair/verify/Capture B/A-B/baseline/SAH-0/SAH-1/SAH-2/Start Audit GREEN
   current/future semantics + exact-order 404 + final closure pending
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

`docs/ERP2_AUTHORITY.md` still contains an older immediate-order subsection that predates newer Sales Audit work. Do not follow that stale subsection over code/tests/this checkpoint. Full doc cleanup remains deferred until G4 closure.

---

# 10. CURRENT GAPS / FROZEN ORDER

Immediate order is now:

```text
CLOSED: SAH-2 duplicate active-run guard
CLOSED: Start Audit contract + UX/API
NEXT: current/future period semantics
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

# 11. EXACT NEXT MICROBLOCK — CURRENT/FUTURE PERIOD SEMANTICS

**STOP NOW.**

Next microblock is:

```text
current/future period semantics
```

Goal:

> Define and enforce the smallest deterministic policy for starting Sales Audit periods that are current or future, without altering historical-source truth, duplicate-run behavior, or exact-order repair semantics.

When work resumes:

1. verify repo/branch/head and this checkpoint;
2. verify GREEN ancestor `b88b8654f0cd16e6ba612084d1067cabc6948ee4` and QA `38089427123`;
3. inspect `SalesAuditWindow`, the new `POST /sales/audits` boundary, source historical horizon semantics, and existing tests;
4. establish the real desired meaning of historical audit for closed/current/future months from existing project authority before choosing implementation;
5. write one minimal RED proving the unresolved current/future behavior;
6. confirm RED fails only for that missing semantic policy;
7. DELETE -> SIMPLIFY -> REUSE -> MERGE -> EXTEND -> ADD;
8. prefer enforcing the policy at the existing audit-window/start boundary; do not create a scheduler/service/state for it;
9. preserve MCO `America/Bogota` month semantics and deterministic absolute time handling;
10. do not alter SAH-2 active-run identity/UNIQUE;
11. do not alter Start Audit auth/CSRF/transaction/Work contract unless the RED proves inseparability;
12. do not mix exact-order 404, Billing, Financial, webhook hardening, DB UTC, docs cleanup, or Git hygiene;
13. remote writes OFF; REAL_MELI_HTTP=0;
14. full PHP 8.3/8.4/8.5 QA;
15. noise audit;
16. checkpoint;
17. STOP.

Do not merge/deploy or enable remote Mercado Libre writes without explicit user authorization.
