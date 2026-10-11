# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED — STOP AFTER THIS CHECKPOINT**  
**Date:** 2026-10-10  
**Repo:** `sierraglobalcompany-rgb/ErpMeli2.0`  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Previous checkpoint:** `5c8eea52855388a57ee2e7b1ea92c409b4755aae`  
**Billing C0 Cycle 6 verified SHA:** `b02b6478b77d87e1a3188fa875f7e1bbc8356a16`  
**Canonical QA:** `38106904760` — SUCCESS — PHP 8.3 / 8.4 / 8.5  
**PHPStan:** 0 errors  
**PHPUnit:** 242 tests / 1699 assertions  
**Normal CI `REAL_MELI_HTTP`:** `0`  
**Remote Mercado Libre writes:** OFF  
**No real Mercado Libre HTTP / no OAuth refresh / no production DB mutation / no merge / no deploy.**

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
Billing C0 tooling Cycles 1-6 = GREEN / CLOSED
CLEAN-0 application QA noise = GREEN / CLOSED
CLEAN-1 authority/documentation noise = GREEN / CLOSED
CLEAN-2 semantic test naming = GREEN / CLOSED
CLEAN-3 Routes settings wiring reuse = GREEN / CLOSED
```

Architecture remains deliberately small: one PHP/Slim app, one MariaDB, one Work table/runner, one MeliClient, existing OAuth/rate-safety core, Sales operational truth, Sales Audit and read-only Billing C0 tooling.

---

## 3. Billing C0 Cycle 6 — GREEN / CLOSED

Objective implemented:

```text
bin/billing-c0-smoke.php
-> BillingC0Runtime
-> CurlMeliTransport
-> MeliClient
-> BillingC0Probe::forMeliClient()
```

The implementation reuses the existing client, transport and probe. No Billing-specific client/service/runner/engine/repository/transport, new Work type, table, migration, schema or architecture layer was added.

### 3.1 Guard contract

Real C0 HTTP is fail-closed behind explicit gates:

```text
APP_ENV=production
BILLING_C0_REAL_HTTP=1
valid CLI arguments
REAL_MELI_HTTP=1
```

CLI arguments are validated before the global `REAL_MELI_HTTP` gate so invalid-input diagnostics remain deterministic without DB or HTTP activity.

Normal CI remains:

```text
REAL_MELI_HTTP=0
```

Therefore the real Curl path is never executed in normal CI.

### 3.2 Runtime composition

`BillingC0Runtime::resolve()` remains responsible for:

```text
connected Mercado Libre account
stored encrypted access token
>60 seconds remaining validity
NO OAuth refresh
```

`BillingC0Runtime::probe()` now composes:

```text
injected MeliTransport
SystemSettingsRepository
existing MeliClient
existing billing.period.details operation
existing BillingC0Probe::forMeliClient()
```

The C0 MeliClient composition intentionally omits:

```text
ApiUsageRecorder
MeliCooldownRepository
DebugRecorder
```

so the zero-side-effect smoke does not persist usage/cooldown/debug state.

### 3.3 Fake-transport proof

Integration coverage proves the runtime composition with a fake transport before any real HTTP:

```text
GET /billing/integration/periods/key/2026-09-01/group/ML/details
document_type=BILL
limit=1000
from_id=0
sort_by=ID
order_by=ASC
```

Verified invariants:

```text
existing MeliClient path used
Authorization token reaches only transport boundary
returned evidence contains no access/refresh token
returned evidence contains no raw payload
api_usage_daily unchanged
meli_cooldowns unchanged
meli_tokens unchanged
meli_accounts status unchanged
bounded max-pages preserved
```

Structural evidence remains limited to page/status/cursor/result-count/top-level-key/last-id metadata plus stop flags.

### 3.4 CLI composition

`bin/billing-c0-smoke.php` now:

```text
validates all gates
loads AppConfig + PDO
uses BillingC0Runtime
loads existing config/meli_operations.php
uses CurlMeliTransport(new RemoteHostPolicy(), $config->appEnv)
invokes BillingC0Runtime::probe()
writes only sanitized JSON evidence to stdout
fails closed to stderr with exit 2
returns exit 0 only after a completed probe
```

No hard-coded `site_id === MCO` application guard was added in Cycle 6. The first real execution remains explicitly scoped to a seller-MCO account as an operational evidence gate, not as an invented endpoint contract.

### 3.5 TDD trail

Cycle 6 was executed RED → GREEN in small commits without history rewrite:

```text
81d0b665da0e243c3b14f1c4caa9a10b9fbabaf9  RED — global REAL_MELI_HTTP gate
58c4fd8f9ca42ca53c05e60b445bfa3f7d71675f  first GREEN attempt
fad2b42dcb382a636f9cc8d26abee4e114e67d39  fix validation ordering after diagnosed regression
637e33d28ffcab5b738145cb1f5072a325f3b2d1  RED — runtime/MeliClient composition
89771b586bca057819070ce87d14c417ac0f8244  GREEN — runtime/MeliClient composition
fda926f51b4b27463b258161df68aad3b79dbeca  RED — guarded CLI Curl composition
b02b6478b77d87e1a3188fa875f7e1bbc8356a16  GREEN — guarded CLI Curl composition
```

Expected RED evidence was observed before each production implementation. The first guard GREEN attempt exposed an existing CLI-contract regression because the new guard was placed before argument validation; root cause was diagnosed and corrected by moving only the gate, not by weakening tests.

### 3.6 Canonical QA

Final functional run:

```text
RUN:          38106904760 — SUCCESS
PHP 8.3 job:  114374244772 — SUCCESS
PHP 8.4 job:  114374244678 — SUCCESS
PHP 8.5 job:  114374244819 — SUCCESS
PHPStan:      0 errors
PHPUnit:      242 tests / 1699 assertions
REAL_MELI_HTTP=0
```

Known MariaDB runner-only `memory.pressure` / `io_uring` warnings remain infrastructure noise.

### 3.7 Noise audit

Compared with checkpoint `5c8eea52855388a57ee2e7b1ea92c409b4755aae`:

```text
7 granular TDD/debug commits
exactly 4 files changed

app/Modules/Billing/C0/BillingC0Runtime.php      +54 / -0
bin/billing-c0-smoke.php                        +21 / -3
tests/Integration/BillingC0CliRuntimeTest.php  +21 / -5
tests/Integration/BillingC0RuntimeTest.php     +90 / -1
```

No changes to:

```text
schema
migrations
config
Sales
OAuth implementation
Work
CompanyContext
MeliClient implementation
CurlMeliTransport implementation
BillingC0Probe implementation
remote-write policy
```

Cycle 6 verdict:

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

Cycles 1-6 prove only the guarded tooling and synthetic/fake-transport behavior. They do NOT establish the real seller response contract.

Before durable Billing handler/schema finalization or Billing Task2, real sanitized seller-MCO evidence must establish:

```text
first-page top-level shape
results structure needed by later Billing work
last_id type/value behavior
cursor progression
empty-result semantics
206 semantics if observed
non-progress behavior if observed
terminal condition
```

Do not invent semantics that are not observed. Existing `005_billing.sql` remains provisional until audited against accepted real C0 evidence.

---

## 5. Gates

```text
G1 REMOTE_TRUTH: PASS for implemented boundary
G2 WORK_SAFETY: PASS
G3 RATE_SAFETY: PASS for current Sales
G4 SALES_AUDIT_TRUTH: PASS / CLOSED
G5 BILLING_CURSOR_TRUTH: IN PROGRESS — Cycles 1-6 GREEN/CLOSED; real authenticated evidence missing
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

## 6. Next microblock — AUTHORIZATION REQUIRED, not started

```text
REAL AUTHENTICATED C0 SELLER-MCO EVIDENCE RUN
```

This next block is intentionally NOT authorized by Cycle 6 completion.

Before any real HTTP, require explicit user authorization in the active conversation. Then re-verify:

```text
target account is the intended seller-MCO account
account status = connected
stored access token has >60 seconds validity
no OAuth refresh is required or permitted
APP_ENV=production
BILLING_C0_REAL_HTTP=1
REAL_MELI_HTTP=1 only for the explicitly authorized invocation
bounded --max-pages
read-only billing.period.details only
sanitized structural output only
no token/raw payload persistence or display
no Billing persistence
no remote Mercado Libre writes
```

After the authorized evidence run:

```text
sanitize + audit evidence
-> accept/reject cursor + terminal semantics
-> checkpoint
-> audit provisional 005_billing.sql against accepted evidence
-> Billing Task2
-> sale_fee alignment
-> Financial no-double-count
```

---

## 7. STOP

Billing C0 Cycle 6 is closed with canonical remote QA evidence.

**STOP HERE.**

Real authenticated Billing C0 HTTP has NOT been executed.  
Real seller-MCO C0 evidence has NOT been collected.  
Billing Task2 has NOT started.  
No OAuth refresh.  
No merge.  
No deploy.  
No production DB/OAuth mutation.  
No remote Mercado Libre write.  
Remote writes remain OFF.
