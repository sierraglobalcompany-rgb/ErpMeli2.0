# CHECKPOINT — F5 after F5.4b

Date: 2026-10-09
Repository: `sierraglobalcompany-rgb/ErpMeli2.0`
Branch: `impl/f5-debug-dvr-20261009`
Base: `fix/f4-1-stabilization-20261009`
Draft PR: #14 — `F5 — Debug DVR Completo`

## Latest verified functional HEAD

`4c5679feda9f8bf65c0d7c8154003cfa581f25ed`

Latest full QA:

`#375 — SUCCESS`

## F5.1 — GREEN

Safe bounded JSONL recorder.

## F5.2 — GREEN

UTC-day gzip, retention, export TTL cleanup, root/symlink defenses, cleanup CLI.

## F5.3 — GREEN

Admin debug status/history UI and CSRF-protected safe clear.

## F5.4a — GREEN

Authenticated server-generated ZIP export under `storage/exports`.

## F5.4b — GREEN / MASTER EXPORT SCOPE COMPLETED

RED:
- `tests/Integration/DebugExportRangeManifestTest.php`
- commit `bc785a65ee93345f945d00532dd112494be3da2b`
- QA #367 failed only on the intended missing contracts:
  - unknown `appVersion` metadata support;
  - invalid/excessive ranges not rejected;
  - settings UI had no date-range inputs.

GREEN:
- `86ed89ca669a9c4e2fbd3bfd85e036c5d356ace9` — ranged export + manifest/checksums;
- `c9d149f561030eae54fa3603825a1765582742d0` — controller range validation + actual schema version;
- `ec71c81de10276c9bb9556de740f32771a57ab92` — UTC date-range UI;
- QA #373 failed only on redundant PHPStan nullability checks;
- `4c5679feda9f8bf65c0d7c8154003cfa581f25ed` — analysis-only nullability repair;
- QA #375 SUCCESS.

Proven:
- one UTC day works as start=end;
- UTC start/end range works;
- malformed, reversed and >90-day ranges are rejected;
- only recognized DVR files within selected range enter the ZIP;
- both `.jsonl` and `.jsonl.gz` are supported;
- ZIP includes `manifest.json`;
- manifest includes app version, actual applied schema version, redaction schema version, UTC range, selected filenames and SHA-256 per selected DVR file;
- no user filesystem path is accepted;
- existing admin/CSRF/root/symlink controls remain in force;
- UI exposes start/end dates; week/month are represented by a range and no duplicate weekly/monthly archive is persisted;
- old no-range call remains backwards compatible and exports recognized DVR history only.

## F5.5 — GREEN

Stable `correlation_id = work:<work_id>` links webhook → work → physical Mercado Libre HTTP.

Functional GREEN HEAD before F5.4b: `eee11b2b0892c548136c3b8fba4b46ccebfb8c96`.
QA #361 SUCCESS.

No DB column, migration, UUID, EventBus or tracing framework was added.

## EXACT NEXT BLOCK — F5.6 FINAL ADVERSARIAL GATES

Do not start Billing yet.

First perform a read-only gap audit against BOTH:
- `ERP_MELI_2_0_PLAN_MAESTRO_PROYECTO_2026-10-08.md`;
- `ERP_MELI_2_0_ESPECIFICACION_MAESTRA_FINAL_2026-10-08.md`.

Then add tests only for requirements not already proven.

Required final evidence at minimum:

```text
DEBUG_BOUNDED=PASS
SECRET_LEAK=0
BUSINESS_BEHAVIOR_DIFF_ON_OFF=0
PHPSTAN=0
PHPUNIT=PASS
REAL_MELI_HTTP=0
```

Existing coverage already proves major gates for:
- allowlist / secret-like unknown fields;
- Debug OFF no DVR;
- cap stops verbose writes;
- retention;
- gzip;
- clear root bounds;
- export auth/CSRF/root bounds/TTL;
- range + manifest + checksums;
- webhook→work→HTTP correlation;
- ON/OFF flow parity.

Important final-audit question to resolve before declaring F5 complete:
- Master Specification says reaching the cap must emit one warning in normal log and show an alert in UI. Current recorder visibly stops at cap, but this exact automatic warning/UI behavior has not yet been proven by a RED/GREEN test. Treat this as a possible F5.6 gap, not as already complete.

Do not duplicate tests that already prove a gate.
Do not create a new logging framework, EventBus, telemetry backend or cleanup engine.

## Constraints

- PR #14 stays Draft until F5 final gates pass.
- No merge/deploy automatically.
- No F6 Billing until F5 is formally closed.
- External Hostinger, main-protection and real Mercado Libre app gates remain separate.
- KISS / YAGNI / TDD.
