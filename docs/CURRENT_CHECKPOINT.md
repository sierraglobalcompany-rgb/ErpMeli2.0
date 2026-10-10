# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED — STOP AFTER THIS CHECKPOINT**  
**Date:** 2026-10-10  
**Repo:** `sierraglobalcompany-rgb/ErpMeli2.0`  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Previous checkpoint:** `a3d7e5846722ef4931b5ad346d4e2c2ffd590a1b`  
**Last verified functional GREEN:** `6b6093b65ad0ab65351140d79f8cfc78d1a56910`  
**Functional QA:** `38093953630` — SUCCESS  
**G4 authority closure:** `a2be85be9e1632979a38de1ca2c35666281133c4`  
**G4 authority QA:** `38094348936` — SUCCESS  
**G4 checkpoint QA:** `38094480858` — SUCCESS  
**Remote Mercado Libre writes:** OFF  
**REAL_MELI_HTTP normal:** `0`

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

Certified durable flow:

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

Capture/source:

- MCO canonical month uses `America/Bogota`; remote search uses UTC guard-band ±1h.
- Guard-band evidence is durable; canonical membership derives from exact `date_created`.
- Outside source horizon -> `unavailable` before token lookup/HTTP.
- Short non-terminal page -> fail closed; no guessed pagination.
- Offsetless/malformed remote timestamps -> fail closed with no partial evidence.
- Capture never fans out directly to `order.sync`.

Repair/verify:

- gap recomputed from canonical A evidence;
- exactly one deterministic missing-order child at a time;
- parent defers while child pending/running;
- terminal child + persistent gap -> audit `attention`, no recreation;
- no gap -> `repairing -> confirming`;
- repair/verify isolated to capture pass A.

Confirm/baseline:

- same `sales.audit` Work type;
- independent durable B evidence;
- A/B equality -> `valid` atomically with Work completion;
- A/B mismatch -> `attention` atomically with Work completion;
- no prior valid -> current confirmed becomes valid;
- prior equivalent valid -> current valid, old equivalent run/evidence removed;
- prior divergent valid -> prior valid preserved, current attention preserved.

Start/concurrency:

```text
POST /sales/audits
admin + tenant + CSRF + connected account
fully closed MCO month only
atomic run + initial sales.audit Work
DB UNIQUE guards one active run per company/account/period/contract
```

Exact-order 404:

```text
GET /orders/{id} -> 404
=> Work failed
=> meli_order_not_found
=> no retry/defer
=> no order persistence
```

`401 -> refresh once -> 404` has the same terminal result and no fourth request. Persistent repair gap composes to audit `attention`.

---

# 4. QA — LAST FUNCTIONAL + G4 CLOSURE

Functional GREEN:

```text
SHA 6b6093b65ad0ab65351140d79f8cfc78d1a56910
RUN 38093953630 — SUCCESS
PHP 8.3 / 8.4 / 8.5 — SUCCESS
PHPStan = 0
PHPUnit = 224 tests / 1571 assertions
REAL_MELI_HTTP = 0
```

Authority reconciliation:

```text
SHA a2be85be9e1632979a38de1ca2c35666281133c4
RUN 38094348936 — SUCCESS
PHP 8.3 / 8.4 / 8.5 — SUCCESS
PHPStan = 0
PHPUnit = 224 tests / 1571 assertions
REAL_MELI_HTTP = 0
```

G4 checkpoint:

```text
SHA a3d7e5846722ef4931b5ad346d4e2c2ffd590a1b
RUN 38094480858 — SUCCESS
PHP 8.3 / 8.4 / 8.5 — SUCCESS
```

Known Slim 404 diagnostic remains pre-existing passing-test noise and is not reopened here.

---

# 5. DOC-CLEAN / GITHUB HYGIENE — CLOSED

Audit performed against live GitHub metadata after G4.

## Issues

Historical completed issues closed with an archival note:

```text
#4  F2 — KISS Work Engine
#6  F2 execution ledger
#11 F4 Sales vertical slice plan
```

Only real external gates remain open:

```text
#3 External gate — Hostinger runtime + main protection
#5 External gate — Dedicated Mercado Libre ERP2 app + production OAuth
```

Both open issue bodies were rewritten to current truth; they no longer instruct continuation through obsolete stacked PRs.

`main` was verified directly:

```text
HEAD = f10ed1f837ef49a588b62ed34c117f6636b3abd6
protected = false
```

Issue #3 therefore remains open.

## Pull requests

Historical stacked Draft PRs archived/closed **without merge**:

```text
#2  F0/F1 foundation
#8  F2 Work Engine
#10 F3 Meli Core/OAuth
#12 F4 Sales vertical slice
#13 F4.1 stabilization
#14 F5 Debug DVR
#15 F6A Billing Period-First
```

REST verification after cleanup:

```text
OPEN_PRS = 0
OPEN_ISSUES = #3, #5 only
```

PR #14 special audit:

- its head diverged by four commits after the F6 fork;
- all four were documentation-only governance changes in `AGENTS.md`/`README.md`;
- the current active branch already contains those KISS/noise-reduction rules in newer/stronger form;
- no functional F5 code was discarded.

PR #15 archive note explicitly states that only its contract/schema foundation survives; Billing Task2 is still blocked on C0 real evidence.

Future integration to `main` must use a **fresh PR from the live certified branch** after external gate #3 is satisfied. Do not resurrect the historical stacked PR chain by inertia.

---

# 6. GATES

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

External gates:

```text
Issue #3 Hostinger/runtime/main protection
Issue #5 dedicated Mercado Libre ERP2 app/OAuth reality
```

---

# 7. NEXT ORDER — FROZEN

Next microblock only:

```text
Billing C0 real sanitized MCO smoke
```

Purpose: obtain real read-only evidence for the current `billing.period.details` contract before implementing Billing Task2.

C0 must prove from sanitized real MCO evidence:

```text
first-page shape
cursor/next-page behavior
terminal signal
real last_id shape
HTTP 206 behavior
non-progress condition
```

Do **not** infer any of these from documentation, ERP1, mocks or short/empty pages.

C0 is a real-HTTP smoke and therefore requires explicit authorization plus usable production/sandbox credentials/environment. It is read-only; remote writes remain OFF.

Only after C0 evidence is accepted:

```text
Billing Task2
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

# 8. STOP

DOC-CLEAN / GitHub issue hygiene is intentionally closed here.

No production/test/schema/config code changed in this microblock.  
No merge.  
No deploy.  
No production DB/OAuth change.  
No remote Mercado Libre write.  
Billing C0 was **not** started.
