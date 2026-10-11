# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED — STOP AFTER THIS CHECKPOINT**  
**Date:** 2026-10-10  
**Repo:** `sierraglobalcompany-rgb/ErpMeli2.0`  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Previous checkpoint:** `cc453f17c56eb697d52fb2aae5a60d8f07630beb`  
**CLEAN-2 verified SHA:** `357a112e949f383afa67d6802c5e3932f7b3478a`  
**Canonical QA:** `38100764525` — SUCCESS — PHP 8.3 / 8.4 / 8.5  
**PHPStan:** 0 errors  
**PHPUnit:** 240 tests / 1674 assertions  
**Normal CI `REAL_MELI_HTTP`:** `0`  
**Remote Mercado Libre writes:** OFF  
**No merge / no deploy / no production DB or OAuth mutation performed.**

## 1. Authority map

```text
AGENTS.md = engineering law / KISS / TDD / DELETE-first
docs/CURRENT_CHECKPOINT.md = transient state / SHAs / QA / gates / next block
docs/ERP2_AUTHORITY.md = durable architecture + domain contracts
README.md = lean entry index
Git = superseded history
```

Implemented truth order:

```text
active code/schema
> tests/CI for relevant SHA
> CURRENT_CHECKPOINT
> ERP2_AUTHORITY
> README/supporting evidence docs
> history/inference
```

---

## 2. Stable project state

```text
G4 SALES_AUDIT_TRUTH = PASS / CLOSED
Billing C0 tooling Cycles 1-5 = GREEN / CLOSED
CLEAN-0 application QA noise = GREEN / CLOSED
CLEAN-1 authority/documentation noise = GREEN / CLOSED
CLEAN-2 semantic test naming = GREEN / CLOSED
```

Architecture remains deliberately small: one PHP/Slim app, one MariaDB, one Work table/runner, one MeliClient, existing OAuth/rate-safety core, Sales operational truth, Sales Audit and read-only Billing C0 tooling.

---

## 3. CLEAN-2 — CLOSED

Base checkpoint:

```text
cc453f17c56eb697d52fb2aae5a60d8f07630beb
```

Functional/test cleanup commit:

```text
357a112e949f383afa67d6802c5e3932f7b3478a
test(clean): rename historical F5 debug integration test
```

Repository-wide test-name audit found one unequivocal historical phase name:

```text
tests/Integration/F5FinalAdversarialTest.php
-> tests/Integration/DebugRuntimeIntegrationTest.php
```

The test is semantically a Debug runtime integration test covering:

```text
DebugRecorder cap warning + settings cap alert
real webhook HTTP route -> DebugRecorder when DEBUG enabled
```

Only historical `F5` fixture labels/temporary identifiers were renamed to Debug-semantic equivalents. Assertions and behavior were preserved.

Noise audit from `cc453f17...` to `357a112...`:

```text
exactly 1 commit
exactly 1 GitHub-recognized rename
7 additions / 7 deletions
no production file changes
no schema/migration changes
no runtime behavior changes
no Billing/Sales/OAuth/MeliClient/Work changes
```

Recursive tree audit after the rename found no remaining `F5` path. No additional test filename justified a cosmetic rename.

Canonical verification:

```text
RUN:          38100764525 — SUCCESS
PHP 8.3 job:  114356049245 — SUCCESS
PHP 8.4 job:  114356049356 — SUCCESS
PHP 8.5 job:  114356049320 — SUCCESS
PHPStan:      0 errors
PHPUnit:      240/240 tests, 1674 assertions
REAL_MELI_HTTP=0
```

Known MariaDB runner-only `memory.pressure` / `io_uring` warnings remain infrastructure noise.

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

C0 tooling remains read-only and sanitized. It does not persist Billing, refresh OAuth, persist api-usage/cooldown, or perform remote writes.

Before durable Billing handler/schema finalization or Billing Task2, real sanitized seller-MCO evidence must establish first-page shape, `last_id`, cursor progress and terminal semantics. 206/non-progress behavior is accepted only if actually observed. Existing `005_billing.sql` remains provisional until audited against accepted C0 evidence.

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

External gates:

```text
Issue #3 — Hostinger/runtime/main protection
Issue #5 — dedicated Mercado Libre ERP2 app/OAuth reality + authenticated real C0 runtime
```

---

## 6. Next microblock — frozen, not started

```text
CLEAN-3 — audit small Routes/CompanyContext reuse cleanup; change only if net simplification is proven
```

Scope guard:

```text
audit first; DELETE/SIMPLIFY before ADD
no cleanup merely for style
no broad Routes refactor
no new service/helper/abstraction unless strictly reducing existing duplication
no behavior/schema/migration changes
no Billing Cycle 6 implementation
fresh QA if any code changes
noise audit
checkpoint
STOP
```

If the audit does not prove a small safe simplification, CLEAN-3 closes as no-op and the project advances to Billing C0 Cycle 6.

Frozen order:

```text
CLEAN-3 audit/reuse cleanup if justified
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

CLEAN-2 is closed with canonical QA evidence.

**STOP HERE.**

No CLEAN-3 started.  
No Billing Cycle 6 started.  
No Billing Task2 started.  
No real Mercado Libre HTTP executed by C0 tooling.  
No OAuth refresh.  
No merge.  
No deploy.  
No production DB/OAuth mutation.  
No remote Mercado Libre write.  
Remote writes remain OFF.
