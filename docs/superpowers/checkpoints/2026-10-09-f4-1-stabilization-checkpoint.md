# CHECKPOINT — F4.1 Stabilization

Date: 2026-10-09
Repository: `sierraglobalcompany-rgb/ErpMeli2.0`
Branch: `fix/f4-1-stabilization-20261009`
Base: `impl/f4-sales-slice-20261009`
Draft PR: #13 — `F4.1 — Stabilization hardening before F5`
Verified functional HEAD before this checkpoint commit: `09620977771e781238760976f541980c15698f7e`

## Purpose

Close concrete reliability gaps found after the F4 audit before opening F5. Keep KISS/YAGNI, existing Work Engine, existing MeliClient and four work states. No merge or deploy.

## Completed and verified

### 1. Fail-closed APP_ENV

Status: DONE / GREEN

Changes:
- `AppConfig::fromEnvironment()` rejects unknown `APP_ENV` values. Accepted: `local|test|production`.
- `RemoteHostPolicy` only permits real Mercado Libre host when environment is exactly `production`.
- Existing remote-block error text preserved to avoid unnecessary contract drift.

Evidence:
- GitHub Actions QA run #262: SUCCESS.

Incidental fixture hardening:
- `ReconcileOrdersHandlerTest` had a clock-sensitive token expiry and was moved to a stable future date.

### 2. Best-effort API telemetry

Status: DONE / GREEN

RED proved that failure of `api_usage_daily` could mask a valid HTTP 200 or replace a typed HTTP 503 error.

Minimal implementation:
- `MeliClient::recordUsage()` contains recorder failures locally.
- Metrics remain best-effort and are not commercial truth.
- No retry engine, logger framework or new abstraction.

Evidence:
- RED QA #263: failed for the intended recorder exception.
- GREEN QA #264: SUCCESS.

### 3. Poison-loop fail-safe

Status: DONE / GREEN

#### 3a. Unsupported work type

RED:
- test: `tests/Integration/SalesWorkPoisonLoopTest.php`
- commit: `c715791f7fd4ba449ad4e578a5a74adc82d52ae5`
- QA #266: 114 tests, one intended error: unsupported Sales work type.

GREEN:
- `SalesWorkProcessor` now uses existing `WorkRepository::failCurrentClaim()` for unsupported types.
- terminal result: `failed`, code `unsupported_work_type`, claim cleared, no recovery/reclaim, zero HTTP.
- QA #269: SUCCESS.

#### 3b. Malformed known `order.sync`

RED:
- test: `tests/Integration/OrderSyncMalformedWorkTest.php`
- commit: `bead57a0facf8077e4a83fe4cff49fcacf9e0a05`
- QA #270: 115 tests, one intended error: invalid `order.sync` claim.

GREEN:
- `OrderSyncWorkProcessor` receives the existing `WorkRepository` and terminally fails local invalid claims with `invalid_work_claim`.
- invalid local work never reaches Mercado Libre HTTP.
- constructor wiring updated in CLI and affected tests.
- QA #276 exposed one stale E2E constructor fixture only; production behavior was not the cause.
- fixture corrected.
- QA #277: SUCCESS.

#### 3c. Malformed `orders.reconcile` identity

Read-only audit confirmed `ReconcileOrdersHandler` already terminally handles invalid payload/account conditions. The remaining poison-loop was the processor identity guard throwing before reaching the handler.

RED:
- test: `tests/Integration/ReconcileMalformedWorkTest.php`
- commit: `0cc8d6b027ba9c55552cbc7ab0f569701115e023`
- QA #278: 116 tests, one intended error: `Invalid orders.reconcile work claim.`

GREEN:
- invalid company/account identity now uses `failCurrentClaim()` with `invalid_work_claim`.
- no HTTP, no recovery, no fifth state, no retry framework.
- implementation commit: `09620977771e781238760976f541980c15698f7e`
- QA #279: SUCCESS.

Task 3 conclusion:
- unsupported type: terminally failed;
- malformed `order.sync`: terminally failed;
- malformed `orders.reconcile` identity: terminally failed;
- recoverable remote/crash semantics remain unchanged.

## Remaining F4.1 work — exact order

### 4. Bounded webhook body — NEXT

Authority:
- Especificación Maestra requires webhook flow to validate `tamaño/topic/resource/seller conocidos` before opening the DB transaction.
- F4 Plan requires invalid-body coverage.

Targets:
- `app/Core/Http/Routes.php`
- `tests/Integration/OrderWebhookHttpRouteTest.php`

Required behavior:
- oversized `/webhooks/mercadolibre` request is handled safely with HTTP 200,
- creates zero `webhook_events`,
- creates zero `work_items`,
- normal valid webhook path remains unchanged,
- raw oversized body is never persisted/logged,
- zero Mercado Libre HTTP inside webhook request.

Important:
- project sources found so far require validating size but do not define a canonical numeric byte threshold.
- do not claim an official Mercado Libre size limit without evidence.
- use one explicit bounded implementation constant, documented as an ERP2 implementation limit, only after RED confirms current gap.

### 5. Database-scoped WorkRunner lock

Target:
- `bin/work.php`
- `tests/Integration/WorkCliEntrypointTest.php`

Required behavior:
- production WorkRunner named lock includes DB/install identity using configured DB name directly,
- two ERP2 databases on one MariaDB server do not contend accidentally,
- no LockName service/framework.

## Mandatory hardening still pending before Billing

### Decimal precision adversarial test

Current concern:
- `SyncOrderHandler::decimal4()` converts through float before formatting.

Rule:
- first add adversarial RED tests using decimal strings / large values;
- change implementation only if the test proves precision loss;
- mandatory before Billing.

## Roadmap constraint

Do NOT silently start Billing after F4.1.

Canonical Master Plan:
- F5 = Debug DVR
- F6 = Billing

Unless the user explicitly prioritizes Billing and the roadmap is deliberately updated, preserve that order.

## External blockers remain open

Do not merge/deploy based only on CI green:
- Hostinger real preflight not certified;
- `main` branch protection not certified/enabled;
- dedicated Mercado Libre ERP2 application + callback/webhook authorization not certified;
- PR stack remains Draft/unmerged.

## Resume command

1. Audit current branch HEAD and PR #13.
2. Confirm latest functional QA #279 is green.
3. Start only Task 4 bounded-webhook TDD RED.
4. Do not reopen Tasks 1–3 unless a regression appears.
5. Keep changes small and refresh this checkpoint after Task 4 before moving to Task 5.
