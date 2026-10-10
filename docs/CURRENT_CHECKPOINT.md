# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-a-exact-boundary-20261009`  
**Base product SHA:** `4d144b9744160c3b1ddfa542af74040b262dec42`  
**V3-A implementation SHA:** `36cb45bd3bd200009e1b87c2462f326c246a9a84`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Plan executed:** `docs/superpowers/plans/2026-10-09-v3-a-exact-boundary-work-safety.md`  
**Remote writes:** OFF  
**Real Mercado Libre HTTP in QA:** OFF

## Current state

V3-A — exact remote boundary + Work safety is closed at implementation SHA `36cb45bd3bd200009e1b87c2462f326c246a9a84`.

The branch still starts from the clean product base rather than the old forensic V3 audit branch. No merge, deploy or remote write has been performed.

## Fresh full QA gate

GitHub Actions run `38011395248`, job `114091991102`, against implementation SHA `36cb45bd3bd200009e1b87c2462f326c246a9a84`:

```text
PHP=8.5.11
PHPSTAN=0
PHPUNIT=159/159 PASS
ASSERTIONS=1057
MEMORY=20 MB
REAL_MELI_HTTP=0
```

The earlier cleanup import warnings were removed before this final gate. The fresh final run reports `bin/cleanup.php` with no syntax warning.

## V3-A completed scope

### A1-A2 — exact JSON number boundary

- RED proved that literal JSON NUMBER `90071992547409.1234` lost precision through native decode.
- Added a small lexical `LosslessJsonDecoder`; it preserves NUMBER lexemes outside JSON strings and delegates structure parsing to native `json_decode`.
- Lossless number preservation is enabled only for operations that require exact decimal payloads, including `orders.get` and `billing.period.details`.
- No general JSON parser/AST was added.

### A3 — strict remote scalar/date boundary

- New order sync requires `date_created` and an explicit `Z`/offset.
- Present-but-invalid nullable remote timestamps fail closed.
- Exact money path rejects PHP `float`.
- DECIMAL(18,4) overflow after rounding fails closed.
- Existing handler helpers were hardened; no premature generic value-normalizer abstraction was added.

### A4 — fail-closed operation classification

Allowed registry classifications are exactly:

```text
READ
AUTH
WRITE
```

Unknown or missing classification is blocked before transport. `WRITE` still requires the internal writes fuse to be enabled.

### A5 — bounded Work retry

- Fixed automatic attempt cap: `5`.
- `retryCurrentClaim()` consumes the claim attempt and becomes terminal `failed` when the cap is exhausted.
- `deferCurrentClaim()` requeues without consuming attempt budget.
- Interrupted `running` work below cap returns to `pending`; at cap it becomes terminal `failed`.
- No RetryEngine, policy table or configurable retry switch was added.

### A6 — 429 semantics

Current Sales handlers route Mercado Libre 429/cooldown to `deferCurrentClaim()`.

```text
429/cooldown -> defer -> no attempt consumed
5xx/transport -> retry -> attempt consumed
```

No inline retry loop or per-domain retry engine was added.

### A7 — premature remote-write control removed

- `meli_writes_enabled` remains in DB as an internal fail-closed fuse.
- System UI no longer exposes the Mercado Libre write checkbox.
- Admin settings POST can no longer set the write fuse to `1`.
- Existing safe-setting invariants were preserved: allowed debug retention values, debug storage bounds, update-count guard and fail-closed boolean decoding.

During A7 review an intermediate whole-file edit had accidentally weakened those invariants and used stale controller method names. The audit caught the regression before closure, restored the real pre-A7 contracts, then reapplied only the intended write-switch removal. Final A7 gate was green before A8 began.

### A8 — bounded operational retention

Existing owners and the existing daily cleanup were extended only:

```text
Debug retention              -> existing DebugMaintenance
Work done/failed >30 days    -> WorkRepository::purgeTerminalBefore()
API usage >90 days           -> ApiUsageRecorder::purgeBefore()
```

Rules proven by tests:

- Work age is determined by `finished_at`.
- Only `done`/`failed` with a demonstrably old `finished_at` are deleted.
- `pending` and `running` are never removed by this purge.
- terminal legacy rows with `finished_at IS NULL` are kept fail-safe.
- cutoffs are strict `< cutoff`; exactly 30/90 days old remains.
- `api_usage_daily` is purged by `usage_date`.
- `bin/cleanup.php` uses one UTC `now` for the daily run.
- no MaintenanceEngine, archive table, migration or new scheduler was added.

## Macro gate status after V3-A

| Gate | Status | Evidence / boundary |
|---|---|---|
| `G1 REMOTE_TRUTH` | PASS for V3-A boundary | literal JSON NUMBER lossless path, float rejection, required zoned `date_created` |
| `G2 WORK_SAFETY` | PASS | cap 5, defer semantics, crash recovery, terminal retention |
| `G3 RATE_SAFETY` | PASS for implemented Sales paths | 429/cooldown defers; 5xx/transport consume bounded retry |
| `G4 SALES_AUDIT_TRUTH` | NOT STARTED | next implementation block is V3-B |
| `G5 BILLING_CURSOR_TRUTH` | BLOCKED | requires C0 real sanitized MCO cursor smoke before Billing handler GREEN |
| `G6 FINANCIAL_NO_DOUBLE_COUNT` | NOT STARTED | belongs to later Financial block |
| `G7 WRITE_FAIL_CLOSED` | PASS | classification allowlist + internal fuse + no pre-F16 UI/admin write path |
| `G8 HOSTING_REALITY` | NOT CERTIFIED | real Hostinger operational limits/gates remain outside V3-A |

Always-on gate at this checkpoint:

```text
PHPSTAN=0
PHPUNIT=PASS
REAL_MELI_HTTP=0
```

## Architectural footprint added by V3-A

Kept intentionally small:

- one focused `LosslessJsonDecoder`;
- bounded/defer behavior inside the existing `WorkRepository`;
- stricter validation inside existing Sales/Meli boundaries;
- two retention methods on existing table owners;
- wiring inside existing `bin/cleanup.php`;
- focused regression/integration tests.

Not added:

```text
new queue
new scheduler
retry engine
maintenance engine
money parser framework
generic historical engine
archive tables
new migration for retention
remote write engine
```

## Next planned block — NOT STARTED

```text
V3-B — Sales audit único
```

Target authority remains:

```text
CAPTURE -> VALIDATE -> REPAIR -> VERIFY -> CONFIRM
```

Do not begin V3-B code from this checkpoint as part of the V3-A plan. Start it only as the next approved/executed block with its own RED -> GREEN -> QA -> checkpoint sequence.

## Active blockers / stop conditions

- `F6A Task 2` remains blocked until C0.
- Billing handler GREEN remains blocked until a real sanitized MCO cursor-terminal smoke proves the remote terminal signal.
- No merge.
- No deploy.
- No real Mercado Libre HTTP except a separately authorized smoke.
- No remote writes.
- Do not enable `meli_writes_enabled` before F16.
