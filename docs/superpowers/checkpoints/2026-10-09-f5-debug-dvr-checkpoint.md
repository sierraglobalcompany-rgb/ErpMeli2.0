# CHECKPOINT — F5 Debug DVR

Date: 2026-10-09
Repository: `sierraglobalcompany-rgb/ErpMeli2.0`
Branch: `impl/f5-debug-dvr-20261009`
Base: `fix/f4-1-stabilization-20261009`
Draft PR: #14 — `F5 — Debug DVR Completo`
Implementation plan: `docs/superpowers/plans/2026-10-09-f5-debug-dvr-implementation.md`
Verified functional HEAD before this checkpoint commit: `4a4492248b35ea4adfc2a8e638b44dd40bf23c90`
Latest full QA: run #303 — SUCCESS.

## F5.1 — Safe bounded JSONL recorder — DONE / GREEN

RED:
- file: `tests/Unit/DebugRecorderTest.php`
- commit: `1464469b13d38d5a39e662bbdcb3ac9c6280b2d6`
- QA #300 failed only because `App\Core\Logging\DebugRecorder` did not exist.

GREEN:
- file: `app/Core/Logging/DebugRecorder.php`
- commit: `4a4492248b35ea4adfc2a8e638b44dd40bf23c90`
- QA #303 SUCCESS.

Behavior proven:
- Debug OFF writes no DVR file.
- Debug ON writes UTC-day `debug-YYYY-MM-DD.jsonl`.
- each line is structured JSON.
- only a central allowlist of safe scalar diagnostic fields can be persisted.
- unknown fields and nested payloads are dropped.
- obvious secret/PII keys and values supplied through unknown fields are not persisted.
- identifier-like values are format bounded; free-form text is not accepted into identifier fields.
- verbose debug stops when the configured byte cap is reached.
- normal `AppLogger` warnings remain independent and continue after verbose cap.
- no DB migration, dependency, logging framework or remote telemetry added.

F5.1 gate:
```text
DEBUG_RECORDER_SAFE=PASS
DEBUG_OFF_NO_FILE=PASS
DEBUG_CAP_NO_VERBOSE_WRITE=PASS
```

## Exact next block — F5.2

Rotation, gzip, retention and cleanup CLI.

RED requirements:
- prior UTC-day `.jsonl` is gzip-compressed;
- current UTC-day `.jsonl` stays uncompressed/writable;
- retention removes only debug files older than cutoff;
- cleanup cannot escape debug root;
- exports older than 24h are removed only from export root;
- repeated cleanup is idempotent.

Minimal targets:
- `app/Core/Logging/DebugMaintenance.php`
- `bin/cleanup.php`
- targeted unit/integration tests.

Do not open F5.3 before F5.2 GREEN + full QA + checkpoint refresh.

## Remaining F5 order

1. F5.2 rotation/gzip/retention/cleanup;
2. F5.3 status UI + safe clear;
3. F5.4 authenticated ZIP export;
4. F5.5 webhook→work→HTTP correlation;
5. F5.6 final adversarial gates.

## Global constraints

- Do not merge/deploy automatically.
- Do not start F6 Billing.
- Keep PR #14 Draft until F5 exit gates are green.
- External Hostinger/main-protection/real-ML-app gates remain separate.
