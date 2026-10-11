# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED — STOP AFTER THIS CHECKPOINT**  
**Date:** 2026-10-10  
**Repo:** `sierraglobalcompany-rgb/ErpMeli2.0`  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Previous checkpoint:** `0d3904a01502e5fba0720462e804a8c6eb4410a0`  
**CLEAN-1 verified SHA:** `e5cc57d70ab485077cdb079a9cf9d1b0562020cd`  
**Canonical QA:** `38100298528` — SUCCESS — PHP 8.3 / 8.4 / 8.5  
**PHPStan:** 0 errors  
**PHPUnit:** 240 tests / 1674 assertions  
**Normal CI `REAL_MELI_HTTP`:** `0`  
**Remote Mercado Libre writes:** OFF  
**No merge / no deploy / no production DB or OAuth mutation performed.**

## 1. Authority map

Use one responsibility per document:

```text
AGENTS.md
= engineering law / KISS / TDD / DELETE-first rules

docs/CURRENT_CHECKPOINT.md
= transient project state / SHAs / QA / gates / next microblock

docs/ERP2_AUTHORITY.md
= durable architecture + domain contracts

README.md
= lean entry index only

Git
= superseded history
```

For implemented truth:

```text
active code/schema
> tests/CI for the relevant SHA
> CURRENT_CHECKPOINT
> ERP2_AUTHORITY
> README/supporting evidence docs
> history/inference
```

A recent explicit user decision governs authorization/scope of the next action and must be reflected in checkpoint/authority when it changes durable truth.

---

## 2. Stable project state

```text
G4 SALES_AUDIT_TRUTH = PASS / CLOSED
Billing C0 tooling Cycles 1-5 = GREEN / CLOSED
CLEAN-0 application QA noise = GREEN / CLOSED
CLEAN-1 authority/documentation noise = GREEN / CLOSED
```

Current architecture remains deliberately small: one PHP/Slim app, one MariaDB, one Work table/runner, one MeliClient, existing OAuth/rate-safety core, Sales operational truth, Sales Audit and read-only Billing C0 tooling.

No runtime behavior, schema, migration, Work type, queue, Sales logic, Billing domain logic, OAuth code, MeliClient code, transport or persistence changed in CLEAN-1.

---

## 3. CLEAN-1 — CLOSED

Base checkpoint:

```text
0d3904a01502e5fba0720462e804a8c6eb4410a0
```

Documentation commits:

```text
e1a9c9d928fe8c817653e26db848611102860eaf
docs(clean): reduce authority and historical noise

e5cc57d70ab485077cdb079a9cf9d1b0562020cd
docs(clean): align supporting docs with current gates
```

Outcome:

```text
README.md: ~1631 -> 85 lines
ERP2_AUTHORITY.md: durable contracts only
15 historical/premature docs removed
retained docs/ surface reduced to 6 living files
stale F0/F3 phase language removed from supporting docs
G8 HOSTING_REALITY terminology aligned
old F6 contract removed after preserving accepted external Billing facts
```

Removed material is still available through Git history and is no longer an authority source.

Living `docs/` surface:

```text
CURRENT_CHECKPOINT.md
ERP2_AUTHORITY.md
meli-contracts-2026.md
mercadolibre-app-erp2.md
runtime-preflight.md
hostinger-runtime-evidence.md
```

Canonical verification for CLEAN-1 functional/doc SHA `e5cc57d7...`:

```text
RUN:          38100298528 — SUCCESS
PHP 8.3 job:  114354685825 — SUCCESS
PHP 8.4 job:  114354685879 — SUCCESS
PHP 8.5 job:  114354685902 — SUCCESS
PHPStan:      0 errors
PHPUnit:      240/240 tests, 1674 assertions
REAL_MELI_HTTP=0
```

Known runner-only MariaDB `memory.pressure` / `io_uring` warnings remain infrastructure noise, not ERP application warnings.

---

## 4. Billing C0 hard gate

Billing remains fiscal/financial reconciliation, not Sales operational truth.

Accepted read request contract:

```text
GET /billing/integration/periods/key/{period_key}/group/ML/details
period_key = YYYY-MM-01
document_type = BILL | CREDIT_NOTE
limit = 1000
from_id = current cursor
sort_by = ID
order_by = ASC
```

Current C0 tooling is read-only and sanitized. It does not persist Billing, refresh OAuth, persist api-usage/cooldown, or perform remote writes.

Before durable Billing handler/schema finalization or Billing Task2, real sanitized seller-MCO evidence must observe:

```text
first-page shape
last_id shape
sequential cursor progress
terminal signal
HTTP 206 only if actually observed
repeated/non-progress only if actually observed
```

Do not synthesize 206/non-progress/terminal semantics. Any existing pre-release Billing schema remains provisional until audited against accepted C0 evidence.

---

## 5. Gates

```text
G1 REMOTE_TRUTH: PASS for implemented boundary
G2 WORK_SAFETY: PASS
G3 RATE_SAFETY: PASS for current Sales
G4 SALES_AUDIT_TRUTH: PASS / CLOSED
G5 BILLING_CURSOR_TRUTH: IN PROGRESS — C0 tooling Cycles 1-5 GREEN; real authenticated evidence missing
G6 FINANCIAL_NO_DOUBLE_COUNT: NOT STARTED
G7 WRITE_FAIL_CLOSED: PASS
G8 HOSTING_REALITY: NOT CERTIFIED
```

External gates remain:

```text
Issue #3 — Hostinger/runtime/main protection
Issue #5 — dedicated Mercado Libre ERP2 app/OAuth reality + authenticated real C0 runtime
```

---

## 6. Next microblock — frozen, not started

```text
CLEAN-2 — semantic test naming / remove historical phase names where safe
```

Scope guard:

```text
rename only when semantics improve
no behavior changes
no broad refactor
no schema/migration changes
no Billing Cycle 6 implementation
full reference audit before renames
fresh QA
noise audit
checkpoint
STOP
```

Frozen order:

```text
CLEAN-2 semantic naming
-> CLEAN-3 small Routes/CompanyContext reuse cleanup if still justified
-> Billing C0 Cycle 6 guarded CLI composition
-> explicit authorization for real authenticated C0 seller-MCO execution
-> sanitize + audit evidence
-> accept/reject C0
-> audit provisional 005_billing.sql against accepted evidence
-> Billing Task2
-> sale_fee alignment
-> Financial no-double-count
```

---

## 7. STOP

CLEAN-1 is closed with canonical QA evidence.

**STOP HERE.**

No CLEAN-2 started.  
No Billing Cycle 6 started.  
No Billing Task2 started.  
No real Mercado Libre HTTP executed by C0 tooling.  
No OAuth refresh.  
No merge.  
No deploy.  
No production DB/OAuth mutation.  
No remote Mercado Libre write.  
Remote writes remain OFF.
