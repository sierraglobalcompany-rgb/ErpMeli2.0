# CHECKPOINT — F5 Debug DVR

Date: 2026-10-09
Repository: `sierraglobalcompany-rgb/ErpMeli2.0`
Branch: `impl/f5-debug-dvr-20261009`
Base: `fix/f4-1-stabilization-20261009`
Draft PR: #14 — `F5 — Debug DVR Completo`
Implementation plan: `docs/superpowers/plans/2026-10-09-f5-debug-dvr-implementation.md`
Verified functional HEAD before this checkpoint commit: `db8b97a3ecb8a766a7d98c138015327c1b2a088c`
Latest full QA: run #311 — SUCCESS.

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
- final functional head `db8b97a3ecb8a766a7d98c138015327c1b2a088c`
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

## Exact next block — F5.3

Status UI + safe clear, reusing existing `/settings/system` admin screen.

RED requirements:
- admin-only access remains enforced;
- screen exposes debug ON/OFF, retention, max storage, current usage and history by UTC day;
- clear mutation requires CSRF;
- clear removes only ERP2 DVR files under `storage/debug`;
- clear does not touch `storage/logs`, `storage/exports`, unrelated debug-root files or symlink targets;
- no user-supplied filesystem path is accepted.

Minimal design:
- extend existing `DebugMaintenance` with read-only usage/history + safe clear of recognized debug files only;
- wire through existing `SystemSettingsController` and view;
- add one explicit POST action under existing settings route or one small dedicated admin POST route;
- no new admin/RBAC framework.

Do not open F5.4 before F5.3 GREEN + full QA + checkpoint refresh.

## Remaining F5 order

1. F5.3 status UI + safe clear;
2. F5.4 authenticated ZIP export;
3. F5.5 webhook→work→HTTP correlation;
4. F5.6 final adversarial gates.

## Global constraints

- Do not merge/deploy automatically.
- Do not start F6 Billing.
- Keep PR #14 Draft until F5 exit gates are green.
- External Hostinger/main-protection/real-ML-app gates remain separate.
