# CHECKPOINT — F5 Debug DVR

Date: 2026-10-09
Repository: `sierraglobalcompany-rgb/ErpMeli2.0`
Branch: `impl/f5-debug-dvr-20261009`
Base: `fix/f4-1-stabilization-20261009`
Draft PR: #14 — `F5 — Debug DVR Completo`
Implementation plan: `docs/superpowers/plans/2026-10-09-f5-debug-dvr-implementation.md`
Master authority checked against:
- `ERP_MELI_2_0_PLAN_MAESTRO_PROYECTO_2026-10-08.md`
- `ERP_MELI_2_0_ESPECIFICACION_MAESTRA_FINAL_2026-10-08.md`

## EMERGENCY PAUSE — EXACT RESUME POINT

Paused intentionally before creating any F5.4b RED tests.

Current branch HEAD before this checkpoint commit:
`4f60a15c508fd58690d23575979ce34af673dfa0`

Latest fully GREEN functional HEAD:
`eee11b2b0892c548136c3b8fba4b46ccebfb8c96`

Latest full QA:
`#361 — SUCCESS`

Since commit `4f60a15...`, only read-only auditing was performed. No production/test/source files were changed after the F5.5 GREEN checkpoint.

The audit re-confirmed from the Master Plan / Master Specification that F5 export must support:
- download one UTC day;
- download UTC date range;
- week/month represented as ranges, not duplicate stored archives;
- ZIP containing selected DVR daily files plus `manifest.json`;
- manifest containing checksums, UTC range, app version, schema version and redaction schema version.

Important: **F5.4b RED has NOT been created yet.**

Exact next action when resuming:
1. extend `tests/Integration/DebugExportTest.php` only;
2. create RED proving date range filtering + manifest/checksums + invalid range rejection;
3. confirm intentional RED once;
4. minimally extend existing `DebugExportService`, `SystemSettingsController` and `views/system.php`;
5. one full QA;
6. checkpoint again;
7. then F5.6 final adversarial gates.

Do not repeat F5.5. Do not start F5.6. Do not start F6 Billing.

## F5.1 — Safe bounded JSONL recorder — DONE / GREEN

Evidence:
- RED `1464469b13d38d5a39e662bbdcb3ac9c6280b2d6`
- GREEN `4a4492248b35ea4adfc2a8e638b44dd40bf23c90`
- QA #303 SUCCESS

Proven:
- Debug OFF writes nothing.
- Debug ON writes UTC-day JSONL.
- strict allowlist; raw/nested/PII-like unknown fields dropped.
- cap stops verbose writes.
- normal AppLogger remains independent.

## F5.2 — Rotation / gzip / retention / cleanup — DONE / GREEN

Evidence:
- RED `8004aef6ef5b77f10f58f5460abb3f556bb155fb`
- GREEN functional head `db8b97a3ecb8a766a7d98c138015327c1b2a088c`
- QA #311 SUCCESS

Proven:
- closed UTC days gzip.
- current day remains writable.
- retention root-bound.
- export TTL 24h cleanup.
- symlinks/traversal not followed.
- cleanup idempotent.

## F5.3 — Status UI + safe clear — DONE / GREEN

Evidence:
- RED `f4c5ec2aaee359c870944b340803a59a87ccec8e`
- GREEN functional head `7c329071cabda513cee535f06bc51f86c5ec36fb`
- QA #325 SUCCESS

Proven:
- admin-only status.
- current usage + history by UTC day.
- clear requires CSRF.
- clear only recognized DVR files.
- no user filesystem paths.

## F5.4a — Basic authenticated ZIP export — GREEN, BUT MASTER SCOPE INCOMPLETE

Evidence:
- RED `30d0a3f62fd38dbff6fbe348e575d306b91078a4`
- GREEN functional head `825d189f169c0c6c0e5a256694b214922b684ad8`
- QA #343 SUCCESS

Already proven:
- admin-only + CSRF.
- fixed route.
- output only under `storage/exports`.
- recognized DVR files only; unrelated/symlink targets excluded.
- bounded server-generated ZIP name.
- TTL-compatible.
- streamed response.

### IMPORTANT audit correction

Master Plan / Master Specification require additional export scope not yet implemented by F5.4a:

UI:
- download one day;
- download date range;
- week/month represented as date ranges.

Export:
- ZIP contains `manifest.json` + selected daily debug files;
- manifest includes checksums;
- specification also calls for app version, schema version, UTC range and redaction schema version.

Therefore F5.4 must NOT be considered fully closed yet.

## F5.5 — Webhook → work → physical HTTP correlation — DONE / GREEN

RED:
- `tests/Integration/DebugCorrelationTest.php`
- commit `9f96a4bfa5c95d8394ab5632e9a36e7fe4decdfb`
- QA #347 failed only because optional DebugRecorder dependencies did not yet exist.

GREEN implementation commits:
- `07de2dd5f0c52a87a1a1e87b4a8fcf664c4f0a33` — bounded work correlation context in DebugRecorder.
- `087de2eb0811ee32b69866289454854b4f723ed5` — webhook.accepted correlation.
- `7fcf08475e4bd0b3011b6865f8f94a3b52919f6e` — work.started scoped correlation.
- `2db2fee56f8a9f64e6ea05134e96daedf6d32726` — physical MeliClient HTTP events.
- `dd969d529283206c975b652cd4aed3371a969045` — worker runtime wiring.
- `eee11b2b0892c548136c3b8fba4b46ccebfb8c96` — PHPStan-only annotation repair.

QA history:
- #359 failed only on PHPStan shape annotation for empty work context.
- #361 SUCCESS after annotation-only repair.

Design implemented:
- stable correlation: `correlation_id = work:<work_id>`.
- no DB column, UUID, migration, EventBus or tracing framework.
- DebugRecorder has tiny process-local current-work context.
- OrderSyncWorkProcessor sets context and clears it in `finally`.
- MeliClient records at the physical HTTP boundary.
- OrderWebhookReceiver records only after transaction commit.
- debug recorder failures are best-effort and cannot alter business outcomes.

Proven:
- Debug ON links `webhook.accepted` → `work.started` → `meli.http`.
- Debug OFF gives same work/order/HTTP result and creates no DVR file.
- access token, refresh token and client secret do not appear.

Gate:
```text
DEBUG_CORRELATION=PASS
DEBUG_ON_OFF_FLOW_PARITY=PASS
DEBUG_CORRELATION_SECRET_SAFE=PASS
```

## EXACT NEXT BLOCK — F5.4b export completeness

RED requirements, limited strictly to Master requirements:
1. server accepts one UTC day or bounded UTC start/end range;
2. start > end / invalid date / malformed input is rejected safely;
3. only DVR files whose UTC day is in selected range enter archive;
4. `.jsonl` and `.jsonl.gz` are both supported;
5. ZIP includes `manifest.json`;
6. manifest contains UTC start/end, selected file names, SHA-256 for every selected file, app version, schema version and redaction schema version;
7. no path is supplied by user;
8. existing admin + CSRF + root/symlink defenses remain intact;
9. UI provides start/end date inputs; day = start=end; week/month are ranges only.

KISS constraints:
- extend existing DebugExportService/controller/view only;
- no export table;
- no scheduler/export engine;
- no permanent weekly/monthly archives;
- keep native ZIP approach already proven unless evidence forces a change.

## F5.6 — NOT STARTED

Final checks:
- secrets never written;
- PII never written;
- ON/OFF business parity;
- cap;
- retention;
- safe clear;
- traversal attempts;
- ZIP auth;
- ZIP TTL;
- gzip;
- webhook→work→HTTP correlation.

Required final evidence:
```text
DEBUG_BOUNDED=PASS
SECRET_LEAK=0
BUSINESS_BEHAVIOR_DIFF_ON_OFF=0
PHPSTAN=0
PHPUNIT=PASS
REAL_MELI_HTTP=0
```

## Global constraints

- Do not merge/deploy automatically.
- Keep PR #14 Draft until F5 exit gates are green.
- Do not start F6 Billing.
- External Hostinger/main-protection/real Mercado Libre app gates remain separate.
- KISS/YAGNI/TDD.
