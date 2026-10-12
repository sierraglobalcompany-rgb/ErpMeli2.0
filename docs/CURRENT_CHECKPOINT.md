# CURRENT CHECKPOINT — ERP MELI 2.0

**STOP AFTER THIS CHECKPOINT**

**Date:** 2026-10-11

**Repo:** `sierraglobalcompany-rgb/ErpMeli2.0`

**Branch:** `impl/v3-b-sales-audit-20261010`

**MB-09 implementation and functional QA SHA:** `c30b7249ae602fbd35102df0120505d32e43756b`

**Working tree before MB-09:** CLEAN

**Last closed block / CURRENT:** MB-09 CLOSED — F6B-FACTS

**NEXT:** MB-10 / F6B-FINANCIAL

**REAL_MELI_HTTP:** `0`

**Remote Mercado Libre writes:** OFF

This docs-only commit follows the implementation and fact-proof QA SHA above; it does not change runtime, schema, tests or dependencies. MB-09 QA used an isolated local MariaDB 11.8.8 container on port 13342; the container and anonymous volume were removed after QA. Tests used injected transports/fakes with `REAL_MELI_HTTP=0`; no real OAuth or Mercado Libre HTTP, remote writes, push, merge or deploy was performed. F14 external certification remains pending.

## 1. Local verification and QA

The expected branch is active. The MB-09 functional commit is `3a7847ffd4b92eebaa5a07a3d593bff8f299f1aa` (`feat(sales): persist exact sale fee`); the test-only fact-proof commit is `c30b7249ae602fbd35102df0120505d32e43756b`. QA ran on the tree at the latter SHA.

The prior MB-01 ancestry is:

```text
997c22f5dcd2c054030ea0f88498a9052845ee25
-> ea86ba2b8e9d3cae8b090dcfc128570f73a334d5
```

The only commit between those SHAs is `ea86ba2b`, message `fix(meli): harden cooldown concurrency`. It changes `app/Integrations/MercadoLibre/Client/MeliClient.php`, `app/Integrations/MercadoLibre/Client/MeliCooldownRepository.php` and `tests/Integration/MeliRateLimitTest.php`.

Fresh QA results for MB-09:

| Check | Result |
|---|---|
| `composer validate --strict` | PASS, exit 0 |
| `php vendor/bin/phpstan analyse --no-progress` | PASS, 0 errors, exit 0 |
| Focused MB-09A Sales persistence and MB-09B fact proof regressions | PASS, 70 tests / 558 assertions / 0 failures / 0 errors |
| `php vendor/bin/phpunit` | PASS, 348 tests / 2600 assertions / 1 skipped / 0 failures / 0 errors, exit 0 |
| Runtime | PHP 8.5.8; PHPUnit 12.5.38 |

PHPUnit used a temporary local MariaDB 11.8.8 container on port 13342; the container and anonymous volume were removed after QA. Injected transports/fakes and `REAL_MELI_HTTP=0` kept Mercado Libre HTTP off. The one skipped test concerns unavailable symlink creation on this platform; that filesystem case is not newly certified.

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
| MB-02 / API-SAFETY-2 | CLOSED locally at `737c99d5`: strict ASCII delay-seconds and IMF-fixdate parsing, overflow-safe invalid handling, MAX(local, remote, durable) 429 cooldown, and GET 5xx Work wait propagation. |
| MB-03 / API-SAFETY-3 | CLOSED locally at `97d13247`: typed busy-lock signal defers Work without consuming attempts; refresh 429 uses the existing effective cooldown; uncertain 5xx/transport outcomes mark the account `attention`; invalid_grant remains `reauth_required`; the under-lock rotated-token reread and one-refresh/one-resource-retry 401 behavior remain intact; successful refresh tokens persist before a post-response cooldown-cleanup PDO failure escapes; PDO failures escape OAuth, Billing, Sales and Audit business catches. |
| F6A-LOCAL-4 | CLOSED locally at `4c402f4d`: `billing.period.sync` routes through the existing SalesWorkProcessor and WorkRunner to BillingPeriodSyncHandler; continuation remains a separate Work. No HandlerRegistry, bus, domain queue or new runner. |
| MB-09 / F6B-FACTS | CLOSED locally at `c30b7249`: `order_items.sale_fee` is nullable `DECIMAL(18,4)` from the existing Orders payload; fact-sufficiency fixtures prove current facts or explicit UNKNOWN/conflict states for MB-10. No new Billing, Financial or HTTP production path. |
| Financial / MB-10 | NOT STARTED; G6 is READY FOR MB-10 / NOT PASS. No ledger or formulas introduced. |

Verified local MB-01 properties:

- Atomic `register429` upsert increments `consecutive_429` up to `65535` without a pre-select race condition.
- Monotonic deadline via `GREATEST(blocked_until, candidate)` guarantees that a shorter 429 cannot shrink an existing longer durable cooldown.
- After upsert, `register429` reloads the effective `blocked_until` from database and returns it.
- `MeliClient` defers cooldown clearing until after response status is validated as 2xx and body is validated as valid JSON; non-2xx (e.g. 403) and invalid JSON do not clear cooldown.
- `MeliCooldownRepository::clear` deletes only expired rows (`blocked_until <= UTC_TIMESTAMP(6)`), preventing late completions from wiping out newer active cooldowns.

Evidence: `MeliCooldownRepository::register429/clear`, `MeliClient::request`, and focused tests in `tests/Integration/MeliRateLimitTest.php`.

Verified local MB-02 properties:

- `MeliClient` accepts exact ASCII delay-seconds and strict IMF-fixdate in UTC; past dates resolve to zero, while missing, relative, malformed, and nonrepresentable values preserve the local fallback.
- A valid remote wait is not clamped; the 429 candidate is the greater of the existing local backoff and remote wait, then MB-01 preserves the greater durable deadline.
- Read-operation 5xx exceptions retain the parsed wait and all three affected Work handlers schedule `max(30 seconds, remote wait)`. OAuth `AUTH` requests do not carry this read retry wait.

Evidence: `MeliClient::retryAfterSeconds/request`, `MeliCooldownRepository::register429`, `MeliApiException::$retryAfterSeconds`, the three affected read handlers, and focused integration tests.

Verified local MB-03 properties:

- A held per-account refresh lock emits one OAuth-specific busy signal; Billing, SyncOrder and Sales Audit defer the existing Work claim for 30 seconds with net attempts unchanged, no HTTP, and no account/token mutation.
- OAuth refresh 429 still defers to the effective durable cooldown without changing account status or retrying inline. `invalid_grant` still requires reauthorization without a second refresh POST.
- Refresh 5xx and ambiguous transport failures set the account to `attention`; subsequent Work stops before HTTP until the account is resolved. The lock-protected token reread still uses a token rotated by another process without a redundant refresh.
- If the remote refresh succeeded but expired-cooldown cleanup fails with PDO, the validated rotated token pair is persisted first; the SQL failure still escapes, and the next Work uses the fresh token without another refresh POST.
- `refreshAfterUnauthorized()` acquires its account lock and rereads a rotated token written by another connection; it returns the new token with zero refresh HTTP.
- First resource 401 still allows one refresh and one resource retry; a second 401 ends without a second refresh or third resource request.
- PDO failures escape before being mapped to OAuth attention or Sales Audit business outcomes; transactional Audit state writes keep rollback behavior.

Evidence: `OAuthRefreshService`, `MeliClient`, Billing/SyncOrder/Sales Audit handlers, `SalesWorkProcessor`, `SalesAuditRepairHandler`, and focused tests in `OAuthRefreshServiceTest`, `SyncOrderHandlerTokenFailureTest`, `BillingPeriodSyncHandlerTest`, `SalesAuditTransientFailureTest`, `SalesAuditUnauthorizedTest`, `SalesWorkProcessorTest`, and `SalesAuditRepairFanoutTest`.

Verified local MB-08 properties:

- The existing application processor routes each claimed `billing.period.sync` Work once to `BillingPeriodSyncHandler`; the handler retains ownership of OAuth, API calls, page lifecycle, cursor persistence and continuation enqueue.
- The production `bin/work.php` composition reuses its existing PDO, WorkRepository, MeliClient and OAuth services and keeps one WorkRunner and processor callback.
- The first Billing page queues a separate `billing.period.sync` continuation. Separate one-item WorkRunner calls process the initial Work and continuation; no page loop or recursive processing was added.
- `order.sync` and `sales.audit` still route through their existing handlers. Unknown Work types still fail terminally as `unsupported_work_type`.

Evidence: focused integration tests in `SalesWorkProcessorTest`, `SalesWorkPoisonLoopTest`, `WorkCliEntrypointTest`, and `BillingPeriodSyncHandlerTest`.

Verified local MB-09 properties:

- `order_items.sale_fee` stores the exact remote per-unit decimal through the existing `SyncOrderHandler::decimal4` path; missing and explicit `null` remain SQL `NULL`, true zero remains `0.0000`, and malformed values fail with the existing order-contract outcome. It is not multiplied by quantity; item/order currency remains unchanged and no additional HTTP call is made.
- The canonical facts support direct Billing order/pack identity and Billing-order-to-local-order-pack linkage. Contradictory Billing order IDs remain visible together, so ambiguity is detectable without choosing one.
- `receiver_shipping_cost` is preserved as buyer-side evidence; zero is known zero, missing/null is unknown, and seller shipping cost remains unavailable. BILL/CREDIT_NOTE, detail type/subtype, associations, bonus reference, exact amount, currency and source timestamps remain inspectable. A shared pack charge stays one Billing fact; replay does not duplicate canonical details.
- MB-09B adds test-only fixtures; there is no Financial read model, allocation, accounting sign rule, ledger, table, new HTTP operation or production state.

Evidence: `SyncOrderHandlerPersistenceTest`, `SalesSchemaTest`, `FinancialFactSufficiencyTest`, `BillingDetailNormalizerTest`, and `BillingPeriodSyncHandlerTest`.

## 3. Current gates and priorities

| Gate | Current verdict / limit |
|---|---|
| G1 REMOTE_TRUTH | Local implemented contracts evidenced; external READ-only certification remains F14. |
| G2 WORK_SAFETY | Local supported-producer coverage GREEN; conditional malformed scalar payload robustness remains open. |
| G3 RATE_SAFETY | PASS LOCAL / core API safety closed locally through MB-03. This is local fake/injected-transport coverage; real provider quotas and external behavior remain pending F14. |
| G4 SALES_AUDIT_TRUTH | CLOSED within local confirmation/repair coverage. |
| G5 BILLING_CURSOR_TRUTH | PASS LOCAL through F6A-LOCAL-4, including normal Work routing and separate continuation processing. |
| G6 FINANCIAL_NO_DOUBLE_COUNT | READY FOR MB-10 / NOT PASS; facts are sufficient or explicitly incomplete/conflicting, and no Financial behavior exists yet. |
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

README roadmap and ERP2_AUTHORITY fact definition were updated for MB-09. AGENTS.md is unchanged.

## 5. NEXT and STOP

```text
CURRENT = MB-09 CLOSED
NEXT = MB-10 / F6B-FINANCIAL
STOP
```

Stop after this checkpoint. Do not start MB-10 or implement Financial formulas, allocation, or a missing-fact candidate here. Seller shipping remains UNKNOWN; do not add remote fetches to fill it. No real Mercado Libre HTTP/OAuth, production mutation, remote writes, push, merge or deploy. F14 external certification remains pending.
