# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED — STOP AFTER THIS CHECKPOINT**  
**Date:** 2026-10-10  
**Repo:** `sierraglobalcompany-rgb/ErpMeli2.0`  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Previous checkpoint:** `6e7c3d01dd73c54802797be7685ee0b7f0999557`  
**Functional GREEN before this docs checkpoint:** `da91d6c43c561d96fb6525b0ac66952e31f656a7`  
**Latest verified functional QA:** `38097632675` — SUCCESS  
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

Stable Sales invariants:

```text
seller Orders Search
monthly membership = order.date_created
MCO timezone = America/Bogota
historical audit = closed months only
outside supported horizon -> unavailable before OAuth/HTTP
short non-terminal page -> fail closed
Capture A -> local compare -> bounded repair -> verify -> Capture B -> A/B compare -> baseline lifecycle
exact GET /orders/{id} 404 -> terminal meli_order_not_found, no retry/defer/persistence
```

Architecture remains KISS: one PHP/Slim app, one MariaDB, one Work table/runner/client; no speculative queue/service/state-machine proliferation.

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

# 4. BILLING C0 TOOLING — GREEN CYCLES

## Cycle 1 — sanitized cursor probe

```text
RED   793853b0f5641e78ebfef7526e404781467d7417
GREEN df0637bcb87b1477833dd10618b1ec869cbfc206
QA    38096002800 — SUCCESS — PHP 8.3/8.4/8.5
```

Files:

```text
app/Modules/Billing/C0/BillingC0Probe.php
tests/Unit/BillingC0ProbeTest.php
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
checkpoint 6e7c3d01dd73c54802797be7685ee0b7f0999557
```

Validated before DB/HTTP:

```text
--account-id = positive integer
--period = valid canonical YYYY-MM-01
--document-type = BILL | CREDIT_NOTE
--max-pages = required integer 1..20
```

## Cycle 4 — DB/account/token read-only runtime wiring — GREEN / CLOSED

### RED A — isolated runtime contract

```text
RED SHA: 28f6850c2fd9bf1e2f0810a41739d1b0b8c5964c
RED QA:  38097322811 — FAILURE as intended on PHP 8.3/8.4/8.5
```

Only the new test was introduced; production had no `BillingC0Runtime`, so the missing runtime boundary was isolated before implementation.

Runtime contract frozen:

```text
requested account must exist with token row
account status must be connected
stored access token is decrypted with existing TokenCipher
stored token must remain valid for >60 seconds
expired/near-expiry token -> BLOCKED
OAuth refresh is forbidden
no account/token mutation
no secret output
```

### GREEN A — minimal read-only resolver

```text
GREEN SHA: 6bfb916b57d7ee3fb74afe4253039f95d69dc8c3
QA:        38097412007 — SUCCESS
PHP 8.3: job 114346081593 — SUCCESS
PHP 8.4: job 114346081538 — SUCCESS
PHP 8.5: job 114346081649 — SUCCESS
```

Added:

```text
app/Modules/Billing/C0/BillingC0Runtime.php
tests/Integration/BillingC0RuntimeTest.php
```

Implementation reuses:

```text
PDO existing connection semantics
meli_accounts + meli_tokens existing schema
TokenCipher
60-second validity margin matching existing OAuth behavior
```

It does **not** call `OAuthRefreshService`, because that service may perform a refresh POST when the token is stale.

### RED B — CLI runtime wiring

```text
RED SHA: ac652c16cc3ad85d3fc465601fa9fa5fe30fe5f3
RED QA:  38097515635 — FAILURE as intended on PHP 8.3/8.4/8.5
```

The new CLI integration test expected a valid stored token to reach a safe readiness boundary, while the pre-GREEN CLI still terminated at:

```text
Billing C0 runtime is not configured yet.
```

### FINAL GREEN — CLI DB/token runtime wired, still no HTTP

```text
FUNCTIONAL GREEN SHA: da91d6c43c561d96fb6525b0ac66952e31f656a7
QA:                   38097632675 — SUCCESS
PHP 8.3: job 114346744521 — SUCCESS
PHP 8.4: job 114346744227 — SUCCESS
PHP 8.5: job 114346744413 — SUCCESS
```

Final CLI sequence:

```text
1. APP_ENV production guard
2. BILLING_C0_REAL_HTTP explicit opt-in guard
3. validate account-id
4. validate period
5. validate document-type
6. validate max-pages
7. load existing app config / DB connection
8. resolve exactly requested account + encrypted token read-only
9. require connected account
10. require stored access token valid >60 seconds
11. decrypt access token with TokenCipher
12. STOP before MeliClient/transport/HTTP
```

Current safe terminal message after a valid runtime resolution:

```text
Billing C0 account/token ready; HTTP smoke is not configured yet.
```

The CLI intentionally exits non-zero at that boundary because C0 HTTP execution is not wired yet.

Security verified by tests:

```text
access token not printed
refresh token not printed
account status unchanged
access_token_cipher unchanged
refresh_token_cipher unchanged
expires_at unchanged
refresh_version unchanged
no OAuth refresh
no Mercado Libre HTTP
```

### Cycle 4 noise audit

Base:

```text
6e7c3d01dd73c54802797be7685ee0b7f0999557
```

Functional GREEN:

```text
da91d6c43c561d96fb6525b0ac66952e31f656a7
```

Exactly four functional files changed:

```text
app/Modules/Billing/C0/BillingC0Runtime.php          added
bin/billing-c0-smoke.php                            modified
tests/Integration/BillingC0RuntimeTest.php          added
tests/Integration/BillingC0CliRuntimeTest.php       added
```

No schema, migration, MeliClient, Billing persistence, Work, queue, config, OAuth refresh service, or Sales changes.

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

# 6. NEXT MICROBLOCK — CYCLE 5 FROZEN, NOT STARTED

Next:

```text
Billing C0 Cycle 5 — read-only MeliClient + BillingC0Probe wiring
```

Goal:

> Wire the existing valid account/token runtime into the existing `billing.period.details` MeliClient operation and `BillingC0Probe`, while preserving sanitization and bounded sequential cursor behavior.

Required implementation discipline:

```text
RED with fake transport first
prove exact operation/query contract
prove no token/payload leak
prove no ApiUsageRecorder persistence
prove no cooldown persistence
prove no OAuth refresh
prove no remote write operation
prove max-pages bound
prove 206/non-progress stop behavior through the composed CLI path
GREEN + PHP 8.3/8.4/8.5 QA
noise audit
checkpoint
STOP
```

Important separation:

```text
Cycle 5 implementation/tests use fake transport only.
Do NOT execute the real seller-MCO HTTP smoke merely as part of coding Cycle 5.
```

The later **real C0 execution** remains a separate runtime action after Cycle 5 GREEN, using an authenticated ERP2 seller environment and emitting only sanitized evidence.

Billing Task2 remains blocked until that real evidence is accepted.

---

# 7. GATES

```text
G1 REMOTE_TRUTH: PASS for implemented boundary
G2 WORK_SAFETY: PASS
G3 RATE_SAFETY: PASS for current Sales
G4 SALES_AUDIT_TRUTH: PASS / CLOSED
G5 BILLING_CURSOR_TRUTH: IN PROGRESS — C0 TOOLING CYCLES 1-4 GREEN; REAL AUTHENTICATED EVIDENCE STILL MISSING
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
Cycle 5 fake-tested MeliClient + probe composition
-> real authenticated C0 seller-MCO execution
-> sanitize + audit evidence
-> accept/reject C0
-> only then Billing Task2
-> sale_fee alignment before Financial
-> Financial no-double-count
```

---

# 8. STOP

Cycle 4 is closed GREEN with canonical QA evidence.

**STOP HERE.**

No Cycle 5 started.  
No Billing Task2 started.  
No real Mercado Libre HTTP executed by C0 tooling.  
No OAuth refresh.  
No merge.  
No deploy.  
No production DB/OAuth mutation.  
No remote Mercado Libre write.  
Remote writes remain OFF.
