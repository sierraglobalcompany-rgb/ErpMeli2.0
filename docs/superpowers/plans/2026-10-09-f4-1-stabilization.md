# F4.1 Stabilization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the concrete reliability gaps found by the 2026-10-09 audit before opening F5, without adding new architecture.

**Architecture:** Keep the existing modular monolith, single Work Engine, single MeliClient boundary and four work states. Each fix is a small fail-safe at the current boundary; no handler registry, retry engine, second queue, event bus or new framework.

**Tech Stack:** PHP 8.5, Slim 4, PDO, MariaDB 11.4, PHPUnit 12, PHPStan.

**Spec:** GitHub Issues #9 and #11 plus `docs/runtime-preflight.md`, `docs/meli-contracts-2026.md` and the ERP Meli 2.0 Master Plan/Handoff recovered on 2026-10-09.

## Global Constraints

- KISS + YAGNI; no future-use abstractions.
- TDD for every behavior change: RED -> verify intended failure -> minimal GREEN -> full QA.
- One Work Engine / one WorkRunner / one MeliClient boundary.
- Work states remain exactly `pending|running|done|failed`.
- Tests/CI must keep real Mercado Libre HTTP blocked.
- No secrets or raw remote payloads in logs/work rows.
- No merge/deploy while external F0/F1 and Mercado Libre app gates remain open.

## Review Focus

- Unknown/malformed environment values must fail closed rather than enable real remote traffic accidentally.
- Optional aggregate telemetry must never turn an otherwise successful Mercado Libre response into a business/OAuth failure.
- Unsupported or malformed claimed work must not remain in a recover/reclaim poison loop.
- Oversized webhook bodies must be answered safely without persistence or work creation.
- Named locks must be installation/database-scoped so two ERP2 databases on one MariaDB server cannot contend accidentally.

---

### Task 1: Fail-closed environment

**Files:**
- Modify: `app/Core/Config/AppConfig.php`
- Modify: `app/Integrations/MercadoLibre/Transport/RemoteHostPolicy.php`
- Test: `tests/Unit/RemoteHostPolicyTest.php`
- Test: `tests/Unit/BootstrapTest.php` or a focused config test if cleaner

**Interfaces:**
- Consumes: `APP_ENV` string from existing config.
- Produces: only known environments `local|test|production` are accepted for security-sensitive behavior; unknown values cannot dispatch real ML HTTP.

- [ ] Add a failing test proving unknown environment cannot allow `api.mercadolibre.com` and production-only assumptions are not silently bypassed.
- [ ] Run focused test and confirm failure is the missing fail-closed behavior.
- [ ] Implement the smallest validation/policy change.
- [ ] Run focused tests and full `composer qa`.
- [ ] Commit.

### Task 2: Best-effort API telemetry

**Files:**
- Modify: `app/Integrations/MercadoLibre/Client/MeliClient.php`
- Test: `tests/Integration/MeliClientResponseMetricsTest.php`

**Interfaces:**
- Consumes: optional `ApiUsageRecorder`.
- Produces: recorder/database failure cannot mask the actual HTTP outcome; metrics remain bounded best-effort observability, never business truth.

- [ ] Add failing tests for a successful HTTP response and a typed remote error when the recorder throws.
- [ ] Verify RED.
- [ ] Make usage recording best-effort with the smallest local containment in `MeliClient`.
- [ ] Verify focused tests and full `composer qa`.
- [ ] Commit.

### Task 3: Terminal fail-safe for unsupported/malformed work

**Files:**
- Modify: `app/Modules/Sales/SalesWorkProcessor.php`
- Modify only if necessary: `app/Modules/Sales/SyncOrder/OrderSyncWorkProcessor.php`
- Test: `tests/Integration/SalesWorkProcessorTest.php`

**Interfaces:**
- Consumes: a current claimed work row and existing `WorkRepository::failCurrentClaim()`.
- Produces: unsupported Sales type or invalid Sales claim becomes terminal `failed` using the current claim token; no poison recovery loop.

- [ ] Add failing test: unsupported claimed type is terminally failed with safe error code/message.
- [ ] Add failing test for malformed known Sales claim if current code can throw before transitioning state.
- [ ] Verify RED failures are the poison-loop behavior.
- [ ] Implement minimal fail-safe; no HandlerRegistry/ExceptionEngine/new work state.
- [ ] Verify focused tests and full `composer qa`.
- [ ] Commit.

### Task 4: Bounded webhook body

**Files:**
- Modify: `app/Core/Http/Routes.php`
- Test: `tests/Integration/OrderWebhookHttpRouteTest.php`

**Interfaces:**
- Consumes: incoming `/webhooks/mercadolibre` request body.
- Produces: oversized webhook is acknowledged safely with HTTP 200 but creates zero webhook events and zero work; normal valid payload remains unchanged.

- [ ] Add failing oversized-body HTTP test.
- [ ] Verify RED.
- [ ] Add a small explicit byte cap before parsed payload processing; no raw body persistence/logging.
- [ ] Verify valid + oversized route tests and full `composer qa`.
- [ ] Commit.

### Task 5: Database-scoped WorkRunner lock

**Files:**
- Modify: `bin/work.php`
- Test: `tests/Integration/WorkCliEntrypointTest.php`

**Interfaces:**
- Consumes: configured DB name.
- Produces: production runner lock name includes the installation/database identity while preserving one global runner per ERP2 database.

- [ ] Add failing composition test requiring DB-scoped lock name in CLI wiring.
- [ ] Verify RED.
- [ ] Build the lock name directly from configured DB name; do not add a LockName service.
- [ ] Verify focused tests and full `composer qa`.
- [ ] Commit.

### Final Gate

- [ ] Fresh full `composer qa` equivalent in GitHub Actions is green.
- [ ] Compare branch against F4 base and confirm only planned files changed.
- [ ] Re-audit F4 exit gates plus these five regression cases.
- [ ] Keep PR Draft and stacked on `impl/f4-sales-slice-20261009`; do not merge automatically.
- [ ] Decimal precision adversarial test remains the next mandatory hardening before Billing unless pulled into this branch by a newly proven failure.
