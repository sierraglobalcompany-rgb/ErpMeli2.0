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

## 4. Billing local-first boundary

Billing remains fiscal/financial reconciliation, not Sales operational truth.

Accepted read request contract:

```text
GET /billing/integration/periods/key/{period_key}/group/ML/details
period_key = YYYY-MM-01
document_type = BILL | CREDIT_NOTE
limit = 1000
first from_id = 0
next from_id = prior last_id
sort_by = ID
order_by = ASC
```

Cycles 1-6 prove the guarded tooling and fake-transport behavior. They do NOT establish the real seller response contract. Billing durable work proceeds locally using accepted official documentation, contractual fixtures, the existing MeliClient, fake MeliTransport and local MariaDB. Real seller evidence is not a hard gate for local Billing Task2.

Local pagination contract:

```text
first from_id = 0
next from_id = prior last_id
empty results = terminal
HTTP 206 = incomplete; never caught_up
BILL and CREDIT_NOTE = separate streams
```

Real authenticated C0 seller-MCO evidence is deferred to F14/preproduction. C0 remains read-only, sanitized, and has no Billing persistence, OAuth refresh, usage/cooldown persistence or remote writes. Do not invent external semantics beyond documented and safely inferable behavior.

---

## 5. Gates

```text
G1 REMOTE_TRUTH: PASS for implemented boundary
G2 WORK_SAFETY: PASS
G3 RATE_SAFETY: PASS for current Sales
G4 SALES_AUDIT_TRUTH: PASS / CLOSED
G5 BILLING_CURSOR_TRUTH: IN PROGRESS LOCAL — no external blocker
G6 FINANCIAL_NO_DOUBLE_COUNT: NOT STARTED
G7 WRITE_FAIL_CLOSED: PASS
G8 HOSTING_REALITY: DEFERRED TO F14 / NOT CERTIFIED
```

External gates:

```text
Issue #3 — Hostinger/runtime/main protection
Issue #5 — dedicated Mercado Libre ERP2 app/OAuth reality + authenticated real C0 runtime
```

---

## 6. Next microblock — LOCAL-CORRECTNESS-1, not started

```text
LOCAL-CORRECTNESS-1 — DB SESSION UTC
```

Real authenticated C0 seller-MCO evidence is deferred to F14/preproduction and is not required before local Billing Task2. Normal development and QA keep `REAL_MELI_HTTP=0`. Do not execute this or another microblock until G0 is reviewed and the next block is explicitly authorized.

---

## 7. STOP

Billing C0 Cycle 6 is closed with canonical remote QA evidence.

**STOP HERE.**

Real authenticated Billing C0 HTTP has NOT been executed.  
Real seller-MCO C0 evidence has NOT been collected; it is deferred to F14 and does not block local Billing Task2.
Billing Task2 has NOT started.  
No OAuth refresh.  
No merge.  
No deploy.  
No production DB/OAuth mutation.  
No remote Mercado Libre write.  
Remote writes remain OFF.
Normal development/QA uses `REAL_MELI_HTTP=0`.
