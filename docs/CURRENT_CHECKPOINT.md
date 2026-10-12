# CURRENT CHECKPOINT — ERP MELI 2.0

**STOP AFTER THIS CHECKPOINT**

**Date:** 2026-10-11

**Repo:** `sierraglobalcompany-rgb/ErpMeli2.0`

**Branch:** `impl/v3-b-sales-audit-20261010`

**MB-10 implementation and functional QA SHA:** `c62fd4f75869fe9e1e5a0bcd3ee72ce6d3d919bd`

**MB-10 base SHA:** `b2415df01b848425e8a2121d3e307d1e83af9e3d`

**Last closed block / CURRENT:** MB-10 CLOSED — F6B-FINANCIAL

**NEXT:** MB-11

**REAL_MELI_HTTP:** `0`

**Remote Mercado Libre writes:** OFF

This docs-only commit follows the MB-10 functional commit and its valid full-QA run. QA used isolated local MariaDB 10.11.19 on port 13342 with `DB_NAME=erp_meli2_test`; fresh migrations and connectivity passed. Composer passed, global PHPStan reported 0 errors, and PHPUnit passed with 356 tests / 2732 assertions / 1 skipped / 0 failures / 0 errors. The prior focal A–H and MB-09/Billing regression passes remain valid because no code changed after those runs. The temporary database container was removed after QA. Tests used injected transports/fakes with `REAL_MELI_HTTP=0`; no real OAuth or Mercado Libre HTTP, remote writes, push, merge or deploy was performed. F14 external certification remains pending.

## 1. Local verification and QA

The expected branch is active. The MB-09 functional commit is `3a7847ffd4b92eebaa5a07a3d593bff8f299f1aa` (`feat(sales): persist exact sale fee`); the test-only fact-proof commit is `c30b7249ae602fbd35102df0120505d32e43756b`; final review fixes are `a21d4fa6a72b3f79b097c7b9ad297b35383afc9e` (`fix(sales): reject unrepresentable sale fees`). QA ran on the tree at the final SHA.

The prior MB-01 ancestry is:

```text
997c22f5dcd2c054030ea0f88498a9052845ee25
-> ea86ba2b8e9d3cae8b090dcfc128570f73a334d5
```

The only commit between those SHAs is `ea86ba2b`, message `fix(meli): harden cooldown concurrency`. It changes `app/Integrations/MercadoLibre/Client/MeliClient.php`, `app/Integrations/MercadoLibre/Client/MeliCooldownRepository.php` and `tests/Integration/MeliRateLimitTest.php`.

MB-10 final QA:

| Check | Result |
|---|---|
| `composer validate --strict` | PASS, exit 0 |
| `php vendor/bin/phpstan analyse --no-progress` | PASS, 0 errors, exit 0 |
| Previous focal MB-10 A–H and nearest MB-09/Billing regressions | PASS, valid prior evidence reused; no intervening code changes |
| `php vendor/bin/phpunit --colors=never` | PASS, 356 tests / 2732 assertions / 1 skipped / 0 failures / 0 errors, exit 0 |
| Runtime | PHP 8.5.8; PHPUnit 12.5.38 |

The corrected QA environment was `DB_HOST=127.0.0.1`, `DB_PORT=13342`, `DB_NAME=erp_meli2_test`, with local-only test credentials. Fresh migrations and DB connectivity passed before QA. PHPUnit used a temporary MariaDB 10.11.19 container; injected transports/fakes and `REAL_MELI_HTTP=0` kept Mercado Libre HTTP off. The one skipped test concerns unavailable symlink creation on this platform; that filesystem case is not newly certified.

Previous MB-09 QA evidence: implementation/fact-proof SHA `a21d4fa6a72b3f79b097c7b9ad297b35383afc9e`; its focused and full-suite results remain historical evidence for that block.

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
| Financial / MB-10 | CLOSED locally at `c62fd4f`; G6 PASS LOCAL / source-separated reconciliation. One read model reuses Sales and Billing facts; no schema, ledger, allocation engine, routes, UI, Work path, HTTP operation or dependency added. |

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

- `order_items.sale_fee` stores the exact remote per-unit decimal through the existing `SyncOrderHandler::decimal4` path; missing and explicit `null` remain SQL `NULL`, true zero remains `0.0000`, and malformed or non-representable values fail with the existing order-contract outcome. Extra trailing zeros are accepted when the value is exact. It is not multiplied by quantity; item/order currency remains unchanged and no additional HTTP call is made.
- The canonical facts support direct Billing order/pack identity and Billing-order-to-local-order-pack linkage. Contradictory Billing order IDs remain visible together, so ambiguity is detectable without choosing one.
- `receiver_shipping_cost` is preserved as buyer-side evidence; zero is known zero, missing/null is unknown, and seller shipping cost remains unavailable. BILL/CREDIT_NOTE, detail type/subtype, associations, bonus reference, exact amount, currency and source timestamps remain inspectable. A shared pack charge stays one Billing fact; replay does not duplicate canonical details.
- MB-09B adds test-only fixtures; there is no Financial read model, allocation, accounting sign rule, ledger, table, new HTTP operation or production state.

Evidence: `SyncOrderHandlerPersistenceTest`, `SalesSchemaTest`, `FinancialFactSufficiencyTest`, `BillingDetailNormalizerTest`, and `BillingPeriodSyncHandlerTest`.

Verified local MB-10 properties:

- `FinancialReadModel` scopes every read to company/account and selects the local pack when present, otherwise the requested order. It reads existing Sales, Billing and pack-link facts without creating state.
- Per-unit `sale_fee` is multiplied by item quantity using MariaDB decimal arithmetic; missing facts, mixed currency and conflicting Billing identity remain explicitly `UNKNOWN` or `CONFLICT` rather than being filled by inference.
- Billing details retain source amounts, currencies, types and associations. Shared pack charges appear once by external detail identity; contradictory duplicate copies are omitted and reported as a conflict.
- Receiver shipping evidence remains separate. Seller shipping cost and official/reference totals remain `UNKNOWN`; the model exposes no inferred net, final cost or official total.
- The implementation adds one production class and test coverage only: no schema changes, new tables/columns, routes/UI, Work, HTTP, dependencies, ledger or allocation engine.

Evidence: `app/Modules/Financial/FinancialReadModel.php` and `tests/Integration/FinancialFactSufficiencyTest.php`.

## 3. Current gates and priorities

| Gate | Current verdict / limit |
|---|---|
| G1 REMOTE_TRUTH | Local implemented contracts evidenced; external READ-only certification remains F14. |
| G2 WORK_SAFETY | Local supported-producer coverage GREEN; conditional malformed scalar payload robustness remains open. |
| G3 RATE_SAFETY | PASS LOCAL / core API safety closed locally through MB-03. This is local fake/injected-transport coverage; real provider quotas and external behavior remain pending F14. |
| G4 SALES_AUDIT_TRUTH | CLOSED within local confirmation/repair coverage. |
| G5 BILLING_CURSOR_TRUTH | PASS LOCAL through F6A-LOCAL-4, including normal Work routing and separate continuation processing. |
| G6 FINANCIAL_NO_DOUBLE_COUNT | PASS LOCAL / source-separated reconciliation; shared Billing identities are not counted twice and missing/conflicting facts remain explicit. Official/reference net and seller shipping remain UNKNOWN. |
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

README roadmap and ERP2_AUTHORITY Financial contract were updated for MB-10. AGENTS.md is unchanged.

## 5. NEXT and STOP

```text
CURRENT = MB-10 CLOSED — G6 PASS LOCAL
NEXT = MB-11
STOP
```

Stop after this checkpoint. Do not start MB-11 here. Seller shipping and official/reference net remain UNKNOWN; do not add remote fetches or infer values to fill them. `REAL_MELI_HTTP=0`, remote writes OFF, and F14 external certification remains pending. No production mutation, push, merge or deploy.
