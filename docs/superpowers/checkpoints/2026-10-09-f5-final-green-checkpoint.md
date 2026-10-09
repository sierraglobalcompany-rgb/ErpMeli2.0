# CHECKPOINT FINAL — F5 Debug DVR GREEN

Date: 2026-10-09
Repository: `sierraglobalcompany-rgb/ErpMeli2.0`
Branch: `impl/f5-debug-dvr-20261009`
Base: `fix/f4-1-stabilization-20261009`
Draft PR: #14 — `F5 — Debug DVR Completo`

## FINAL FUNCTIONAL HEAD

`df5a6818009bcd35c4c9a35a1d756f40f2023ec5`

PR #14 at closure checkpoint:
- open
- Draft
- not merged
- mergeable
- base remains F4 stabilization

No merge or deploy was performed.

## FINAL QA EVIDENCE

GitHub Actions:
- workflow: `qa`
- run: `#393`
- run id: `37959046600`
- job id: `113916879594`
- conclusion: `SUCCESS`
- PHP: `8.5.11`
- MariaDB: `11.4`
- `REAL_MELI_HTTP=0`
- PHPStan: `[OK] No errors`
- PHPUnit: `OK (140 tests, 911 assertions)`

The 404 stack printed by `BootstrapTest::testStoragePathIsNotExposedAsApplicationRoute` is expected and the suite remains green.

## F5 STATUS — COMPLETE

### F5.1 — safe bounded JSONL recorder — GREEN

Implemented and proven:
- Debug OFF writes nothing.
- Debug ON writes UTC-day JSONL.
- strict field allowlist.
- raw/nested/unapproved fields are discarded.
- secrets/PII-like unknown fields are not persisted.
- global DVR byte cap stops verbose writes.
- normal AppLogger remains separate.

QA evidence: #303 SUCCESS.

### F5.2 — gzip / retention / cleanup — GREEN

Implemented and proven:
- closed UTC days gzip.
- current UTC day remains writable.
- configured retention.
- export TTL 24h cleanup.
- symlinks and traversal are not followed.
- cleanup is idempotent.
- CLI `bin/cleanup.php` composes existing settings and maintenance only.

QA evidence: #311 SUCCESS.

### F5.3 — status UI + safe clear — GREEN

Implemented and proven:
- admin-only debug status.
- usage bytes + history by UTC day.
- clear requires CSRF.
- clear removes only recognized DVR files.
- no user-supplied filesystem path.

QA evidence: #325 SUCCESS.

### F5.4a — authenticated ZIP export — GREEN

Implemented and proven:
- admin-only + CSRF.
- fixed route.
- ZIP only under `storage/exports`.
- only recognized DVR files.
- unrelated files/symlink targets excluded.
- server-generated bounded filename.
- streamed response.
- TTL-compatible.

QA evidence: #343 SUCCESS.

### F5.4b — day/range + manifest/checksums — GREEN

Implemented and proven:
- one UTC day supported.
- bounded UTC start/end range supported.
- malformed dates and start>end rejected.
- excessive ranges rejected.
- `.jsonl` and `.jsonl.gz` selected by UTC day.
- week/month represented as ranges, not permanent duplicate archives.
- ZIP includes `manifest.json`.
- manifest includes:
  - UTC start/end;
  - selected file names;
  - SHA-256 per selected file;
  - app version;
  - schema version;
  - redaction schema version.
- settings UI exposes start/end date fields.

QA evidence: #375 SUCCESS.

### F5.5 — webhook → work → physical HTTP correlation — GREEN

Implemented and proven:
- stable correlation id is `work:<work_id>`.
- webhook acceptance records the work correlation after DB commit.
- worker establishes/clears bounded process-local work context.
- physical MeliClient HTTP boundary records correlated events.
- no new DB column, UUID, migration, tracing framework or event bus.
- Debug OFF preserves business result and writes no DVR file.
- tokens/secrets are not written.

QA evidence: #361 SUCCESS.

### F5.6 — final adversarial gates — GREEN

RED evidence:
- `cbe994f232d315647b7f4db858c23d1270e6c20a`
- deterministic webhook test repair: `3f195d46c49727b06e7fd3538851aab15415b46e`
- RED QA #381 failed only because `DebugRecorder` did not yet accept `normalLogger`.

GREEN implementation:
- `f82ea791eb4a94a1072e6899ad6d486e99214717` — cap emits one best-effort normal warning per recorder process.
- `cd4102a676aaf7cee57ebf7c991ab07c4ea609c1` — settings controller computes cap reached.
- `3210b0827b2d3877cf927807db239d4d1f425cf2` — UI displays `Límite de almacenamiento debug alcanzado`.
- `286f7b398bdc47309b14aec15f87c3242d1a1088` — actual webhook route composes DebugRecorder + AppLogger from runtime settings.
- `df5a6818009bcd35c4c9a35a1d756f40f2023ec5` — worker runtime composes the same cap warning path.

Final QA #393 SUCCESS.

Proven final adversarial behavior:
- cap is hard and DVR stops writing when full.
- cap emits only one normal warning per recorder process.
- settings UI exposes a cap alert.
- real HTTP webhook runtime appends its own `webhook.accepted` DVR entry when Debug is enabled.
- test checks only bytes appended by the current request, avoiding false green from pre-existing files.
- observability failures remain best-effort and cannot alter webhook/business outcome.

## FINAL F5 EXIT GATES

```text
DEBUG_BOUNDED=PASS
SECRET_LEAK=0
BUSINESS_BEHAVIOR_DIFF_ON_OFF=0
PHPSTAN=0
PHPUNIT=PASS
REAL_MELI_HTTP=0
```

Additional verified capabilities:
- bounded storage cap;
- normal cap warning + UI warning;
- gzip;
- retention;
- safe clear;
- safe root-bound export;
- export TTL;
- day/range export;
- manifest + SHA-256;
- admin + CSRF controls;
- webhook→work→HTTP correlation;
- Debug ON/OFF business parity.

## GLOBAL CONSTRAINTS PRESERVED

- KISS/YAGNI/TDD.
- no EventBus/CommandBus/CQRS.
- no observability/tracing framework.
- no export table or scheduler engine.
- no new queue/state model.
- no Billing code in F5.
- no merge/deploy performed.

## EXACT NEXT ACTION

F5 is technically complete on its Draft branch.

When work resumes:
1. do NOT repeat F5 tests/design unless new evidence appears;
2. keep PR #14 Draft unless merge/integration is explicitly authorized;
3. start F6 Billing only as a new stacked branch from the final F5 head after reading the exact Master Plan/Specification Billing scope;
4. create an F6 implementation plan before production code;
5. use microblocks RED → GREEN → one QA → checkpoint.

External production gates remain separate: Hostinger runtime/preflight, real Mercado Libre application configuration, callbacks/webhook reachability, and repository/main protection are not resolved by F5.
