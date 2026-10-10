# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED — STOP AFTER THIS CHECKPOINT**  
**Date:** 2026-10-10  
**Repo:** `sierraglobalcompany-rgb/ErpMeli2.0`  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Previous checkpoint:** `ce78c2e31467c5da563c8962658d5b048d931f3c`  
**Functional HEAD before this docs checkpoint:** `ea0175a9d3f912ad5fda7090db589c055d5b6188`  
**Latest verified QA:** `38096614606` — SUCCESS  
**Remote Mercado Libre writes:** OFF  
**Normal `REAL_MELI_HTTP`:** `0`  
**No merge / no deploy / no production DB or OAuth mutation performed.**

> Authority order: active code/schema at branch HEAD → tests/CI at relevant SHA → this checkpoint → `docs/ERP2_AUTHORITY.md` → `AGENTS.md` → recent explicit user decisions → historical plans/issues/PRs.

---

# 1. EXECUTION LAW

```text
1 microblock at a time
RED -> confirm intended failure -> minimal GREEN -> full QA -> noise audit -> checkpoint -> STOP
DELETE -> SIMPLIFY -> REUSE -> MERGE -> EXTEND -> ADD
Correct -> Simple -> Stable -> Maintainable -> Efficient -> Scalable
```

Supported PHP: `8.3 / 8.4 / 8.5`; Composer PHP contract: `>=8.3 <8.6`.

No merge, deploy, destructive cleanup, production DB/OAuth mutation, or remote Mercado Libre write without explicit authorization.

---

# 2. PROJECT STATE

`G4 SALES_AUDIT_TRUTH` remains **PASS / CLOSED**.

Certified Sales contract remains unchanged:

```text
seller Orders Search
monthly membership = order.date_created
MCO timezone = America/Bogota
historical audit = closed months only
outside supported horizon -> unavailable before OAuth/HTTP
short non-terminal page -> fail closed
Capture A -> local compare -> bounded repair -> verify -> Capture B -> A/B compare -> baseline lifecycle
```

Exact-order 404 remains closed:

```text
GET /orders/{id} -> 404
=> Work failed
=> meli_order_not_found
=> no retry/defer
=> no order persistence
```

Architecture remains deliberately small; no second queue, scheduler, generic retry/repair/recovery engine, state machine, extra audit state/table, or generic lock/transaction framework.

---

# 3. BILLING C0 — PURPOSE / HARD GATE

Billing Task2 must **not** start until C0 obtains sanitized real seller-MCO evidence for:

```text
GET /billing/integration/periods/key/{period_key}/group/ML/details
```

C0 must establish from real responses:

```text
first-page shape
last_id shape
sequential cursor progress
terminal signal
HTTP 206 behavior if actually observed
repeated/non-progress cursor behavior if actually observed
```

Do not synthesize 206/non-progress evidence. Unobserved cases remain unproven.

Billing remains fiscal/financial reconciliation and period-first; it is not the operational Sales truth source.

---

# 4. BILLING C0 TOOLING — COMPLETED CYCLES

## Cycle 1 — sanitized cursor probe — GREEN

RED:

```text
793853b0f5641e78ebfef7526e404781467d7417
```

GREEN:

```text
df0637bcb87b1477833dd10618b1ec869cbfc206
RUN 38096002800 — SUCCESS
PHP 8.3 / 8.4 / 8.5 — SUCCESS
```

Added:

```text
app/Modules/Billing/C0/BillingC0Probe.php
tests/Unit/BillingC0ProbeTest.php
```

Probe contract:

```text
start cursor = "0"
follow returned last_id sequentially
stop on empty results
stop immediately on HTTP 206
stop on non-progress cursor
bounded max page count
output only structural/sanitized evidence
never expose result rows, email, access token, or arbitrary payload fields
```

## Cycle 2 — CLI real-HTTP safety guards — GREEN

RED:

```text
92ccb33db3fb7cc0fe2f293071b482403b66e07a
```

GREEN:

```text
c705f239c7c71d7cc191f412c04a53e92bd29baf
RUN 38096234324 — SUCCESS
PHP 8.3 / 8.4 / 8.5 — SUCCESS
```

Added:

```text
bin/billing-c0-smoke.php
tests/Integration/BillingC0CliGuardTest.php
```

Guards execute before DB/HTTP:

```text
APP_ENV == production
BILLING_C0_REAL_HTTP == 1
```

## Cycle 3 — CLI argument validation — GREEN / CLOSED

RED commit:

```text
8177e6938db36d492a8f2cd6e8ed912b9eab98dc
```

RED QA:

```text
RUN 38096506917 — FAILURE as intended
PHP 8.3 / 8.4 / 8.5 — FAILURE
```

RED reason was confirmed against the exact pre-GREEN CLI: after both real-HTTP guards passed, every invalid argument case still reached the generic terminal message:

```text
Billing C0 runtime is not configured yet.
```

The missing behavior was therefore precisely argument validation before DB/HTTP, not a fixture/schema/OAuth failure.

Argument contract frozen and tested:

```text
--account-id = positive integer
--period = canonical valid YYYY-MM-01
--document-type = BILL | CREDIT_NOTE
--max-pages = required integer 1..20
```

GREEN commit:

```text
ea0175a9d3f912ad5fda7090db589c055d5b6188
```

GREEN QA:

```text
RUN 38096614606 — SUCCESS
PHP 8.3 — SUCCESS (job 114343703595)
PHP 8.4 — SUCCESS (job 114343703669)
PHP 8.5 — SUCCESS (job 114343703703)
APP_ENV=test
REAL_MELI_HTTP=0
composer qa — SUCCESS
```

Final order inside CLI:

```text
1. APP_ENV production guard
2. BILLING_C0_REAL_HTTP explicit opt-in guard
3. account-id validation
4. period validation
5. document-type validation
6. max-pages validation
7. current placeholder: runtime is not configured yet
```

No DB access and no Mercado Libre HTTP are performed by the CLI yet.

Noise audit from `ce78c2e...` to functional GREEN `ea0175a9...`:

```text
2 commits ahead
only 2 files changed
bin/billing-c0-smoke.php                 +35 / -0
tests/Integration/BillingC0CliGuardTest.php +73 / -4
no schema/config/Billing persistence changes
```

---

# 5. SECURITY / KISS DECISIONS FROZEN FOR C0

The eventual real C0 runner remains read-only and minimal:

```text
one CLI entrypoint
reuse BillingC0Probe
reuse existing MeliClient operation billing.period.details
reuse existing TokenCipher / DB connection patterns only as necessary
no Billing persistence
no new Billing tables
no Billing handler/work type yet
no refresh-token POST in the smoke
no remote write operation
no ApiUsageRecorder persistence
no cooldown persistence
sanitized JSON evidence only
```

Token rule:

```text
read/decrypt an already stored access token only if still valid
if expired -> BLOCKED / stop
never auto-refresh OAuth in C0
```

Never output/store:

```text
access token
refresh token
Client Secret
full raw Billing payload
buyer/seller PII
unneeded order/item metadata
```

---

# 6. EXACT NEXT MICROBLOCK — CYCLE 4 FROZEN, NOT STARTED

Next:

```text
Billing C0 Cycle 4 — DB/account/token read-only runtime wiring
```

Scope must remain smaller than authenticated HTTP execution.

Required sequence when resumed:

1. Verify branch/checkpoint HEAD and functional ancestor `ea0175a9...`.
2. Inspect existing connection, Mercado Libre account schema/repository and `TokenCipher` patterns before designing anything.
3. Define one minimal RED for the account/token read-only runtime boundary.
4. Wire only what is necessary to:
   - connect using existing DB configuration,
   - locate exactly the requested Mercado Libre account,
   - reject missing/disconnected/invalid account state,
   - decrypt an existing access token,
   - reject an expired token,
   - never refresh OAuth,
   - never print the token,
   - perform no Mercado Libre HTTP yet.
5. GREEN + full PHP 8.3/8.4/8.5 QA.
6. Noise audit + checkpoint + STOP.

Do **not** combine Cycle 4 with the real Billing HTTP call.

Authenticated `billing.period.details` execution belongs to a later isolated cycle only after Cycle 4 is GREEN.

---

# 7. GATES

```text
G1 REMOTE_TRUTH: PASS for implemented boundary
G2 WORK_SAFETY: PASS
G3 RATE_SAFETY: PASS for current Sales
G4 SALES_AUDIT_TRUTH: PASS / CLOSED
G5 BILLING_CURSOR_TRUTH: IN PROGRESS — C0 TOOLING CYCLES 1-3 GREEN; REAL AUTHENTICATED EVIDENCE STILL MISSING
G6 FINANCIAL_NO_DOUBLE_COUNT: NOT STARTED
G7 WRITE_FAIL_CLOSED: PASS
G8 HOSTING_REALITY: NOT CERTIFIED
```

External gates remain:

```text
Issue #3 Hostinger/runtime/main protection
Issue #5 dedicated Mercado Libre ERP2 app/OAuth reality + authenticated C0 runtime access
```

---

# 8. FROZEN FUTURE ORDER

```text
Cycle 4 DB/account/token read-only wiring
-> later isolated authenticated billing.period.details smoke cycle
-> sanitize real evidence
-> accept/reject C0
-> only then Billing Task2
-> sale_fee alignment before Financial
-> Financial no-double-count
```

Separate hardening stays outside this block:

```text
Sales detail multi-account scope
webhook seller multi-company scope
webhook timestamp timezone
webhook_events retention
MariaDB session UTC
Slim diagnostic cleanup
```

---

# 9. STOP

Cycle 3 is closed GREEN with canonical QA evidence.

**STOP HERE.**

No Cycle 4 started.  
No Billing Task2 started.  
No real Mercado Libre request executed by C0 tooling yet.  
No merge.  
No deploy.  
No production DB/OAuth mutation.  
No remote Mercado Libre write.  
Remote writes remain OFF.
