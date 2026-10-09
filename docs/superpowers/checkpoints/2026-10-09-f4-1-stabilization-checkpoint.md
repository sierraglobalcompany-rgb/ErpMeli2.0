# CHECKPOINT — F4.1 Stabilization

Date: 2026-10-09
Repository: `sierraglobalcompany-rgb/ErpMeli2.0`
Branch: `fix/f4-1-stabilization-20261009`
Base: `impl/f4-sales-slice-20261009`
Draft PR: #13 — `F4.1 — Stabilization hardening before F5`
Checkpoint branch HEAD before this checkpoint commit: `e3df7caabae5216dcb86622998bc10290b115204`

## Purpose

Close concrete reliability gaps found after the F4 audit before opening F5. Keep KISS/YAGNI, existing Work Engine, existing MeliClient and four work states. No merge or deploy.

## Completed and verified

### 1. Fail-closed APP_ENV

Status: DONE / GREEN

Changes:
- `AppConfig::fromEnvironment()` now rejects unknown `APP_ENV` values. Accepted: `local|test|production`.
- `RemoteHostPolicy` only permits real Mercado Libre host when environment is exactly `production`.
- Existing remote-block error text was preserved to avoid unnecessary contract drift.

Tests added:
- unknown APP_ENV rejected by config.
- unknown APP_ENV cannot call real Mercado Libre host.

Evidence:
- GitHub Actions QA run #262: SUCCESS.

Incidental fixture hardening discovered during RED:
- `ReconcileOrdersHandlerTest` had token expiry fixed at `2026-10-09 06:00:00`; it started expiring as the current date/time advanced.
- Fixture changed to `2030-01-01 00:00:00.000000`.
- This is test-only stability, not production behavior.

### 2. Best-effort API telemetry

Status: DONE / GREEN

RED evidence:
- QA run #263 failed exactly because dropping `api_usage_daily` raised `PDOException` inside `ApiUsageRecorder`.
- One failure masked a valid HTTP 200.
- One failure replaced the expected typed HTTP 503 error.

Minimal implementation:
- `MeliClient::recordUsage()` now contains recorder failures locally.
- Aggregate usage telemetry remains optional/best-effort and cannot become business truth or alter the authoritative remote HTTP result.
- No retry engine, logger framework or new abstraction added.

Tests added:
- telemetry failure cannot mask successful HTTP response.
- telemetry failure cannot replace typed remote error.

Evidence:
- GitHub Actions QA run #264: SUCCESS.

Current checkpoint HEAD before checkpoint-file commit:
`e3df7caabae5216dcb86622998bc10290b115204`

## Remaining F4.1 work — exact order

### 3. Poison-loop fail-safe — NEXT

Target files:
- `app/Modules/Sales/SalesWorkProcessor.php`
- possibly `app/Modules/Sales/SyncOrder/OrderSyncWorkProcessor.php` only if test proves needed
- `tests/Integration/SalesWorkProcessorTest.php`

Required TDD:
1. RED: unsupported claimed Sales work type must not throw and remain `running` for stale recovery.
2. RED if applicable: malformed known Sales claim that throws before a state transition must also end terminally.
3. Minimal GREEN using current `WorkRepository::failCurrentClaim()` / existing claim token semantics.
4. No HandlerRegistry, ExceptionEngine, fifth work state or retry framework.

Expected terminal safe result:
- status `failed`
- safe bounded error code/message
- no recover/reclaim poison loop.

### 4. Bounded webhook body

Target:
- `app/Core/Http/Routes.php`
- `tests/Integration/OrderWebhookHttpRouteTest.php`

Required behavior:
- oversized `/webhooks/mercadolibre` request returns HTTP 200 safely,
- creates zero `webhook_events`,
- creates zero `work_items`,
- normal valid webhook path remains unchanged,
- raw oversized body is never persisted/logged.

### 5. Database-scoped WorkRunner lock

Target:
- `bin/work.php`
- `tests/Integration/WorkCliEntrypointTest.php`

Required behavior:
- production WorkRunner named lock includes DB/install identity (use configured DB name directly),
- two ERP2 databases on same MariaDB server do not contend accidentally,
- no LockName service or framework.

## Mandatory hardening still pending after F4.1

### Decimal precision adversarial test

Current concern:
- `SyncOrderHandler::decimal4()` converts through float before formatting.

Rule:
- first add adversarial RED tests using decimal strings / large values.
- change implementation only if the test proves precision loss.
- this remains mandatory before Billing.

## Roadmap constraint still unresolved

Do NOT silently start Billing after F4.1.

Canonical Master Plan says:
- F5 = Debug DVR
- F6 = Billing

A later conversation referred to next work as “F5 Billing”, but no formal approval resolved the reorder.

Therefore after F4.1 + decimal hardening:
- preserve canonical F5 Debug DVR unless the user explicitly prioritizes Billing and the roadmap is deliberately updated.

## External blockers remain open

Do not merge/deploy based only on local/CI green:
- Hostinger real preflight not certified.
- `main` branch protection not certified/enabled.
- dedicated Mercado Libre ERP2 application + callback/webhook authorization not certified.
- PR stack remains Draft/unmerged.

## Resume command for next agent/chat

Resume from this checkpoint, not from memory.

1. Audit current branch HEAD and PR #13.
2. Confirm latest QA is green.
3. Start only Task 3 poison-loop TDD RED.
4. Do not reopen completed Tasks 1–2 unless a regression appears.
5. Keep changes in small commits and refresh this checkpoint before context becomes large.
