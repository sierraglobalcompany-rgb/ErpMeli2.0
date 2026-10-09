# CHECKPOINT — F5 Debug DVR

Date: 2026-10-09
Repository: `sierraglobalcompany-rgb/ErpMeli2.0`
Branch: `impl/f5-debug-dvr-20261009`
Base: `fix/f4-1-stabilization-20261009`
Draft PR: #14 — `F5 — Debug DVR Completo`
Implementation plan: `docs/superpowers/plans/2026-10-09-f5-debug-dvr-implementation.md`
Current branch HEAD before this checkpoint commit: `9f96a4bfa5c95d8394ab5632e9a36e7fe4decdfb`
Last fully GREEN functional HEAD: `825d189f169c0c6c0e5a256694b214922b684ad8`
Latest full GREEN QA: run #343 — SUCCESS.
Current intentional RED QA: run #347 — FAILURE only in new F5.5 correlation tests.

## F5.1 — Safe bounded JSONL recorder — DONE / GREEN

RED:
- `tests/Unit/DebugRecorderTest.php`
- commit `1464469b13d38d5a39e662bbdcb3ac9c6280b2d6`
- QA #300 failed only because `DebugRecorder` did not exist.

GREEN:
- `app/Core/Logging/DebugRecorder.php`
- commit `4a4492248b35ea4adfc2a8e638b44dd40bf23c90`
- QA #303 SUCCESS.

Proven:
- Debug OFF writes nothing.
- Debug ON writes UTC-day JSONL.
- central safe-field allowlist; unknown/nested payload fields dropped.
- identifier fields are bounded and format-checked.
- verbose recorder stops at cap.
- normal `AppLogger` warnings remain independent.

Gate:
```text
DEBUG_RECORDER_SAFE=PASS
DEBUG_OFF_NO_FILE=PASS
DEBUG_CAP_NO_VERBOSE_WRITE=PASS
```

## F5.2 — Rotation / gzip / retention / cleanup — DONE / GREEN

RED:
- `tests/Unit/DebugMaintenanceTest.php`
- commit `8004aef6ef5b77f10f58f5460abb3f556bb155fb`
- QA #307 failed only because `DebugMaintenance` did not exist.

GREEN:
- `app/Core/Logging/DebugMaintenance.php`
- `bin/cleanup.php`
- functional head `db8b97a3ecb8a766a7d98c138015327c1b2a088c`
- QA #311 SUCCESS.

Proven:
- closed UTC-day `.jsonl` files gzip safely to `.jsonl.gz`;
- current UTC-day JSONL stays uncompressed/writable;
- configured retention removes only ERP2 debug files older than cutoff;
- unrelated files are left alone;
- cleanup skips symlinks and does not follow them outside managed roots;
- only ERP2-owned `debug-export-*.zip` files older than 24h are deleted;
- unrelated ZIP/files are untouched;
- repeated cleanup is idempotent;
- cleanup CLI reads existing `SystemSettings.debugRetentionDays` and adds no scheduler framework.

Gate:
```text
DEBUG_ROTATION=PASS
DEBUG_RETENTION=PASS
DEBUG_EXPORT_TTL=PASS
DEBUG_CLEANUP_ROOT_BOUND=PASS
```

## F5.3 — Status UI + safe clear — DONE / GREEN

RED:
- `tests/Integration/DebugSettingsControllerTest.php`
- commit `f4c5ec2aaee359c870944b340803a59a87ccec8e`
- QA #315 failed only because usage/history UI and `clearDebug()` did not yet exist.

GREEN:
- `app/Core/Logging/DebugMaintenance.php`
- `app/Modules/Settings/SystemSettingsController.php`
- `app/Modules/Settings/views/system.php`
- `app/Core/Http/Routes.php`
- functional head `7c329071cabda513cee535f06bc51f86c5ec36fb`
- QA #325 SUCCESS.

Proven:
- `/settings/system` remains admin-only;
- admin sees Debug ON/OFF, retention, max storage, total DVR bytes and UTC-day history;
- history only inspects recognized ERP2 DVR filenames and skips symlinks;
- clear mutation is fixed route `/settings/system/debug/clear`;
- clear requires CSRF;
- non-admin cannot view or clear;
- clear deletes only recognized `debug-YYYY-MM-DD.jsonl[.gz]` files;
- unrelated files, normal logs and exports are preserved;
- no user-provided filesystem path is accepted.

Gate:
```text
DEBUG_STATUS_ADMIN_ONLY=PASS
DEBUG_CLEAR_CSRF=PASS
DEBUG_CLEAR_ROOT_BOUND=PASS
```

## F5.4 — Authenticated ZIP export — DONE / GREEN

RED:
- `tests/Integration/DebugExportTest.php`
- initial RED commit `30d0a3f62fd38dbff6fbe348e575d306b91078a4`
- QA #329 failed only because `DebugExportService` did not exist.

GREEN:
- `app/Core/Logging/DebugExportService.php`
- `app/Modules/Settings/SystemSettingsController.php`
- `app/Modules/Settings/views/system.php`
- `app/Core/Http/Routes.php`
- native PHP `PharData` ZIP; no new Composer/runtime dependency;
- functional head after deterministic rate-limit fixture fix: `825d189f169c0c6c0e5a256694b214922b684ad8`
- QA #343 SUCCESS.

Proven:
- only company admin can export;
- POST export requires CSRF;
- fixed route `/settings/system/debug/export` accepts no filesystem path;
- only recognized ERP2 DVR files enter archive;
- unrelated files and symlink targets are excluded;
- output lives only under `storage/exports`;
- generated filename is bounded `debug-export-<safe-id>.zip` and therefore owned by F5.2 TTL cleanup;
- empty DVR history creates no export and returns a bounded empty outcome;
- response is `application/zip` attachment and streams the generated archive in chunks;
- no `.env`, normal application logs, tokens or arbitrary files are selected by the exporter.

Incidental test hardening:
- `MeliRateLimitTest` had a wall-clock race around the existing 0–2 second cooldown jitter;
- test now verifies the real contract: `Retry-After 20s + jitter 0..2s` using before/after bounds;
- no product rate-limit behavior changed.

Gate:
```text
DEBUG_EXPORT_ADMIN_ONLY=PASS
DEBUG_EXPORT_CSRF=PASS
DEBUG_EXPORT_ALLOWLIST=PASS
DEBUG_EXPORT_ROOT_BOUND=PASS
DEBUG_EXPORT_TTL_COMPATIBLE=PASS
```

## F5.5 — Webhook → work → Mercado Libre HTTP correlation — RED / PAUSED HERE

### Design decision already audited

KISS decision: **do not add a new DB column, UUID, migration, tracing subsystem or EventBus.**

Existing identifiers are sufficient:
- `OrderWebhookReceiver::receive()` already receives Mercado Libre `event_id`;
- `WorkRepository::enqueue()` already returns the durable `work_id`;
- `work_id` survives retries and is already present in every claimed work item;
- `DebugRecorder` already allowlists `correlation_id`, `event_id`, `work_id`, `work_type`, `operation`, `request_id`, `resource_id`, `http_status`, `duration_ms` and `outcome`.

Chosen stable correlation identifier:
```text
correlation_id = work:<work_id>
```

This is deterministic, bounded, secret-free and retry-stable.

### RED created

Test:
- `tests/Integration/DebugCorrelationTest.php`
- RED commit: `9f96a4bfa5c95d8394ab5632e9a36e7fe4decdfb`
- QA #347: FAILURE as expected.

The new test proves two scenarios:
1. Debug ON must relate `webhook.accepted` → `work.started` → `meli.http` with the same `correlation_id = work:<id>` and must not expose access token, refresh token or client secret.
2. Debug OFF must preserve the same business result (`work=done`, one physical order HTTP request, persisted order) and create no DVR file.

### Exact RED failure from QA #347

Only the two new correlation tests error.

Error:
```text
Error: Unknown named parameter $debugRecorder
```

First failure point:
```text
tests/Integration/DebugCorrelationTest.php:103
```

This is expected because production constructors have not yet been extended with optional `DebugRecorder` dependencies.

No existing test regression was reported before these two new errors. PHP syntax and PHPStan were green before PHPUnit reached the intentional RED.

### Exact GREEN implementation to do next — DO NOT REDESIGN

Implement only these explicit changes:

1. `OrderWebhookReceiver`
   - add optional `?DebugRecorder $debugRecorder = null` constructor dependency;
   - capture `$workId = $this->work->enqueue(...)`;
   - after successful transaction commit, best-effort record `webhook.accepted` with:
     - `correlation_id = 'work:' . $workId`
     - `event_id`
     - `work_id`
     - `company_id`
     - `account_id`
     - `topic`
     - `resource_id = orderId`
   - DVR failure must never alter webhook/business outcome; debug recording must therefore be best-effort.

2. `OrderSyncWorkProcessor`
   - add optional `?DebugRecorder $debugRecorder = null` constructor dependency;
   - for valid `order.sync` claim, best-effort record `work.started` before calling handler:
     - same `correlation_id = 'work:' . $claim['id']`
     - `work_id`
     - `company_id`
     - `account_id`
     - `work_type`
     - `resource_id = orderId`
   - do not log `claim_token`.

3. `SyncOrderHandler`
   - pass a safe debug context to the existing `MeliClient::request()` for `orders.get` only:
     - `correlation_id = 'work:' . $workId`
     - `work_id`
     - `resource_id = orderId`
   - preserve the same context on the 401-refresh retry.
   - no generic context propagation framework.

4. `MeliClient`
   - add optional `?DebugRecorder $debugRecorder = null` constructor dependency at the end to preserve all existing positional callers;
   - add one optional bounded debug-context argument to `request()` at the end;
   - after a physical transport response is received, best-effort record `meli.http` containing only allowlisted safe fields:
     - `correlation_id`, `work_id`, `resource_id`
     - `operation`
     - `http_status`
     - `duration_ms`
     - `request_id` when present
     - `outcome` = `success|rate_limited|client_error|server_error|invalid_json`
   - never record Authorization header, body, access token, refresh token, client secret, raw response or arbitrary debug context keys.
   - debug recorder exceptions must be swallowed so Debug ON cannot change business outcome.

5. Composition
   - `bin/work.php`: instantiate one `DebugRecorder` from existing `SystemSettings` values and pass the same recorder to `MeliClient` and `OrderSyncWorkProcessor`.
   - HTTP webhook composition in `Routes.php`: instantiate the recorder from existing settings and pass it to `OrderWebhookReceiver`.
   - do not add new configuration keys, tables, migrations or dependencies.

### GREEN verification required

After implementation, run one full QA only.
Expected:
```text
PHPStan = 0 errors
DebugCorrelationTest = GREEN
All existing tests = GREEN
REAL_MELI_HTTP=0
```

Then update this checkpoint with exact functional HEAD + QA run number.

Do **not** start F5.6 until F5.5 is GREEN.

## Remaining F5 order

1. Finish F5.5 GREEN + QA + checkpoint refresh.
2. F5.6 final adversarial gates.
3. Only after all F5 exit gates are GREEN: close F5 implementation work / evaluate PR #14 readiness.
4. Do not start F6 Billing yet.

## Global constraints

- Do not merge/deploy automatically.
- Do not start F6 Billing.
- Keep PR #14 Draft until F5 exit gates are green.
- External Hostinger/main-protection/real-ML-app gates remain separate.
- Keep KISS/YAGNI/TDD.
- Do not add EventBus, OpenTelemetry/tracing framework, new queue state, new migration or generic debug-context subsystem for F5.5.
