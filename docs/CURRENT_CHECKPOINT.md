# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED — STOP AFTER THIS CHECKPOINT**  
**Date:** 2026-10-10  
**Repo:** `sierraglobalcompany-rgb/ErpMeli2.0`  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Functional HEAD before this docs checkpoint:** `c705f239c7c71d7cc191f412c04a53e92bd29baf`  
**Latest verified QA:** `38096234324` — SUCCESS  
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

# 2. PROJECT STATE BEFORE BILLING C0 TOOLING

`G4 SALES_AUDIT_TRUTH` remains **PASS / CLOSED**.

Certified source/lifecycle contract remains unchanged:

```text
seller Orders Search
monthly membership = order.date_created
MCO timezone = America/Bogota
historical audit = closed months only
outside supported historical horizon -> unavailable before OAuth/HTTP
short non-terminal page -> fail closed
Capture A -> local compare -> bounded repair -> verify -> Capture B -> A/B compare -> baseline lifecycle
```

Exact-order 404 behavior remains closed:

```text
GET /orders/{id} -> 404
=> Work failed
=> meli_order_not_found
=> no retry/defer
=> no order persistence
```

Architecture remains deliberately small; no second queue, scheduler, generic retry/repair/recovery engine, state machine, extra audit state/table, or generic lock/transaction framework was added.

---

# 3. BILLING C0 — PURPOSE

Billing Task2 must **not** start until C0 obtains sanitized real seller-MCO evidence for:

```text
GET /billing/integration/periods/key/{period_key}/group/ML/details
```

C0 needs to establish from real responses:

```text
first-page shape
last_id shape
sequential cursor progress
terminal signal
HTTP 206 behavior if actually observed
repeated/non-progress cursor behavior if actually observed
```

Do not synthesize 206/non-progress evidence. Unobserved cases stay unproven.

Billing remains fiscal/financial reconciliation and period-first; it is not the operational Sales truth source.

---

# 4. NEW C0 TOOLING COMPLETED IN THIS SESSION

The previous checkpoint said C0 was externally blocked because this ChatGPT harness had no authenticated seller runtime. To make the authorized smoke executable later from the real ERP2 environment, a deliberately small read-only tool boundary was started.

## Cycle 1 — sanitized cursor probe

RED commit:

```text
793853b0f5641e78ebfef7526e404781467d7417
```

RED was confirmed for the intended reason only: `BillingC0Probe` did not yet exist; PHPStan remained clean.

GREEN commit:

```text
df0637bcb87b1477833dd10618b1ec869cbfc206
```

Added:

```text
app/Modules/Billing/C0/BillingC0Probe.php
tests/Unit/BillingC0ProbeTest.php
```

Probe contract now covers:

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

GREEN QA:

```text
RUN 38096002800 — SUCCESS
PHP 8.3 / 8.4 / 8.5 — SUCCESS
```

## Cycle 2 — CLI real-HTTP safety guards

RED commit:

```text
92ccb33db3fb7cc0fe2f293071b482403b66e07a
```

RED was confirmed for the intended reason: `bin/billing-c0-smoke.php` did not yet exist.

GREEN commit:

```text
c705f239c7c71d7cc191f412c04a53e92bd29baf
```

Added:

```text
bin/billing-c0-smoke.php
tests/Integration/BillingC0CliGuardTest.php
```

Current CLI safety behavior:

```text
APP_ENV must equal production
BILLING_C0_REAL_HTTP must equal 1
otherwise exit before DB/HTTP
```

The current CLI intentionally ends with:

```text
Billing C0 runtime is not configured yet.
```

Therefore **the CLI is not yet a complete executable real C0 smoke**. That is intentional at this checkpoint.

Latest verified QA:

```text
SHA c705f239c7c71d7cc191f412c04a53e92bd29baf
RUN 38096234324 — SUCCESS
PHP 8.3 / 8.4 / 8.5 — SUCCESS
```

Normal CI still runs with test environment / no real Mercado Libre HTTP.

---

# 5. SECURITY / KISS DECISIONS FROZEN FOR C0

The intended real C0 runner must remain read-only and minimal:

```text
one CLI entrypoint
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

# 6. EXACT PAUSE POINT — CYCLE 3 NOT STARTED

The next planned microblock was:

```text
Cycle 3 — validate CLI arguments before DB/HTTP
```

Planned argument contract:

```text
--account-id = positive integer
--period = canonical YYYY-MM-01
--document-type = BILL | CREDIT_NOTE
--max-pages = 1..20
```

A first attempt to update `BillingC0CliGuardTest.php` hit a GitHub content lease mismatch (`409`). The file was refreshed afterward.

**Important:** that failed update created no commit and no branch mutation. No RED for Cycle 3 exists yet.

So the exact resume point is:

1. Verify branch and checkpoint HEAD.
2. Confirm `c705f239...` is the functional ancestor and QA `38096234324` is GREEN.
3. Re-open `tests/Integration/BillingC0CliGuardTest.php` with its current blob SHA.
4. Add **only** Cycle 3 argument-validation RED.
5. Run QA and confirm the RED is solely the missing argument validation.
6. Implement minimal argument validation in `bin/billing-c0-smoke.php` before any DB/HTTP access.
7. Full PHP 8.3/8.4/8.5 QA.
8. Only after GREEN decide the next microblock for DB/account/token read-only runtime wiring.

Do **not** jump directly to authenticated HTTP or Billing Task2.

---

# 7. GATES

```text
G1 REMOTE_TRUTH: PASS for implemented boundary
G2 WORK_SAFETY: PASS
G3 RATE_SAFETY: PASS for current Sales
G4 SALES_AUDIT_TRUTH: PASS / CLOSED
G5 BILLING_CURSOR_TRUTH: IN PROGRESS — C0 TOOLING PARTIAL; REAL AUTHENTICATED EVIDENCE STILL MISSING
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
finish C0 CLI safely
-> execute C0 in authenticated ERP2 MCO runtime
-> sanitize evidence
-> accept/reject C0
-> only then Billing Task2
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

# 9. STOP

Checkpoint requested explicitly by user on 2026-10-10.

**STOP HERE.**

No Cycle 3 commit exists.  
No Billing Task2 started.  
No real Mercado Libre request executed by this C0 tooling yet.  
No merge.  
No deploy.  
No production DB/OAuth mutation.  
No remote Mercado Libre write.  
Remote writes remain OFF.
