# CHECKPOINT — F5 Debug DVR

Date: 2026-10-09
Repository: `sierraglobalcompany-rgb/ErpMeli2.0`
Branch: `impl/f5-debug-dvr-20261009`
Base: `fix/f4-1-stabilization-20261009`
Draft PR: #14 — `F5 — Debug DVR Completo`
Implementation plan: `docs/superpowers/plans/2026-10-09-f5-debug-dvr-implementation.md`

## Stable verified base

Last fully GREEN functional HEAD before current RED work:
- `db8b97a3ecb8a766a7d98c138015327c1b2a088c`
- QA #311 — SUCCESS

Checkpoint after F5.2:
- `71777f7c0f8a6c4710b6d387e81e656e3755244b`

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

# CURRENT LIVE STATE — F5.3 IS RED, DO NOT RECREATE THE RED

Current RED commit:
- `f4c5ec2aaee359c870944b340803a59a87ccec8e`
- test file: `tests/Integration/DebugSettingsControllerTest.php`
- QA #315 — expected FAILURE

The RED is valid and isolated. PHPStan is GREEN and all prior tests still pass. Exactly three new failures exist:

1. `testAdminScreenShowsUsageHistoryAndClearAction`
   - existing page does not yet render `Uso debug`, usage/history or clear action.
2. `testAdminClearRequiresCsrfAndDeletesOnlyRecognizedDebugFiles`
   - `SystemSettingsController::clearDebug()` does not exist yet.
3. `testNonAdminCannotViewOrClearDebugStorage`
   - same missing `clearDebug()` method.

No unrelated regression was found.

## F5.3 RED contract already encoded

The new test requires:

- admin screen keeps existing admin authorization;
- page shows current debug usage bytes;
- page shows recognized DVR history days;
- page renders POST action `/settings/system/debug/clear`;
- CSRF is mandatory for clear;
- clear deletes only recognized files matching:
  - `debug-YYYY-MM-DD.jsonl`
  - `debug-YYYY-MM-DD.jsonl.gz`
- clear must leave intact:
  - unrelated files inside debug root, e.g. `notes.txt`;
  - `storage/logs`;
  - `storage/exports`;
  - symlink targets/outside paths;
- non-admin gets 403 for both view and clear;
- no user-supplied filesystem path is accepted.

## Exact GREEN to implement next — four small edits only

### 1. `app/Core/Logging/DebugMaintenance.php`

Add public read/clear methods without changing F5.2 behavior:

- `status(): array{usage_bytes:int,history:list<array{day:string,bytes:int,compressed:bool}>}`
  - inspect only recognized regular non-symlink debug files;
  - sum their sizes;
  - derive day from filename;
  - sort history newest first.

- `clear(): int`
  - delete only recognized regular non-symlink debug files;
  - no path argument;
  - return deleted count;
  - do not touch unrelated files.

Reuse the existing private `entries()` helper and filename pattern. No new filesystem abstraction.

### 2. `app/Modules/Settings/SystemSettingsController.php`

Constructor currently receives:
- `PDO`
- `SystemSettingsRepository`
- `Csrf`

Add optional/required `DebugMaintenance` dependency for F5 branch.

In `show()` after admin check:
- fetch `$debugStatus = $this->debugMaintenance->status()`;
- keep existing settings/CSRF behavior.

Add:
```text
clearDebug(request, response)
```
Behavior:
- admin check first → 403;
- parse body;
- CSRF failure → 419;
- call `DebugMaintenance::clear()`;
- redirect 303 to `/settings/system`.

Do not add flash/session framework.

### 3. `app/Modules/Settings/views/system.php`

Keep existing form unchanged.
Add a small section below it:
- heading/label `Uso debug`;
- print `<usage_bytes> bytes`;
- list each history day + bytes + compressed/plain marker;
- separate clear form:
  - POST `/settings/system/debug/clear`
  - hidden `csrf_token`
  - button `Limpiar debug`

Escape all rendered strings with `htmlspecialchars`.

### 4. `app/Core/Http/Routes.php`

Add `use App\Core\Logging\DebugMaintenance;`.

When constructing `SystemSettingsController`, inject:
```php
new DebugMaintenance(
    dirname(__DIR__, 3) . '/storage/debug',
    dirname(__DIR__, 3) . '/storage/exports',
)
```

Add explicit:
```text
POST /settings/system/debug/clear
```
using same controller composition and `clearDebug()`.

KISS option to avoid duplicate construction: small local closure/factory inside `register()` is acceptable only if it reduces duplication cleanly; otherwise duplicate four constructor lines. Do not create DI framework.

## After GREEN implementation

1. Run exactly one final QA on the functional GREEN head.
2. Expected targeted result: all 130 tests pass (or more if route-level test added), PHPStan 0 errors, REAL_MELI_HTTP=0.
3. If GREEN, update this checkpoint with:
   - GREEN commit SHA;
   - QA run number;
   - F5.3 gates.
4. Only then start F5.4 authenticated ZIP export.

Do not recreate the RED test and do not restart F5.1/F5.2.

## Remaining F5 order

1. Finish F5.3 status UI + safe clear;
2. F5.4 authenticated ZIP export;
3. F5.5 webhook→work→HTTP correlation;
4. F5.6 final adversarial gates.

## Global constraints

- Do not merge/deploy automatically.
- Do not start F6 Billing.
- Keep PR #14 Draft until F5 exit gates are green.
- No new RBAC/admin framework.
- No new queue/logging/observability framework.
- External Hostinger/main-protection/real-ML-app gates remain separate.
