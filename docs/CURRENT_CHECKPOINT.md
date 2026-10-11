# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED — STOP AFTER THIS CHECKPOINT**  
**Date:** 2026-10-10  
**Repo:** `sierraglobalcompany-rgb/ErpMeli2.0`  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Previous checkpoint:** `96d26328473228516cb1abfe9b83c76ef083a63f`  
**CLEAN-3 verified SHA:** `c3aaa1b09968e178eb1b0c9f7837eea396bf60c3`  
**Canonical QA:** `38105874676` — SUCCESS — PHP 8.3 / 8.4 / 8.5  
**PHPStan:** 0 errors in full QA  
**PHPUnit:** 240 tests / 1674 assertions in full QA  
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
CLEAN-3 Routes settings wiring reuse = GREEN / CLOSED
```

Architecture remains deliberately small: one PHP/Slim app, one MariaDB, one Work table/runner, one MeliClient, existing OAuth/rate-safety core, Sales operational truth, Sales Audit and read-only Billing C0 tooling.

---

## 3. CLEAN-3 — CLOSED

Base checkpoint:

```text
96d26328473228516cb1abfe9b83c76ef083a63f
```

Functional refactor commit:

```text
c3aaa1b09968e178eb1b0c9f7837eea396bf60c3
refactor(routes): reuse system settings controller wiring
```

Verified change:

```text
app/Core/Http/Routes.php only
1 file changed
19 insertions
33 deletions
```

The three Settings routes now reuse one local private helper:

```php
private static function systemSettingsController(AppConfig $config): SystemSettingsController
```

The helper centralizes the same existing controller construction for:

```text
/settings/system
/settings/system/debug/clear
/settings/system/debug/export
```

No new production class, service, interface, schema, migration, config, Work type or architecture layer was added. OAuth, CompanyContext, Sales, Billing and Work behavior were not changed.

Characterization before/after remained GREEN:

```text
12 tests
103 assertions
0 failures
0 errors
```

Full local QA evidence:

```text
composer qa exit 0
PHPStan 0 errors
PHPUnit 240 tests / 1674 assertions
REAL_MELI_HTTP=0
```

Windows-native full QA exposed environment-specific shell/process issues only; the same tree passed the full suite in the writable Linux environment. No production code was changed to accommodate Windows-only shell behavior.

Canonical remote verification:

```text
RUN:          38105874676 — SUCCESS
PHP 8.3 job:  114371183043 — SUCCESS
PHP 8.4 job:  114371183194 — SUCCESS
PHP 8.5 job:  114371183184 — SUCCESS
```

Noise audit:

```text
exactly 1 functional commit from CLEAN-2 checkpoint
exactly 1 production file changed
19 additions / 33 deletions
no test files changed
no schema/migration changes
no config changes
no docs changed by functional commit
no new classes
no new services
no new interfaces
no new tables
no new Work types
no unexpected files
```

CLEAN-3 verdict:

```text
GREEN / CLOSED
```

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

C0 tooling remains read-only and sanitized. It must not persist Billing, refresh OAuth, perform remote writes, or persist api-usage/cooldown in the zero-side-effect smoke composition.

Before durable Billing handler/schema finalization or Billing Task2, real sanitized seller-MCO evidence must establish first-page shape, `last_id`, cursor progress and terminal semantics. `206`, non-progress, empty-result and terminal behavior remain evidence-driven; do not invent semantics. Existing `005_billing.sql` remains provisional until audited against accepted C0 evidence.

The exact site guard for seller-MCO must be audited before the first real C0 HTTP execution; do not silently add or omit an `MCO` guard.

---

## 5. Gates

```text
G1 REMOTE_TRUTH: PASS for implemented boundary
G2 WORK_SAFETY: PASS
G3 RATE_SAFETY: PASS for current Sales
G4 SALES_AUDIT_TRUTH: PASS / CLOSED
G5 BILLING_CURSOR_TRUTH: IN PROGRESS — Cycles 1-5 GREEN; Cycle 6 next; real authenticated evidence missing
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
Billing C0 Cycle 6 — guarded CLI composition
```

Objective:

```text
bin/billing-c0-smoke.php
-> BillingC0Runtime
-> CurlMeliTransport
-> MeliClient
-> BillingC0Probe::forMeliClient()
```

Cycle 6 is code/tests only and must use fake transport first. It does NOT authorize real Mercado Libre HTTP.

Must preserve:

```text
one existing MeliClient
one existing transport path
read-only operation billing.period.details
bounded max-pages
sanitized structural evidence only
no token/raw payload output
no Billing persistence
no OAuth refresh
no remote ML writes
no ApiUsageRecorder persistence
no MeliCooldown persistence
REAL_MELI_HTTP=0 in normal local/CI
```

Do not create:

```text
BillingC0Service
BillingClient
BillingRunner
BillingEngine
BillingTransport
BillingRepository
new Work type
```

Frozen order:

```text
Billing C0 Cycle 6 guarded CLI composition
-> checkpoint
-> explicit user authorization for real authenticated C0 seller-MCO execution
-> sanitized evidence
-> accept/reject cursor + terminal semantics
-> audit provisional 005_billing.sql
-> Billing Task2
-> sale_fee alignment
-> Financial no-double-count
```

---

## 7. STOP

CLEAN-3 is closed with canonical remote QA evidence.

**STOP HERE.**

Billing Cycle 6 has NOT started.  
Billing Task2 has NOT started.  
No real Billing C0 Mercado Libre HTTP has been executed.  
No OAuth refresh.  
No merge.  
No deploy.  
No production DB/OAuth mutation.  
No remote Mercado Libre write.  
Remote writes remain OFF.
