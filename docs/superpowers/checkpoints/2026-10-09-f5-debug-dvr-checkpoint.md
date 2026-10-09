# CHECKPOINT — F5 Debug DVR

Date: 2026-10-09
Repository: `sierraglobalcompany-rgb/ErpMeli2.0`
Branch: `impl/f5-debug-dvr-20261009`
Base: `fix/f4-1-stabilization-20261009`
Draft PR: #14 — `F5 — Debug DVR Completo`
Implementation plan: `docs/superpowers/plans/2026-10-09-f5-debug-dvr-implementation.md`
Verified functional HEAD before this checkpoint commit: `7c329071cabda513cee535f06bc51f86c5ec36fb`
Latest full QA: run #325 — SUCCESS.

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

## Exact next block — F5.4

Authenticated ZIP export.

RED requirements:
- only authenticated company admin can create/download debug export;
- export includes only recognized ERP2 DVR files selected by safe server-side rules;
- no arbitrary path, traversal or symlink target can enter ZIP;
- export filename follows `debug-export-<safe-id>.zip` so F5.2 TTL cleanup owns it;
- ZIP is created under `storage/exports`, never public storage;
- response is attachment download with bounded/generated filename;
- export does not expose application logs, `.env`, tokens or unrelated files;
- empty DVR history returns a bounded safe outcome and does not create arbitrary files.

Minimal targets:
- one small export service under `app/Core/Logging`;
- one admin controller action + fixed route;
- targeted unit/integration tests;
- reuse existing CSRF/admin checks and `storage/exports`.

Do not open F5.5 before F5.4 GREEN + full QA + checkpoint refresh.

## Remaining F5 order

1. F5.4 authenticated ZIP export;
2. F5.5 webhook→work→HTTP correlation;
3. F5.6 final adversarial gates.

## Global constraints

- Do not merge/deploy automatically.
- Do not start F6 Billing.
- Keep PR #14 Draft until F5 exit gates are green.
- External Hostinger/main-protection/real-ML-app gates remain separate.
