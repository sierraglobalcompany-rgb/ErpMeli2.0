# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED — STOP AFTER THIS CHECKPOINT**  
**Date:** 2026-10-10  
**Repo:** `sierraglobalcompany-rgb/ErpMeli2.0`  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Previous checkpoint:** `0c55a78cf292343f266d27fb8eb2a4450a1d5cca`  
**Last verified functional GREEN:** `6b6093b65ad0ab65351140d79f8cfc78d1a56910`  
**Functional QA:** `38093953630` — SUCCESS  
**G4 authority closure:** `a2be85be9e1632979a38de1ca2c35666281133c4`  
**G4 authority QA:** `38094348936` — SUCCESS  
**Remote Mercado Libre writes:** OFF  
**REAL_MELI_HTTP:** `0`

> Live continuity checkpoint. Authority: active code/schema → tests/CI at relevant SHA → this checkpoint → `docs/ERP2_AUTHORITY.md` → `AGENTS.md` → recent explicit decisions → historical plans/issues/PRs.

---

# 1. EXECUTION LAW

```text
1 microblock at a time
RED -> confirm intended failure -> minimal GREEN -> full QA -> noise audit -> checkpoint -> STOP
DELETE -> SIMPLIFY -> REUSE -> MERGE -> EXTEND -> ADD
Correct -> Simple -> Stable -> Maintainable -> Efficient -> Scalable
```

No merge, deploy, destructive cleanup, production DB/OAuth change, or remote Mercado Libre write without explicit authorization.

Supported PHP: `8.3 / 8.4 / 8.5`; Composer PHP contract: `>=8.3 <8.6`.

---

# 2. G4 SALES_AUDIT_TRUTH — PASS / CLOSED

Durable certified flow is GREEN:

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
site MCO business timezone = America/Bogota
historical horizon ~= 12 months
outside horizon -> unavailable before OAuth/HTTP
short non-terminal page -> fail closed
valid = verified relative to seller-search contract, not absolute ML history
```

Architecture remains deliberately small:

```text
1 PHP/Slim app
1 MariaDB
1 Work table / WorkRunner
1 MeliClient
Work types: order.sync + sales.audit
1 SalesAuditHandler
1 SalesAuditRepository
1 SalesAuditRepairHandler
```

No second queue, domain scheduler, generic retry/repair/recovery engine, SalesAuditStateMachine, ConfirmRepository, BaselineService, FinalizerEngine, StartAuditService, extra audit table/state, or generic lock/transaction layer.

---

# 3. SALES AUDIT INVARIANTS — GREEN

## Capture / source safety

- MCO canonical month uses `America/Bogota`; remote search uses UTC guard-band ±1h.
- Guard-band observations remain durable; canonical membership is derived from exact `date_created`.
- Outside source horizon -> `unavailable` before token lookup / HTTP.
- Short non-terminal page -> fail closed; no guessed pagination.
- Offsetless/malformed remote timestamps -> fail closed with no partial evidence.
- Capture does not fan out directly to `order.sync`.

## Repair / verify

- Gap is recomputed from canonical A evidence.
- Exactly one deterministic missing order child at a time.
- Parent `sales.audit` defers while child is pending/running.
- Terminal child + persistent gap -> audit `attention`; child is not recreated automatically.
- No remaining gap -> `repairing -> confirming`.
- Repair/verify stays isolated to capture pass A.

## Confirm B

- Same `sales.audit` Work type; no confirmation queue/type/state.
- B evidence is independent from A in the same durable table.
- A remains unchanged while B is captured.
- B terminal count/fingerprint must be complete.
- A/B equality -> `valid` + `completed_at`, atomically with Work completion.
- A/B mismatch -> `attention`, atomically with Work completion.
- Terminal B creates no extra continuation or `order.sync`.

## Baseline lifecycle

```text
no prior valid -> current confirmed becomes valid
prior equivalent valid -> current valid; old equivalent run/evidence removed
prior divergent valid -> prior valid preserved; current attention preserved
```

No age-based deletion of the valid baseline.

---

# 4. START / CONCURRENCY / PERIOD SAFETY — GREEN

Active-run identity:

```text
company_id + account_id + period_key + contract_version
```

MariaDB generated `active_contract_version` + UNIQUE remains the final concurrency guard for `capturing`, `repairing`, `confirming`. Terminal `valid`, `attention`, `unavailable` permits replacement. No SELECT-before-INSERT race workaround.

Start boundary:

```text
POST /sales/audits
authenticated session
selected company
admin membership
valid CSRF
connected tenant-bound ML account
UI YYYY-MM -> canonical YYYY-MM-01
legacy YYYY-MM-01 accepted
only fully closed MCO months
```

HTTP contract:

```text
success -> 303 /sales
duplicate active -> 409
non-admin -> 403
invalid CSRF -> 419
invalid input/account/current/future -> 422
```

Success is atomic:

```text
create capturing run
-> enqueue initial sales.audit Work
-> COMMIT
```

Current/future rejection occurs before durable state. Admin UI defaults/caps at last closed MCO month.

---

# 5. EXACT-ORDER 404 — GREEN / CLOSED

For exact `GET /orders/{order_id}`:

```text
HTTP 404
-> Work failed
-> last_error_code = meli_order_not_found
-> no retry/defer
-> no order persistence
```

Classification uses HTTP status, not remote-body error code.

```text
401 -> refresh once -> 404
```

ends with the same terminal classification and exactly three physical requests: order GET, OAuth POST, retried order GET. No fourth request.

403/other 4xx retain `meli_remote_permanent`; 429 defer, 5xx retry, transport retry, malformed-200 handling, OAuth and Work semantics remain unchanged.

A repair child ending terminal on 404 with a persistent gap composes with the existing repair rule -> audit `attention`, no child recreation.

TDD / GREEN evidence:

```text
BASE: 025dc620537ca445adac7f97bf7bef67b6ae462f
RED direct: 0ea4e0aabb2ad0639f25c91385568c3d2402a053
RED post-refresh: ced22f341a8176c55042be2527061e2cff72411c
FUNCTIONAL GREEN: 6b6093b65ad0ab65351140d79f8cfc78d1a56910
```

Functional diff was limited to:

```text
app/Modules/Sales/SyncOrder/SyncOrderHandler.php
tests/Integration/SyncOrderHandlerRemoteFailureTest.php
```

---

# 6. G4 ADVERSARIAL COVERAGE — SUFFICIENT / COMPOSABLE

No mega-test was added because existing focused tests already cover the critical failure matrix:

```text
SalesAuditCaptureHandlerTest
  horizon before OAuth/HTTP
  short non-terminal fail closed
  malformed/offsetless date no partial evidence
  no capture fan-out

SalesAuditUnauthorizedTest
  401 refresh once
  second 401 terminal
  OAuth 429 defer
  post-refresh 5xx retry
  403 terminal

SalesAuditTransientFailureTest
  429 defer without attempt burn
  5xx bounded retry
  transport bounded retry

SalesAuditRepairRuntimeTest + repair tests
  one-child repair
  terminal child + gap -> attention
  no recreation
  repaired gap -> confirming

SalesAuditConfirmValidTest
  independent B equality -> valid
  A preserved

SalesAuditConfirmMismatchTest
  B mismatch -> attention
  A fingerprint preserved

SalesAuditBaselineLifecycleTest
  equivalent valid replacement
  divergent run preserves prior valid + attention

SalesAuditActiveRunGuardTest
  DB UNIQUE blocks every active status
  terminal allows replacement

SalesAuditHttpStartRouteTest / SalesAuditFoundationTest
  auth/tenant/CSRF/duplicate/closed/current/future/timezone boundary

SyncOrderHandlerRemoteFailureTest
  direct exact 404 terminal
  401-refresh-404 terminal/no fourth request
```

No new production code or redundant adversarial test was necessary for final G4 closure.

---

# 7. QA / NOISE AUDIT

Functional GREEN:

```text
SHA 6b6093b65ad0ab65351140d79f8cfc78d1a56910
RUN 38093953630 — SUCCESS
PHP 8.3 job 114335875883 — SUCCESS
PHP 8.4 job 114335875661 — SUCCESS
PHP 8.5 job 114335875912 — SUCCESS
PHPStan = 0
PHPUnit = 224 tests / 1571 assertions
REAL_MELI_HTTP = 0
```

G4 authority reconciliation:

```text
SHA a2be85be9e1632979a38de1ca2c35666281133c4
RUN 38094348936 — SUCCESS
PHP 8.3 job 114337051315 — SUCCESS
PHP 8.4 job 114337051376 — SUCCESS
PHP 8.5 job 114337051355 — SUCCESS
PHPStan = 0
PHPUnit = 224 tests / 1571 assertions
REAL_MELI_HTTP = 0
```

Noise audit for G4 final closure:

```text
0c55a78c... -> a2be85be...
only docs/ERP2_AUTHORITY.md changed
no production/test/schema/config changes
```

The authority document was intentionally compacted from stale historical sequencing to current truth. Known Slim 404 diagnostic remains pre-existing passing-test noise and stays out of this block.

---

# 8. GATES

```text
G1 REMOTE_TRUTH: PASS for implemented boundary
G2 WORK_SAFETY: PASS
G3 RATE_SAFETY: PASS for current Sales
G4 SALES_AUDIT_TRUTH: PASS / CLOSED
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

---

# 9. NEXT ORDER — FROZEN

Next microblock only:

```text
small DOC-CLEAN / GitHub issue hygiene
```

Then:

```text
Billing C0 real sanitized MCO smoke
-> Billing Task2
-> sale_fee alignment before Financial
-> Financial no-double-count
```

Separate hardening; do not mix by inertia:

```text
Sales detail multi-account scope
webhook seller multi-company scope
webhook timestamp timezone
webhook_events retention
MariaDB session UTC
Slim diagnostic cleanup
```

---

# 10. STOP

G4 is intentionally closed here.

No merge.  
No deploy.  
No production DB/OAuth change.  
No remote Mercado Libre write.  
No Billing C0 or issue-hygiene work started in this checkpoint.
