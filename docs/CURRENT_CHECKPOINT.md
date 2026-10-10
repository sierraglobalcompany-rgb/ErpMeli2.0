# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED — STOP AFTER THIS CHECKPOINT**  
**Date:** 2026-10-10  
**Repo:** `sierraglobalcompany-rgb/ErpMeli2.0`  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Previous checkpoint:** `b6d4c63561dd5fec824d13a9dcf589e5cef72d46`  
**Previous checkpoint QA:** `38094978802` — SUCCESS  
**Last verified functional GREEN:** `6b6093b65ad0ab65351140d79f8cfc78d1a56910`  
**Functional QA:** `38093953630` — SUCCESS  
**G4 authority closure:** `a2be85be9e1632979a38de1ca2c35666281133c4`  
**G4 authority QA:** `38094348936` — SUCCESS  
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

Core source contract:

```text
source = seller Orders Search
monthly membership = order.date_created
site MCO business timezone = America/Bogota
historical horizon ~= 12 months
outside horizon -> unavailable before OAuth/HTTP
short non-terminal page -> fail closed
valid = verified relative to seller-search contract, not absolute ML history
```

Exact-order 404 is also closed:

```text
GET /orders/{id} -> 404
=> Work failed
=> meli_order_not_found
=> no retry/defer
=> no order persistence
```

`401 -> refresh once -> 404` ends the same way with no fourth request.

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

No second queue, domain scheduler, generic retry/repair/recovery engine, state machine, extra audit table/state, or generic lock/transaction layer.

---

# 3. G4 QA EVIDENCE

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

Known Slim 404 diagnostic remains pre-existing passing-test noise and is not reopened here.

---

# 4. DOC-CLEAN / GITHUB HYGIENE — CLOSED

Historical completed issues archived/closed:

```text
#4  F2 Work Engine
#6  F2 execution ledger
#11 F4 Sales vertical slice plan
```

Only real external gates remain open:

```text
#3 External gate — Hostinger runtime + main protection
#5 External gate — Dedicated Mercado Libre ERP2 app + production OAuth
```

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

Verified after cleanup:

```text
OPEN_PRS = 0
OPEN_ISSUES = #3, #5 only
main HEAD = f10ed1f837ef49a588b62ed34c117f6636b3abd6
main protected = false
```

Hygiene checkpoint:

```text
SHA b6d4c63561dd5fec824d13a9dcf589e5cef72d46
RUN 38094978802 — SUCCESS
PHP 8.3 / 8.4 / 8.5 — SUCCESS
PHPStan = 0
PHPUnit = 224 tests / 1571 assertions
REAL_MELI_HTTP = 0
```

Future integration to `main` must use a fresh PR from the live certified branch after external gate #3 is satisfied. Do not resurrect historical stacked PRs by inertia.

---

# 5. BILLING C0 REAL MCO SMOKE — AUTHORIZED, EXTERNALLY BLOCKED

User explicitly authorized continuing with the read-only Billing C0 smoke on 2026-10-10.

Purpose remains:

```text
obtain sanitized real seller-MCO evidence for billing.period.details
before implementing Billing Task2
```

C0 must prove from a real response sequence:

```text
first-page shape
cursor / next-page behavior
terminal signal
real last_id shape
HTTP 206 behavior if observed
non-progress / repeated-cursor behavior if observed
```

Do not synthesize 206 or non-progress merely to satisfy the gate. If those cases are not observed in the authorized smoke window, record them as still unproven rather than infer behavior.

## Official contract rechecked

Current official Mercado Libre Billing documentation still supports:

```text
GET /billing/integration/periods/key/{KEY}/group/ML/details

document_type = BILL | CREDIT_NOTE
limit = 1000
first from_id = 0
next from_id = previous response last_id
sort_by = ID
order_by = ASC
consume sequentially
206 = incomplete data; wait and retry later
```

Billing is fiscal/financial reconciliation, not the operational sales source. Historical ingestion must remain period-first, not order-by-order.

## Repo boundary verified

Existing operation:

```text
billing.period.details
method = GET
classification = READ
path = /billing/integration/periods/key/{period_key}/group/ML/details
preserve_numbers = true
```

Existing CI/runtime safety:

```text
GitHub QA: APP_ENV=test + REAL_MELI_HTTP=0
RemoteHostPolicy: api.mercadolibre.com blocked outside production
```

Therefore CI/fake transport cannot be accepted as C0 real evidence.

## Exact external blocker

Current ChatGPT execution environment has no usable authenticated Mercado Libre path for this seller:

```text
no access to ERP2 production/sandbox DB token state
no Mercado Libre authenticated connector/plugin available here
no existing real-HTTP smoke workflow in repo
no prior sanitized real C0 artifact found on active branch
```

Important distinction:

> This does **not** prove ERP2 credentials do not exist. It proves only that usable ERP2 OAuth/runtime credentials are not available to this execution environment.

Issue #5 now contains the C0-specific blocker note.

## Sanitization contract for the eventual real smoke

Capture only facts required by C0, for example:

```text
HTTP status
period key + document type
result count
response top-level field names/types
last_id type/value pattern
whether next request advanced
how terminal completion was signaled
whether 206 occurred
whether repeated cursor/non-progress occurred
```

Never print/store:

```text
access token
refresh token
Client Secret
full raw Billing payload
buyer/seller PII
unneeded order/item metadata
```

No Billing Task2 production/test/schema/config code has been started in this C0 attempt.

---

# 6. GATES

```text
G1 REMOTE_TRUTH: PASS for implemented boundary
G2 WORK_SAFETY: PASS
G3 RATE_SAFETY: PASS for current Sales
G4 SALES_AUDIT_TRUTH: PASS / CLOSED
G5 BILLING_CURSOR_TRUTH: BLOCKED ON C0 REAL AUTHENTICATED ENVIRONMENT
G6 FINANCIAL_NO_DOUBLE_COUNT: NOT STARTED
G7 WRITE_FAIL_CLOSED: PASS
G8 HOSTING_REALITY: NOT CERTIFIED
```

External gates:

```text
Issue #3 Hostinger/runtime/main protection
Issue #5 dedicated Mercado Libre ERP2 app/OAuth reality + C0 authenticated-runtime access
```

---

# 7. NEXT ORDER — FROZEN

Do **not** start Billing Task2 yet.

Exact next action when an authenticated ERP2 MCO runtime is available:

```text
execute Billing C0 read-only smoke
-> sanitize evidence
-> verify first page + cursor progress + terminal signal + last_id shape
-> record any actually observed 206/non-progress behavior
-> accept/reject C0
```

Only after C0 is accepted:

```text
Billing Task2
-> sale_fee alignment before Financial
-> Financial no-double-count
```

Separate hardening remains outside this block:

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

Billing C0 was **started as an evidence audit but could not execute the authenticated real request in this harness**.

No fake evidence accepted.  
No Billing Task2 started.  
No merge.  
No deploy.  
No production DB/OAuth mutation.  
No remote Mercado Libre write.  
Remote writes remain OFF.
