# CURRENT CHECKPOINT — ERP MELI 2.0

**STOP AFTER THIS CHECKPOINT**

**Date:** 2026-10-11

**Repo:** `sierraglobalcompany-rgb/ErpMeli2.0`

**Branch:** `impl/v3-b-sales-audit-20261010`

**HEAD verified before R0 / implementation and QA SHA:** `6dbb6087d13d28b87c95a84959424e362d1e1385`

**Working tree before R0 and after QA:** CLEAN

**Last closed block / CURRENT:** R0 CLOSED — local reconciliation and documentation only

**NEXT:** MB-01 / API-SAFETY-1

**REAL_MELI_HTTP:** `0`

**Remote Mercado Libre writes:** OFF

This docs-only commit follows the implementation/QA SHA above. Its own SHA is discoverable from Git; it does not change runtime, schema, tests or dependencies. Verify actual HEAD and any later delta before continuing. No fetch, pull, push, merge, deploy, real OAuth or Mercado Libre HTTP was performed.

## 1. Local verification and QA

The expected branch is active. Both commits exist locally, and Git verifies this ancestry:

```text
e1231e94cada2cd7054c449dfaf3a01e1576d46b
-> 6dbb6087d13d28b87c95a84959424e362d1e1385
```

The only commit between those SHAs is `6dbb6087`, message `fix(billing): harden period page durability`. It changes `app/Modules/Billing/BillingPeriodSyncHandler.php` and `tests/Integration/BillingPeriodSyncHandlerTest.php`. The corrected 3B/R1 post-decoder cursor contract is present in that commit; there is no separate intervening commit named R1. Its presence was verified from code and tests, not inferred from an audit or GitHub visibility.

Fresh QA ran before documentation edits, on the exact SHA above; HEAD remained unchanged and working tree remained clean:

| Check | Result |
|---|---|
| `composer validate --strict` | PASS, exit 0 |
| `php vendor/bin/phpstan analyse --no-progress` | PASS, 0 errors, exit 0 |
| `php vendor/bin/phpunit` | PASS, 295 tests / 2172 assertions / 1 skipped / 0 failures / 0 errors, exit 0 |
| Runtime | PHP 8.5.8; PHPUnit 12.5.38 |

PHPUnit used the existing test harness and a unique private local MariaDB database, removed after the run. Its fixture reset did not touch the shared test DB or production. Injected transports/fakes and the local/test HTTP host guard prevent live Mercado Libre access; REAL_MELI_HTTP stayed 0. The one skipped test concerns unavailable symlink creation on this platform; that filesystem case is not newly certified.

Local commits need not exist on GitHub to be valid. No remote reference was refreshed or published. Publication requires separate authorization.

## 2. Current implementation and Billing boundary

One PHP/Slim application, one MariaDB, one Work table/runner and one MeliClient. Existing OAuth/cooldown/usage, Sales, Sales Audit, Debug and period-first Billing are reused. No domain queue, scheduler or additional runner.

Closed foundations include local-first governance, CLI QA portability, DB session UTC, explicit webhook timezone/calendar policies, C0 tooling and CLEAN-0 through CLEAN-3. Sales Audit confirmation handoff and completed-event webhook replay corrections remain in code and in the green suite. Git preserves their history; they are not pending blocks.

| Block | Current status |
|---|---|
| F6A-LOCAL-0 / SCHEMA | CLOSED locally: period-first schema and curated context contract. |
| F6A-LOCAL-1 | CLOSED locally: exact Billing detail normalizer and source timezone validation. |
| F6A-LOCAL-2 | CLOSED locally: one Work/page with details, cursor, continuation and completion atomic. |
| F6A-LOCAL-3A | CLOSED locally at `e1231e94`: remote lifecycle tests; 206 defers without canonical writes. Shared core risks below remain open. |
| F6A-LOCAL-3B / reconciled R1 contract | CLOSED locally at `6dbb6087`, backed by fresh QA and focused durability tests. |
| F6A-LOCAL-4 | PENDING: minimal application Work routing; no HandlerRegistry, bus, domain queue or new runner. |
| Financial | NOT STARTED / NOT READY; no ledger or formulas introduced. |

For a non-empty successful page, the post-decoder cursor is an opaque string: trim is non-empty, length is at most 40, and trimmed last_id differs from from_id. No digits-only, integer, positivity or numeric-order requirement is imposed. Lossless decoding makes numeric and quoted numeric cursor representations equivalent; the focused test uses 1.5.

Verified local properties:

- Invalid successful page/detail fails `meli_billing_contract` without invalid canonical mutation; all details are prepared before persistence.
- Stale cursor/replay fails `billing_cursor_conflict` and preserves previous facts/cursor/continuation; period locking and equality use the durable from_id.
- Claim lost after HTTP cannot commit canonical effects. `recoverRunning`, new claim and replay commit the canonical effect once.
- Empty results are terminal even without last_id; cursor remains unchanged and no continuation is created.
- Non-empty valid page persists facts, advances cursor and creates one deduplicated continuation atomically with current Work completion.
- HTTP 206 defers with no facts/cursor/continuation mutation; the period is not declared caught_up. This does not certify an indefinite recurrence policy.
- No auto-repair architecture was added. BILL/CREDIT_NOTE remain separate sequential streams.

Evidence: `BillingPeriodSyncHandler::preparePage/lockPeriod`, `WorkRepository::completeCurrentClaim`, and focused tests `testInvalidSuccessfulPageFailsCurrentClaimWithoutCanonicalMutation`, `testNumericAndQuotedNumericLastIdAreEquivalentOpaqueCursors`, `testStalePageReplayFailsCursorConflictWithoutChangingCanonicalState`, `testLostClaimAfterRemotePageCannotPersistAndRecoveredWorkCanReplayOnce`, plus empty/non-empty page tests.

Billing is not complete: routing and core API safety remain open. Financial requires sufficient local facts, source-aligned sale_fee, pack/shipping coverage and no-double-count fixtures; no missing value is invented as zero.

## 3. Current gates and priorities

| Gate | Current verdict / limit |
|---|---|
| G1 REMOTE_TRUTH | Local implemented contracts evidenced; external READ-only certification remains F14. No new official/API live verification in R0. |
| G2 WORK_SAFETY | Local supported-producer coverage GREEN; conditional malformed scalar payload robustness remains open. No universal claim. |
| G3 RATE_SAFETY | OPEN / NOT FULL PASS. Existing cooldown races and Retry-After/OAuth recovery gaps remain; normal green tests do not certify these missing protections. |
| G4 SALES_AUDIT_TRUTH | CLOSED within local confirmation/repair coverage; infrastructure exception classification remains a separate open core issue. |
| G5 BILLING_CURSOR_TRUTH | PASS LOCAL THROUGH F6A-LOCAL-3B: opaque cursor, claim, invalid page, replay and recovery evidenced. Routing/external behavior not certified by this gate. |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED / NOT READY. |
| G7 WRITE_FAIL_CLOSED | Local coverage GREEN; remote writes OFF. |
| G8 HOSTING_REALITY | DEFERRED F14 / NOT CERTIFIED. |

G3 risks confirmed by existing code and the same-SHA audit evidence: a non-429 response clears cooldown unconditionally; registration can overwrite a longer deadline and uses an unprotected read/increment; Retry-After dates/extreme values and propagation need correction. OAuth uncertain POST, busy lock and SQL versus business error classification require their own bounded correction. R0 does not implement any of these.

Cross-family/provider quota scope remains unproven. Do not introduce a global limiter or adaptive engine by inference. Diagnostic debug-OFF history, calibration authority and 206 recurrence remain separate later blocks; critical protection must not wait for their screens.

The existing multi-company domain decision remains open: schema permits the same external_user_id across companies, and webhook account resolution fails closed when ambiguous. This is not a newly confirmed schema defect. Preserve sale_fee only against its source contract; add gross_price only if a concrete contract/fixture demonstrates its need. Neither change belongs to R0.

## 4. Document responsibilities and external boundaries

```text
AGENTS.md = engineering law / KISS / noise reduction
README.md = product / permitted architecture / master roadmap / dependencies / guardrails
CURRENT_CHECKPOINT.md = current state / exact implementation-QA SHA / gates / next block
ERP2_AUTHORITY.md = durable architecture and domain contracts
Git = superseded history
```

Current truth: local code/schema > same-SHA tests/QA > same-SHA evidence > checkpoint > durable authority > README/supporting documents > handoffs/audits > remote visibility > inference. Explicit user authorization governs scope. An audit is evidence, not permission to enlarge the architecture.

The master roadmap lives in README; this checkpoint does not duplicate it. F1–F13 use local/test fakes and REAL_MELI_HTTP=0. F14 covers hosting, real ERP2 OAuth and READ-only canaries with separate authorization; F15 is cutover; F16 requires explicit separate remote-write authorization.

C0 tooling stays read-only and sanitized: no OAuth refresh, Billing/usage/cooldown/Debug persistence or remote writes. Real seller-MCO C0 remains F14, not a prerequisite for already completed local Billing development. No external issue or hosting/app/OAuth status was newly certified here.

ERP2_AUTHORITY.md is unchanged: its durable contracts remain compatible. HEAD, QA, roadmap progress and R0 closure do not justify modifying that file.

## 5. NEXT and STOP

```text
CURRENT = R0 CLOSED
NEXT = MB-01 / API-SAFETY-1
STOP
```

MB-01 addresses cooldown durability/concurrency within existing components. MB-02 addresses Retry-After; MB-03 addresses OAuth/infra recovery. Complete these safety blocks before greater Billing remote consumption. This docs-only block does not authorize or initiate their implementation.

No runtime/schema/config/dependency/test changes, new files, tables, states or layers in R0. Do not start routing, Financial, sale_fee changes, a global limiter or F14 here. No real Mercado Libre HTTP/OAuth, production mutation, remote writes, push, merge or deploy.
