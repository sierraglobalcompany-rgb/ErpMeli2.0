# CURRENT CHECKPOINT — ERP MELI 2.0

**STOP AFTER THIS CHECKPOINT**

**Date:** 2026-10-11

**Repo:** `sierraglobalcompany-rgb/ErpMeli2.0`

**Branch:** `impl/v3-b-sales-audit-20261010`

**HEAD verified before MB-01 / implementation and QA SHA:** `ea86ba2b8e9d3cae8b090dcfc128570f73a334d5`

**Working tree before MB-01 and after QA:** CLEAN

**Last closed block / CURRENT:** MB-01 CLOSED — cooldown concurrency, monotonic deadline and safe expiration clear

**NEXT:** MB-02 / API-SAFETY-2

**REAL_MELI_HTTP:** `0`

**Remote Mercado Libre writes:** OFF

This docs-only commit follows the implementation/QA SHA above. Its own SHA is discoverable from Git; it does not change runtime, schema, tests or dependencies. Verify actual HEAD and any later delta before continuing. No fetch, pull, push, merge, deploy, real OAuth or Mercado Libre HTTP was performed.

## 1. Local verification and QA

The expected branch is active. Git verifies this ancestry:

```text
997c22f5dcd2c054030ea0f88498a9052845ee25
-> ea86ba2b8e9d3cae8b090dcfc128570f73a334d5
```

The only commit between those SHAs is `ea86ba2b`, message `fix(meli): harden cooldown concurrency`. It changes `app/Integrations/MercadoLibre/Client/MeliClient.php`, `app/Integrations/MercadoLibre/Client/MeliCooldownRepository.php` and `tests/Integration/MeliRateLimitTest.php`.

Fresh QA ran on the exact SHA above; HEAD remained unchanged and working tree remained clean:

| Check | Result |
|---|---|
| `composer validate --strict` | PASS, exit 0 |
| `php vendor/bin/phpstan analyse --no-progress` | PASS, 0 errors, exit 0 |
| `php vendor/bin/phpunit` | PASS, 308 tests / 2276 assertions / 1 skipped / 0 failures / 0 errors, exit 0 |
| Runtime | PHP 8.5.8; PHPUnit 12.5.38 |

PHPUnit used the existing test harness and a unique private local MariaDB database, removed after the run. Injected transports/fakes and the local/test HTTP host guard prevent live Mercado Libre access; REAL_MELI_HTTP stayed 0. The one skipped test concerns unavailable symlink creation on this platform; that filesystem case is not newly certified.

## 2. Current implementation and Safety boundary

One PHP/Slim application, one MariaDB, one Work table/runner and one MeliClient. Existing OAuth/cooldown/usage, Sales, Sales Audit, Debug and period-first Billing are reused. No domain queue, scheduler or additional runner.

| Block | Current status |
|---|---|
| F6A-LOCAL-0 / SCHEMA | CLOSED locally: period-first schema and curated context contract. |
| F6A-LOCAL-1 | CLOSED locally: exact Billing detail normalizer and source timezone validation. |
| F6A-LOCAL-2 | CLOSED locally: one Work/page with details, cursor, continuation and completion atomic. |
| F6A-LOCAL-3A | CLOSED locally at `e1231e94`: remote lifecycle tests; 206 defers without canonical writes. |
| F6A-LOCAL-3B | CLOSED locally at `6dbb6087`: post-decoder cursor contract durability. |
| R0 | CLOSED locally at `997c22f5`: local state and roadmap reconciliation. |
| MB-01 / API-SAFETY-1 | CLOSED locally at `ea86ba2b`: atomic upsert with saturated counter, GREATEST monotonic deadline, effective retryAt reload, safe clear only for expired cooldowns, and cleanup deferred until post-2xx/JSON validation. |
| MB-02 / API-SAFETY-2 | PENDING: strict Retry-After parsing, HTTP-date support, integer overflow bounds, and effective propagation. |
| MB-03 / API-SAFETY-3 | PENDING: OAuth uncertain POST, busy lock, and SQL versus business error classification. |
| F6A-LOCAL-4 | PENDING: minimal application Work routing; no HandlerRegistry, bus, domain queue or new runner. |
| Financial | NOT STARTED / NOT READY; no ledger or formulas introduced. |

Verified local MB-01 properties:

- Atomic `register429` upsert increments `consecutive_429` up to `65535` without a pre-select race condition.
- Monotonic deadline via `GREATEST(blocked_until, candidate)` guarantees that a shorter 429 cannot shrink an existing longer durable cooldown.
- After upsert, `register429` reloads the effective `blocked_until` from database and returns it.
- `MeliClient` defers cooldown clearing until after response status is validated as 2xx and body is validated as valid JSON; non-2xx (e.g. 403) and invalid JSON do not clear cooldown.
- `MeliCooldownRepository::clear` deletes only expired rows (`blocked_until <= UTC_TIMESTAMP(6)`), preventing late completions from wiping out newer active cooldowns.

Evidence: `MeliCooldownRepository::register429/clear`, `MeliClient::request`, and focused tests in `tests/Integration/MeliRateLimitTest.php`.

## 3. Current gates and priorities

| Gate | Current verdict / limit |
|---|---|
| G1 REMOTE_TRUTH | Local implemented contracts evidenced; external READ-only certification remains F14. |
| G2 WORK_SAFETY | Local supported-producer coverage GREEN; conditional malformed scalar payload robustness remains open. |
| G3 RATE_SAFETY | OPEN / NOT FULL PASS. MB-01 resolved cooldown races, monotonic deadlines, and premature clearing; MB-02 (Retry-After parser/overflow) and MB-03 (OAuth recovery) remain open. |
| G4 SALES_AUDIT_TRUTH | CLOSED within local confirmation/repair coverage. |
| G5 BILLING_CURSOR_TRUTH | PASS LOCAL THROUGH F6A-LOCAL-3B. Routing remains pending (F6A-LOCAL-4). |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED / NOT READY. |
| G7 WRITE_FAIL_CLOSED | Local coverage GREEN; remote writes OFF. |
| G8 HOSTING_REALITY | DEFERRED F14 / NOT CERTIFIED. |

## 4. Document responsibilities and external boundaries

```text
AGENTS.md = engineering law / KISS / noise reduction
README.md = product / permitted architecture / master roadmap / dependencies / guardrails
CURRENT_CHECKPOINT.md = current state / exact implementation-QA SHA / gates / next block
ERP2_AUTHORITY.md = durable architecture and domain contracts
Git = superseded history
```

Current truth: local code/schema > same-SHA tests/QA > same-SHA evidence > checkpoint > durable authority > README/supporting documents > handoffs/audits > remote visibility > inference.

README and ERP2_AUTHORITY are unchanged in MB-01.

## 5. NEXT and STOP

```text
CURRENT = MB-01 CLOSED
NEXT = MB-02 / API-SAFETY-2
STOP
```

MB-02 addresses strict Retry-After parsing, HTTP-date support, and integer overflow bounds. Do not start routing, Financial, sale_fee changes, or F14 here. No real Mercado Libre HTTP/OAuth, production mutation, remote writes, push, merge or deploy.
