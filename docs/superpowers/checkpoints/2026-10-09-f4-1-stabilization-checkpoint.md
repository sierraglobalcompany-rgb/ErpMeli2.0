# CHECKPOINT — F4.1 Stabilization

Date: 2026-10-09
Repository: `sierraglobalcompany-rgb/ErpMeli2.0`
Branch: `fix/f4-1-stabilization-20261009`
Base: `impl/f4-sales-slice-20261009`
Draft PR: #13 — `F4.1 — Stabilization hardening before F5`
Verified functional HEAD before this checkpoint commit: `a936bad156f3bb183543acc6f26a0541613c8870`
Latest full QA: run #288 — SUCCESS.

## Purpose

Close concrete reliability gaps found after the F4 audit before opening F5. Keep KISS/YAGNI, existing Work Engine, existing MeliClient and four work states. No merge or deploy.

## Completed and verified

### 1. Fail-closed APP_ENV — DONE / GREEN

- Unknown `APP_ENV` rejected.
- Real Mercado Libre host only allowed when environment is exactly `production`.
- QA #262 SUCCESS.

### 2. Best-effort API telemetry — DONE / GREEN

- Recorder failures cannot mask a valid HTTP response or replace typed remote errors.
- No retry engine or new framework.
- RED #263; GREEN #264.

### 3. Poison-loop fail-safe — DONE / GREEN

Covered and terminally failed without remote HTTP:
- unsupported work type → `unsupported_work_type`;
- malformed `order.sync` → `invalid_work_claim`;
- malformed `orders.reconcile` identity → `invalid_work_claim`.

Relevant evidence:
- unsupported type GREEN #269;
- malformed order.sync GREEN #277;
- malformed reconcile GREEN #279.

### 4. Bounded webhook body — DONE / GREEN

- Oversized `/webhooks/mercadolibre` body is bounded before normal body processing/persistence.
- Oversized input creates zero `webhook_events` and zero `work_items`.
- Valid webhook behavior remains unchanged.
- No claim that the implementation threshold is an official Mercado Libre limit.
- RED commit: `28dc0f5b88edf11cb12cf5a8eacf4f7f757b04fa`.
- GREEN commit: `2a979fcb54c5dcb0639977467c1e6d9468809e83`.
- QA #282 SUCCESS.

### 5. Database-scoped WorkRunner lock — DONE / GREEN

- Production CLI lock now uses configured DB identity directly:
  `erp_meli2.runner.<DB_NAME>`.
- Two ERP2 databases on one MariaDB server no longer share the same runner lock name accidentally.
- No LockName service/framework added.
- RED commit: `5708495738dd52e497dcd664aa6fafef8584c55c`.
- GREEN commit: `a936bad156f3bb183543acc6f26a0541613c8870`.
- QA #288 SUCCESS.

### Incidental fixture hardening found on 2026-10-09

QA #283 exposed three additional Sales tests whose access-token fixtures expired at `2026-10-09 08:00:00 UTC`. These were test-clock defects, not production regressions.

Hardened to a stable future date in:
- `OrderSyncWorkProcessorTest`;
- `OrderSyncWorkRunnerPacingTest`;
- `SalesVerticalSliceEndToEndTest`.

Final QA #288 proves the full branch green after those corrections and Task 5.

## F4.1 conclusion

F4.1 stabilization is technically closed at HEAD `a936bad156f3bb183543acc6f26a0541613c8870` with QA #288 SUCCESS.

Do not merge/deploy automatically. PR #13 remains Draft and stacked over F4.

## Mandatory hardening still pending before Billing

### Decimal precision adversarial test — NEXT SMALL BLOCK

Current concern:
- `SyncOrderHandler::decimal4()` converts through float before formatting.

Rule:
1. Add adversarial RED tests using decimal strings / large values.
2. Change implementation only if the test proves precision loss.
3. Keep DECIMAL semantics and avoid a new money/decimal framework unless evidence requires it.

This hardening is mandatory before Billing, but it does not reorder the roadmap.

## Roadmap constraint

Canonical Master Plan remains:
- F5 = Debug DVR
- F6 = Billing

Do not silently start Billing or renumber phases without an explicit roadmap decision.

## External blockers remain open

Do not merge/deploy based only on CI green:
- Hostinger real preflight not certified;
- `main` branch protection not certified/enabled;
- dedicated Mercado Libre ERP2 application + callback/webhook authorization not certified;
- PR stack remains Draft/unmerged.

## Resume command

1. Audit HEAD `a936bad156f3bb183543acc6f26a0541613c8870` and QA #288.
2. Do not reopen Tasks 1–5 unless regression evidence appears.
3. Run the decimal precision adversarial RED/GREEN block.
4. Then continue the canonical roadmap with F5 Debug DVR.
