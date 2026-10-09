# F5 — Debug DVR Completo — Implementation Plan

Date: 2026-10-09
Branch: `impl/f5-debug-dvr-20261009`
Base: `fix/f4-1-stabilization-20261009`
Authority: approved ERP MELI 2.0 Master Plan, F5 Debug DVR.

## Goal

Add bounded, privacy-safe diagnostics to real ERP2 flows without changing business results or introducing a second execution/logging architecture.

Exit gates:

```text
DEBUG_BOUNDED=PASS
SECRET_LEAK=0
BUSINESS_BEHAVIOR_DIFF_ON_OFF=0
```

## Existing assets to reuse

- `SystemSettings.debugEnabled`
- `SystemSettings.debugRetentionDays`
- `SystemSettings.debugMaxMb`
- existing admin `/settings/system` UI and CSRF/auth
- existing `AppLogger` for minimal operational warnings/errors
- existing `storage/debug` and `storage/exports` roots from `.gitignore`
- existing webhook → work → Sales flow and `WorkRepository`
- existing MeliClient / Sales handlers

No Redis, queue replacement, EventBus, logging framework, generic observability platform or external telemetry service.

## Blocking discipline

Each block is independent and must end before the next begins:

1. one RED commit;
2. confirm intended failure once;
3. minimal GREEN commit(s);
4. one full QA on the final functional commit;
5. update F5 checkpoint;
6. only then open the next block.

Do not poll CI continuously. If a RED/GREEN run is still active, work only on read-only analysis for the next block.

---

## F5.1 — Safe bounded JSONL recorder

### Purpose

Create the smallest reusable verbose recorder. Keep normal `AppLogger` warnings/errors independent so they continue even when verbose debug reaches its cap.

### RED tests

Create `tests/Unit/DebugRecorderTest.php` proving:

- Debug OFF writes nothing.
- Debug ON writes exactly one UTC-day `.jsonl` file.
- output is valid JSONL.
- only explicitly allowed field names are persisted.
- secret-like and PII-like unknown fields (`authorization`, token/password/email/name/address/phone) never appear.
- nested/unstructured payloads are not persisted.
- max-byte cap stops verbose writes.
- reaching verbose cap does not prevent normal `AppLogger::warning()` in its own log root.

### Minimal implementation

Create:

- `app/Core/Logging/DebugRecorder.php`

Contract:

- constructor receives debug directory, enabled flag and max bytes;
- `record(event, fields, at?)`;
- one file `debug-YYYY-MM-DD.jsonl` UTC;
- central scalar field-key allowlist;
- drop unknown fields rather than redact-and-store them;
- no raw request/response body support;
- no exception stack/body dumping;
- no directory traversal inputs.

No database changes.

### Gate

```text
DEBUG_RECORDER_SAFE=PASS
DEBUG_OFF_NO_FILE=PASS
DEBUG_CAP_NO_VERBOSE_WRITE=PASS
```

Checkpoint after GREEN.

---

## F5.2 — Rotation, gzip, retention and cleanup CLI

### RED tests

Create integration/unit coverage proving:

- closed UTC-day JSONL is gzip-compressed;
- current-day JSONL stays writable/uncompressed;
- retention deletes only debug files older than configured cutoff;
- debug cleanup never escapes `storage/debug`;
- export cleanup deletes exports older than 24h only;
- repeated cleanup is idempotent.

### Minimal implementation

Create:

- `app/Core/Logging/DebugMaintenance.php`
- `bin/cleanup.php`

Responsibilities:

- gzip prior-day `.jsonl` → `.jsonl.gz`;
- retention cleanup;
- export TTL cleanup;
- safe root-bound paths only.

No cron scheduler abstraction; Hostinger cron invokes the CLI directly.

Checkpoint after GREEN.

---

## F5.3 — Debug status UI + safe clear

Reuse `/settings/system`.

### RED tests

Prove admin-only + CSRF for mutations and display of:

- debug ON/OFF;
- retention;
- max storage;
- current usage;
- history by UTC day;
- clear debug action.

Prove clear:

- deletes only files under `storage/debug`;
- does not delete `storage/logs`, `storage/exports` or arbitrary supplied paths;
- cannot use traversal/path parameters.

### Minimal implementation

Add a small read/maintenance service to the existing settings controller/view. Do not build a new admin framework.

Checkpoint after GREEN.

---

## F5.4 — Authenticated ZIP export

### RED tests

Prove:

- only company admin can create/download export;
- CSRF on creation;
- selected day/range only;
- archive contains debug files + `manifest.json`;
- manifest contains SHA-256 checksums;
- no source path traversal;
- export file TTL is 24h;
- invalid/missing export returns safe 404/403;
- archive does not include operational logs, `.env`, storage outside debug, or arbitrary paths.

### Minimal implementation

Use `ZipArchive` only. If CI/runtime extension is missing, add/check `ext-zip`; do not add a ZIP library.

Store generated archives only in `storage/exports` with server-generated opaque names.

Checkpoint after GREEN.

---

## F5.5 — Correlation: webhook → work → HTTP

Only after recorder/storage/export are stable.

### RED tests

One end-to-end test must prove a non-PII correlation identifier links:

```text
webhook accepted
→ work claimed/executed
→ Mercado Libre HTTP outcome
```

while:

- Debug OFF and Debug ON produce the same DB/business result;
- Debug OFF emits no verbose DVR records;
- Debug ON emits correlated records;
- no access token, authorization header, buyer data, email, address, phone, raw ML body or raw webhook body appears.

### Minimal instrumentation

Prefer existing identifiers (`work_id`, event id / safe correlation id, operation key) and optional DebugRecorder dependency at current boundaries. Do not introduce EventBus/Tracing SDK/Context container.

Checkpoint after GREEN.

---

## F5.6 — Final gates

Run full QA and targeted adversarial checks:

- secrets never written;
- PII never written;
- ON/OFF business parity;
- cap;
- retention;
- safe clear;
- traversal attempts;
- export auth;
- export TTL;
- gzip;
- webhook→work→HTTP correlation.

Final evidence:

```text
DEBUG_BOUNDED=PASS
SECRET_LEAK=0
BUSINESS_BEHAVIOR_DIFF_ON_OFF=0
PHPSTAN=0
PHPUNIT=PASS
REAL_MELI_HTTP=0
```

Then update checkpoint/PR. Do not merge/deploy automatically. F6 Billing remains untouched until these gates are green.
