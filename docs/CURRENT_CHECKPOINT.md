# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED — STOP AFTER THIS CHECKPOINT**  
**Date:** 2026-10-10  
**Repo:** `sierraglobalcompany-rgb/ErpMeli2.0`  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Previous checkpoint:** `eb6f54e1a45b4b7357535be21968744329c9cdc9`  
**Cycle 5 functional GREEN:** `7fa54adb74eb7ea23f214f6025bae7268e46cc5d`  
**Latest verified functional/test SHA:** `ed571ae1fcd78f697fd91758cc2a3b273ee39847`  
**Latest verified QA:** `38099137542` — SUCCESS — PHP 8.3 / 8.4 / 8.5  
**Remote Mercado Libre writes:** OFF  
**Normal CI `REAL_MELI_HTTP`:** `0`  
**No merge / no deploy / no production DB or OAuth mutation performed.**

> Authority: active code/schema at branch HEAD → tests/CI → this checkpoint → `docs/ERP2_AUTHORITY.md` → `AGENTS.md` → recent explicit user decisions → historical material.

---

# 1. EXECUTION LAW

```text
1 microblock at a time
RED -> intended failure -> minimal GREEN -> full QA -> noise audit -> checkpoint -> STOP
DELETE -> SIMPLIFY -> REUSE -> MERGE -> EXTEND -> ADD
Correct -> Simple -> Stable -> Maintainable -> Efficient -> Scalable
```

Supported PHP: `8.3 / 8.4 / 8.5`; Composer PHP contract: `>=8.3 <8.6`.

No merge, deploy, destructive cleanup, production DB/OAuth mutation, or remote Mercado Libre write without explicit authorization.

---

# 2. STABLE PROJECT STATE

`G4 SALES_AUDIT_TRUTH` remains **PASS / CLOSED**.

Sales Audit design is now intentionally KISS:

```text
SalesAuditHandler owns remote I/O + orchestration
SalesAuditRepository owns durable lifecycle/fingerprint/baseline transitions
SalesAuditRepairHandler remains the bounded repair responsibility
one active run per company/account/period/contract enforced by existing sales_audit_runs schema
no ConfirmRepository
no BaselineService
no state-machine engine
no extra queue/runner
```

Important closed Sales fixes:

```text
remote contract errors -> meli_sales_audit_contract
internal lifecycle/persistence errors -> sales_audit_state
Capture A -> local compare -> bounded repair -> verify -> Capture B -> A/B compare -> baseline lifecycle
exact GET /orders/{id} 404 -> terminal meli_order_not_found
```

Architecture remains: one PHP/Slim app, one MariaDB, one Work table/runner/client; no speculative service/state-machine proliferation.

---

# 3. BILLING C0 — HARD GATE

Billing Task2 must **not** start until C0 obtains sanitized real MCO seller evidence from:

```text
GET /billing/integration/periods/key/{period_key}/group/ML/details
```

C0 must observe from real responses:

```text
first-page shape
last_id shape
sequential cursor progress
terminal signal
HTTP 206 behavior only if actually observed
repeated/non-progress cursor only if actually observed
```

Never synthesize 206/non-progress evidence.

Billing remains fiscal/financial reconciliation and period-first; it is not Sales operational truth.

---

# 4. BILLING C0 TOOLING — CYCLES 1-5 GREEN

## Cycle 1 — sanitized cursor probe

```text
RED   793853b0f5641e78ebfef7526e404781467d7417
GREEN df0637bcb87b1477833dd10618b1ec869cbfc206
QA    38096002800 — SUCCESS — PHP 8.3/8.4/8.5
```

Contract:

```text
start from_id = "0"
follow last_id sequentially
stop on empty results
stop immediately on HTTP 206
stop on repeated/non-progress cursor
bounded max pages
sanitized structural evidence only
never expose result rows/tokens/PII/arbitrary payload
```

## Cycle 2 — CLI safety guards

```text
RED   92ccb33db3fb7cc0fe2f293071b482403b66e07a
GREEN c705f239c7c71d7cc191f412c04a53e92bd29baf
QA    38096234324 — SUCCESS — PHP 8.3/8.4/8.5
```

Guards before DB/HTTP:

```text
APP_ENV == production
BILLING_C0_REAL_HTTP == 1
```

## Cycle 3 — CLI argument validation

```text
RED   8177e6938db36d492a8f2cd6e8ed912b9eab98dc
RED QA 38096506917 — FAILURE as intended
GREEN ea0175a9d3f912ad5fda7090db589c055d5b6188
QA    38096614606 — SUCCESS — PHP 8.3/8.4/8.5
```

Validated before DB/HTTP:

```text
--account-id = positive integer
--period = valid canonical YYYY-MM-01
--document-type = BILL | CREDIT_NOTE
--max-pages = required integer 1..20
```

## Cycle 4 — DB/account/token read-only runtime

```text
runtime RED        28f6850c2fd9bf1e2f0810a41739d1b0b8c5964c
runtime GREEN      6bfb916b57d7ee3fb74afe4253039f95d69dc8c3
CLI wiring RED     ac652c16cc3ad85d3fc465601fa9fa5fe30fe5f3
functional GREEN  da91d6c43c561d96fb6525b0ac66952e31f656a7
QA                38097632675 — SUCCESS — PHP 8.3/8.4/8.5
checkpoint        eb6f54e1a45b4b7357535be21968744329c9cdc9
```

Runtime contract:

```text
requested account must exist with token row
account status connected
stored token decrypted with existing TokenCipher
token valid >60 seconds
expired/near-expiry token -> BLOCKED
OAuth refresh forbidden
no account/token mutation
no secret output
CLI still stopped before MeliClient/transport/HTTP
```

## Cycle 5 — BillingC0Probe + existing MeliClient composition — GREEN / CLOSED

### RED

```text
RED SHA: 2402d36b0aa1b5ff9cea53aea1b197c18a1101cd
RED QA:  38098928796 — FAILURE as intended on PHP 8.3/8.4/8.5
```

The RED required one minimal composition boundary on the existing `BillingC0Probe` and failed only because `BillingC0Probe::forMeliClient()` did not exist.

### GREEN

```text
FUNCTIONAL GREEN SHA: 7fa54adb74eb7ea23f214f6025bae7268e46cc5d
TEST-HELPER FIX SHA:  ed571ae1fcd78f697fd91758cc2a3b273ee39847
QA:                   38099137542 — SUCCESS
PHP 8.3:              job 114351224091 — SUCCESS
PHP 8.4:              job 114351224036 — SUCCESS
PHP 8.5:              job 114351223995 — SUCCESS
PHPStan:              0 errors
PHPUnit:              240/240 tests, 1673 assertions
Memory:               22 MB
REAL_MELI_HTTP:       0
```

Implementation deliberately reuses only:

```text
BillingC0Probe
MeliClient::request()
existing billing.period.details operation
existing sanitized probe output
```

Added factory behavior:

```text
operation = billing.period.details
path period_key = requested canonical period
query document_type = BILL | CREDIT_NOTE
query limit = 1000
query from_id = current probe cursor
query sort_by = ID
query order_by = ASC
scope = company:{companyId}:account:{accountId}
```

Fake-transport proof covers:

```text
exact URL/query on first and next page
Bearer token reaches transport but never evidence output
cursor progresses 0 -> returned last_id
empty results terminates probe
large numeric result ID is not exposed in evidence
api_usage_daily remains untouched
meli_cooldowns remains untouched
no Billing persistence
no OAuth refresh
no remote write
```

### Cycle 5 noise audit

Base:

```text
eb6f54e1a45b4b7357535be21968744329c9cdc9
```

Verified SHA:

```text
ed571ae1fcd78f697fd91758cc2a3b273ee39847
```

Exactly two files changed:

```text
app/Modules/Billing/C0/BillingC0Probe.php             +24 / -0
tests/Integration/BillingC0MeliClientProbeTest.php   added
```

No schema, migration, Work, queue, Sales, OAuth, Billing persistence, CLI behavior, or real HTTP change.

One intermediate GREEN QA failed only because the fake transport incorrectly called a PHPUnit assertion as an instance method. Production code was unchanged; the helper was corrected in `ed571ae1...`, after which the full matrix passed.

Pre-existing lint noise remains intentionally deferred: `bin/billing-c0-smoke.php` has ineffective global `use DateTimeImmutable`, `DateTimeZone`, and `Throwable` imports. Do not mix that cleanup into functional Billing logic.

---

# 5. C0 SECURITY / KISS CONTRACT

The final C0 smoke must remain deliberately small:

```text
one CLI entrypoint
reuse BillingC0Runtime
reuse BillingC0Probe
reuse existing MeliClient billing.period.details operation
no Billing persistence
no new Billing tables
no Billing Work/handler yet
no refresh-token POST
no remote write operation
no ApiUsageRecorder persistence
no cooldown persistence
sanitized JSON evidence only
```

Never output/store:

```text
access token
refresh token
MELI_CLIENT_SECRET
full raw Billing payload
buyer/seller PII
unneeded order/item metadata
```

---

# 6. NEXT MICROBLOCK — CYCLE 6 FROZEN, NOT STARTED

Next:

```text
Billing C0 Cycle 6 — wire the guarded CLI to existing CurlMeliTransport + MeliClient + BillingC0Probe
```

Goal:

> Complete the CLI composition so that, only after all existing production/opt-in/account/token/argument guards pass, the CLI can run the existing sanitized probe through the existing read-only MeliClient operation.

Required discipline:

```text
RED first with controlled/fake boundary
reuse existing CurlMeliTransport / RemoteHostPolicy / MeliClient
reuse BillingC0Runtime and BillingC0Probe::forMeliClient()
no OAuth refresh
no ApiUsageRecorder persistence
no cooldown persistence
no Billing persistence
no new service/engine/handler
sanitized JSON only
PHP 8.3/8.4/8.5 GREEN
noise audit
checkpoint
STOP
```

Important separation:

```text
Cycle 6 may wire the real transport class into the CLI,
but normal CI remains REAL_MELI_HTTP=0 and must not contact Mercado Libre.
Do NOT execute a real authenticated seller-MCO request merely as part of Cycle 6 coding/testing.
```

The later **real authenticated C0 execution** remains a separate explicitly authorized runtime action. Billing Task2 remains blocked until sanitized real evidence is captured and accepted.

---

# 7. GATES

```text
G1 REMOTE_TRUTH: PASS for implemented boundary
G2 WORK_SAFETY: PASS
G3 RATE_SAFETY: PASS for current Sales
G4 SALES_AUDIT_TRUTH: PASS / CLOSED
G5 BILLING_CURSOR_TRUTH: IN PROGRESS — C0 TOOLING CYCLES 1-5 GREEN; REAL AUTHENTICATED EVIDENCE STILL MISSING
G6 FINANCIAL_NO_DOUBLE_COUNT: NOT STARTED
G7 WRITE_FAIL_CLOSED: PASS
G8 HOSTING_REALITY: NOT CERTIFIED
```

External gates:

```text
Issue #3 Hostinger/runtime/main protection
Issue #5 dedicated Mercado Libre ERP2 app/OAuth reality + authenticated real C0 runtime
```

Future order:

```text
Cycle 6 guarded CLI composition
-> explicit authorization for real authenticated C0 seller-MCO execution
-> sanitize + audit evidence
-> accept/reject C0
-> only then Billing Task2
-> sale_fee alignment before Financial
-> Financial no-double-count
```

---

# 8. STOP

Cycle 5 is closed GREEN with canonical QA evidence.

**STOP HERE.**

No Cycle 6 started.  
No Billing Task2 started.  
No real Mercado Libre HTTP executed by C0 tooling.  
No OAuth refresh.  
No merge.  
No deploy.  
No production DB/OAuth mutation.  
No remote Mercado Libre write.  
Remote writes remain OFF.
