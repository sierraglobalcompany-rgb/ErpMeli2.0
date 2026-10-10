# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED — STOP AFTER THIS CHECKPOINT**  
**Date:** 2026-10-10  
**Repo:** `sierraglobalcompany-rgb/ErpMeli2.0`  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Previous checkpoint:** `025dc620537ca445adac7f97bf7bef67b6ae462f`  
**Last verified functional GREEN:** `6b6093b65ad0ab65351140d79f8cfc78d1a56910`  
**QA run:** `38093953630` — SUCCESS  
**Remote Mercado Libre writes:** OFF  
**REAL_MELI_HTTP:** `0`

> Live continuity checkpoint. Authority order: active code/schema → tests/CI at relevant SHA → this checkpoint → `AGENTS.md` → `docs/ERP2_AUTHORITY.md` → recent explicit decisions → historical plans/issues/PRs.

---

# 1. EXECUTION LAW

```text
1 microblock at a time
RED -> confirm intended failure -> minimal GREEN -> full QA -> noise audit -> checkpoint -> STOP
DELETE -> SIMPLIFY -> REUSE -> MERGE -> EXTEND -> ADD
Correct -> Simple -> Stable -> Maintainable -> Efficient -> Scalable
```

No merge, deploy, destructive cleanup, production DB change, OAuth production change, or remote Mercado Libre write without explicit user authorization.

Supported PHP:

```text
8.3 / 8.4 / 8.5
composer require.php = >=8.3 <8.6
```

Architecture remains deliberately small:

```text
1 PHP/Slim app
1 MariaDB
1 Work table
1 WorkRunner
1 MeliClient
1 sales.audit Work type
1 SalesAuditHandler
1 SalesAuditRepository
1 SalesAuditRepairHandler
```

Do not add by inertia: second queue, per-domain queue/scheduler, Retry/Repair/Recovery engines, SalesAuditStateMachine, ConfirmRepository, BaselineService, FinalizerEngine, StartAuditService, generic transaction/lock layers, extra audit tables, or new audit states.

---

# 2. SALES AUDIT CORE — GREEN

Durable flow:

```text
Capture A
-> durable A evidence
-> canonical fingerprint A
-> compare local
-> bounded one-child repair only for missing orders
-> verify local
-> independent Capture B
-> compare A/B
-> baseline lifecycle
```

Source contract:

```text
source = seller Orders Search
monthly membership = order.date_created
business timezone = America/Bogota
historical horizon ~= 12 months
outside horizon -> unavailable before OAuth/HTTP
short non-terminal page -> fail closed
```

Baseline lifecycle:

```text
no prior valid baseline -> current confirmed becomes valid
prior equivalent valid -> current valid; old equivalent valid removed with evidence cascade
prior divergent valid -> prior valid preserved; current attention preserved
```

Closed hardening:

```text
SAH-0:
remote/source incoherence -> meli_sales_audit_contract
internal lifecycle/persistence -> sales_audit_state

SAH-1:
durable lifecycle SQL belongs to SalesAuditRepository
handler remains orchestration

SAH-2:
one active run per company/account/period/contract enforced atomically by MariaDB
```

SAH-2 active statuses: `capturing`, `repairing`, `confirming`. Terminal: `valid`, `attention`, `unavailable`. The generated `active_contract_version` + UNIQUE database boundary remains the concurrency-safe guard; do not replace it with SELECT-before-INSERT or a lock manager.

---

# 3. START AUDIT CONTRACT — GREEN / CLOSED

Endpoint:

```text
POST /sales/audits
```

Boundary:

```text
authenticated session
selected company
company role = admin
valid CSRF
connected Mercado Libre account in selected company
UI month input = YYYY-MM
controller canonicalizes once -> YYYY-MM-01
legacy canonical YYYY-MM-01 remains accepted by the boundary
SalesAuditWindow validates real MCO site/month semantics
only fully closed MCO months are eligible
```

HTTP behavior:

```text
success                         -> 303 /sales
duplicate active scope          -> 409
not admin / invalid membership  -> 403
invalid CSRF                    -> 419
invalid input/account scope     -> 422
current MCO month               -> 422
future MCO month                -> 422
```

Atomic success path remains one PDO transaction:

```text
create capturing sales_audit_run
-> enqueue initial sales.audit Work
-> COMMIT
```

Failure rolls back. Current/future rejection occurs before durable run/Work creation.

Initial Work contract remains:

```text
scope_key    = company:<company_id>:account:<account_id>
type         = sales.audit
resource_key = <run_id>
logical id   = sales.audit:<run_id>:0:50
payload      = {run_id:<run_id>, offset:0, limit:50}
status       = pending
```

Admin UX on `GET /sales`:

```text
connected account selector
<input type="month">
default = last closed MCO month
max = last closed MCO month
CSRF
Iniciar auditoría
```

Backend/domain validation remains authoritative; HTML constraints are UX only.

No separate dashboard, wizard, scheduler, StartAuditService, JavaScript workflow, new route family, schema, migration, state, or Work type was added.

---

# 4. CURRENT/FUTURE PERIOD SEMANTICS — GREEN / CLOSED

Authority is centralized in `SalesAuditWindow`.

Rule:

```text
period_start < start_of_current_month_in_America/Bogota -> eligible
current/future MCO month                                -> rejected 422
```

Rejected current/future start persists:

```text
sales_audit_runs = 0 new rows
sales.audit Work = 0 new rows
```

Deterministic timezone boundary is covered: October 2026 is still open at `2026-11-01T04:30:00Z` (`2026-10-31 23:30` Bogotá) and becomes closed at `2026-11-01T05:00:00Z`.

TDD evidence:

```text
BASE: 7011672b29e910728fc62cc293af9e98223b320e
RED current: 90621ab3737e5a47dd676f9a1c8c4de3f3a563de
  expected 422 / runs=0 / Work=0
  observed 303 / runs=1 / Work=1
RED future: 710faf374c099ecd0ba74812dc9824a73e6bdf3f
  expected 422 / runs=0 / Work=0
  observed 303 / runs=1 / Work=1
GREEN: 49ef2762990484b815a46ace42c9a34af8f104c1
```

Functional diff was limited to:

```text
app/Modules/Sales/Audit/SalesAuditWindow.php
app/Modules/Sales/ViewSales/SalesListController.php
app/Modules/Sales/ViewSales/views/list.php
tests/Integration/SalesAuditFoundationTest.php
tests/Integration/SalesAuditHttpStartRouteTest.php
```

No schema/migration/source-horizon/lifecycle/queue changes.

---

# 5. QA — VERIFIED GREEN

Authoritative workflow for functional GREEN `49ef2762990484b815a46ace42c9a34af8f104c1`:

```text
RUN 38091483115 — SUCCESS
PHP 8.3 job 114328646111 — SUCCESS
PHP 8.4 job 114328646106 — SUCCESS
PHP 8.5 job 114328646058 — SUCCESS
PHPStan = 0 errors
PHPUnit = 222 tests / 1550 assertions on each job
REAL_MELI_HTTP = 0
```

Canonical Linux CI ran the full lint successfully. Local Windows PowerShell lacks `xargs`, and the local `WorkCliEntrypointTest` has a known POSIX environment-assignment incompatibility; neither is a functional regression because the canonical Ubuntu workflow is fully GREEN.

Known pre-existing CI noise: the intentional Slim 404 diagnostic from `BootstrapTest::testStoragePathIsNotExposedAsApplicationRoute()` remains visible while the test passes. Do not mix that cleanup into Sales Audit functional blocks.

---

# 6. EXACT-ORDER 404 — GREEN / CLOSED

Exact endpoint contract:

```text
GET /orders/{order_id} HTTP 404
-> Work failed
-> last_error_code = meli_order_not_found
-> no retry / defer / order persistence
```

Classification uses the HTTP status alone; it does not require `errorCode=order_not_found`. The 404 path exits before order persistence. A 404 after the one allowed 401 token refresh has the same terminal result and issues exactly three physical requests: order GET, OAuth refresh POST, retried order GET.

TDD evidence:

```text
BASE: 025dc620537ca445adac7f97bf7bef67b6ae462f
RED direct: 0ea4e0aabb2ad0639f25c91385568c3d2402a053
  testOrderNotFoundFailsTerminallyWithoutPersistingAnOrder
  expected meli_order_not_found; observed meli_remote_permanent
RED after refresh: ced22f341a8176c55042be2527061e2cff72411c
  testOrderNotFoundAfterUnauthorizedRefreshFailsWithoutAnotherRetry
  expected meli_order_not_found; observed meli_remote_permanent
FUNCTIONAL GREEN: 6b6093b65ad0ab65351140d79f8cfc78d1a56910
```

Both tests verify terminal Work state and released claim. Direct 404 makes one order request and persists zero orders. The 401-refresh-404 test makes exactly three requests, records `refresh_version=1`, and persists zero orders. Its 404 body omits `errorCode`, proving status-based classification.

Functional diff from BASE is limited to:

```text
app/Modules/Sales/SyncOrder/SyncOrderHandler.php
tests/Integration/SyncOrderHandlerRemoteFailureTest.php
```

`SyncOrderHandler` classifies 404 in both the initial and post-refresh exception paths through one private helper. It adds no retry, state, type, service, schema, or configuration. 400/403/other 4xx retain `meli_remote_permanent`; 429, 5xx, transport failure, malformed 200, OAuth refresh, Work claim semantics, and Sales Audit lifecycle are unchanged. A terminal repair child with a remaining gap still moves the audit to `attention` without recreation (`SalesAuditRepairRuntimeTest::testTerminalRepairChildMovesRunToAttentionWithoutRecreation`).

Canonical QA for FUNCTIONAL GREEN:

```text
RUN 38093953630 — SUCCESS
PHP 8.3 job 114335875883 — SUCCESS
PHP 8.4 job 114335875661 — SUCCESS
PHP 8.5 job 114335875912 — SUCCESS
PHPStan = 0 errors on all jobs
PHPUnit = 224 tests / 1571 assertions on each job
REAL_MELI_HTTP = 0
```

Targeted Windows tests against isolated MariaDB on port 3307:

```text
SyncOrderHandlerRemoteFailureTest.php = 7 tests / 62 assertions — PASS
SalesAuditRepairRuntimeTest.php = 3 tests / 42 assertions — PASS
```

No schema/migration, `MeliClient`, `MeliApiException`, Sales Audit production code, or `meli_operations.php` changes. Remote Mercado Libre writes remained OFF.

---

# 7. GATES

```text
G1 REMOTE_TRUTH: PASS for implemented boundary
G2 WORK_SAFETY: PASS
G3 RATE_SAFETY: PASS for current Sales
G4 SALES_AUDIT_TRUTH: IN PROGRESS
   GREEN: Capture A / repair / verify / Capture B / A-B / baseline / SAH-0 / SAH-1 / SAH-2 / Start Audit / current-future semantics / exact-order 404 semantics
   PENDING: final adversarial/noise/docs closure
G5 BILLING_CURSOR_TRUTH: BLOCKED ON C0
G6 FINANCIAL_NO_DOUBLE_COUNT: NOT STARTED
G7 WRITE_FAIL_CLOSED: PASS
G8 HOSTING_REALITY: NOT CERTIFIED
```

External gates remain Issue #3 Hostinger/runtime/main protection and Issue #5 dedicated Mercado Libre ERP2 app/OAuth reality.

`docs/ERP2_AUTHORITY.md` still contains older sequencing text. Active code/tests/this checkpoint outrank that stale roadmap prose until G4 doc cleanup.

---

# 8. NEXT MICROBLOCK — FROZEN

```text
G4 final adversarial/noise/docs closure
```

Closed blocks now include current/future period semantics and exact-order 404 semantics/classification. G4 remains open only for its final adversarial/noise/docs closure.

Do not mix into the next block:

```text
Sales detail multi-account scope
webhook seller multi-company scope
webhook timestamp timezone
webhook_events retention
MariaDB session UTC
Billing C0 / Billing Task2
sale_fee / Financial
OAuth/Hostinger
Git/PR/issue hygiene
Slim diagnostic cleanup
```

After G4 final closure:

```text
small DOC-CLEAN / issue hygiene
-> Billing C0 real sanitized
-> Billing Task2
-> sale_fee alignment
-> Financial no-double-count
```

---

# 9. STOP

Current state is intentionally paused. No merge, deploy, production write, remote Mercado Libre write, or next-block implementation has been authorized by this checkpoint.
