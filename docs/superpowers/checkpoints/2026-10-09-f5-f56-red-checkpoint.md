# CHECKPOINT — F5.6 final adversarial gates in progress

Date: 2026-10-09
Repository: `sierraglobalcompany-rgb/ErpMeli2.0`
Branch: `impl/f5-debug-dvr-20261009`
Base: `fix/f4-1-stabilization-20261009`
Draft PR: #14 — `F5 — Debug DVR Completo`

## Current branch state

Latest branch HEAD at checkpoint creation:

`3f195d46c49727b06e7fd3538851aab15415b46e`

This HEAD contains an intentionally RED final adversarial test refinement. Do not treat it as release-ready.

## Last fully GREEN functional checkpoint

Latest fully verified GREEN functional HEAD before F5.6:

`4c5679feda9f8bf65c0d7c8154003cfa581f25ed`

Full QA:

`#375 — SUCCESS`

Checkpoint after F5.4b:

`docs/superpowers/checkpoints/2026-10-09-f5-after-f54b-checkpoint.md`

## Completed F5 blocks

### F5.1 — GREEN
- safe bounded JSONL recorder
- allowlisted fields
- secret-like unknown fields excluded
- Debug OFF writes no DVR
- storage cap stops verbose debug writes

### F5.2 — GREEN
- UTC-day gzip
- retention
- cleanup CLI
- export TTL cleanup
- root/symlink defenses

### F5.3 — GREEN
- admin-only debug status/history UI
- CSRF-protected clear

### F5.4a — GREEN
- authenticated server-generated ZIP export under `storage/exports`

### F5.4b — GREEN
- selected UTC day/range export
- malformed/reversed/>90-day ranges rejected
- `.jsonl` and `.jsonl.gz` included only when inside range
- `manifest.json`
- manifest metadata: app version, applied schema version, redaction schema version, UTC range
- SHA-256 per selected DVR file
- UI start/end date controls
- backwards-compatible no-range export
- QA #375 SUCCESS

### F5.5 — GREEN
- stable `correlation_id = work:<work_id>`
- webhook → work → physical Mercado Libre HTTP correlation
- no DB column/migration/UUID/EventBus/tracing framework added

## F5.6 — CURRENT BLOCK / NOT GREEN YET

Goal: close only the final requirements not already proven. Do not duplicate previous tests and do not start Billing.

Two concrete gaps were identified during read-only final audit:

1. **Debug cap behavior required by master spec**
   - when cap is reached, verbose DVR must stop;
   - emit one warning in the normal application log;
   - settings UI must visibly alert that the cap was reached.

2. **Real webhook HTTP composition**
   - prove the real `/webhooks/mercadolibre` route actually uses the DVR recorder when Debug is enabled;
   - do not accept a test that passes because another test already wrote into `storage/debug`.

## RED history

Initial F5.6 RED commit:

`cbe994f232d315647b7f4db858c23d1270e6c20a`

Test added:

`tests/Integration/F5FinalAdversarialTest.php`

QA:

`#379 — FAILURE`

Observed intended failure:

- `Unknown named parameter $normalLogger`

Meaning: current `DebugRecorder` still lacks the normal-log warning dependency required for cap warning behavior.

Important audit finding from #379:

- the webhook runtime test was not reliable because it could read existing `storage/debug` content from the same test suite and therefore produce a false green.

## RED refinement

Commit:

`3f195d46c49727b06e7fd3538851aab15415b46e`

Change:

- the webhook runtime test now snapshots the pre-existing debug file and asserts that the request appends **new bytes**;
- assertions are performed only against the newly appended segment;
- existing file content is restored after the test.

QA:

`#381 — FAILURE`

At the exact moment of this checkpoint, the workflow failure has been confirmed but its final log has **not yet been inspected**. Therefore:

- DO NOT guess the cause;
- DO NOT implement GREEN until reading job logs for #381;
- the next action is to inspect only the failing QA job/log and classify whether the failures correspond exactly to the two intended gaps.

## Expected GREEN — only if RED confirms these gaps

### A. Cap warning, KISS implementation

Extend `DebugRecorder` with an optional normal logger dependency, e.g. `?AppLogger $normalLogger = null`.

Behavior:
- when usage is already >= maxBytes, call a private warning-once method then return;
- when the next line would exceed maxBytes, call warning-once then return;
- warning event: `debug.cap.reached`;
- do not write secrets or raw payloads;
- warning must be best-effort and must never break business processing;
- one warning per recorder process instance is enough; no DB state, lock, marker table or new engine.

Wire `AppLogger` into the real worker composition in `bin/work.php`.

### B. UI cap alert, KISS implementation

In `SystemSettingsController::show()` compute:

`debugCapReached = settings.debugEnabled && debugUsage.total_bytes >= settings.debugMaxMb * 1024 * 1024`

Expose the boolean to `views/system.php`.

Render a clear message when true:

`Límite de almacenamiento debug alcanzado`

No new settings table/flag is needed.

### C. Real webhook DVR composition

In `app/Core/Http/Routes.php` real Mercado Libre webhook route:

- instantiate/read `SystemSettingsRepository`;
- read current runtime settings;
- create `DebugRecorder(storage/debug, debugEnabled, debugMaxMb bytes, optional normal AppLogger)`;
- inject it into `OrderWebhookReceiver` using the existing constructor slot already exercised by integration tests;
- keep response semantics unchanged;
- no business logic change.

If constructor signatures differ, inspect current live code and adapt minimally; do not invent a new service container/framework.

## Exact next action after this checkpoint

1. Fetch QA #381 job.
2. Read only its failing log section.
3. Confirm RED causes.
4. Apply the minimal GREEN above only for confirmed failures.
5. Run one fresh full QA.
6. If GREEN, perform final F5 acceptance audit and create a completion checkpoint.
7. Only then decide whether PR #14 can leave Draft.
8. Do **not** start F6 Billing before F5 is formally GREEN.

## Final F5 evidence required

At minimum:

```text
DEBUG_BOUNDED=PASS
SECRET_LEAK=0
BUSINESS_BEHAVIOR_DIFF_ON_OFF=0
PHPSTAN=0
PHPUNIT=PASS
REAL_MELI_HTTP=0
```

Also prove:
- cap warning normal log = PASS
- cap alert UI = PASS
- real webhook route DVR wiring = PASS
- range/manifest/checksums = PASS
- correlation = PASS

## Constraints / anti-overengineering

Do not add:
- EventBus
- telemetry backend
- new log database tables
- cap state table
- cap lock/lease
- generic logging framework
- tracing framework
- new queue/work state
- Redis/RabbitMQ/Kafka

Keep:
- KISS
- YAGNI
- TDD RED → intended failure → minimal GREEN → fresh QA

## External gates still separate

These are not F5 code blockers for continuing implementation but remain release/merge gates:

- Hostinger runtime/preflight verification
- main branch protection
- dedicated real Mercado Libre ERP2 app and callbacks/secrets/reauthorization

No merge or deploy automatically.
