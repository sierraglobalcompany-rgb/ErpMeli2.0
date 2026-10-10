# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Current implementation SHA:** `62954301183f487382d79ca119e965d2165c6229`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**V3-B plan:** `docs/superpowers/plans/2026-10-10-v3-b-sales-audit.md`  
**Remote writes:** OFF  
**Real Mercado Libre HTTP in QA:** OFF

## Current state

V3-A remains closed and green. Before starting Sales Audit, the branch was audited against authority, code, diff and QA to detect gaps left by the previous chat blockage.

One concrete gap was found and closed in B0: V3.2 required native `orders.search` decoding with `JSON_BIGINT_AS_STRING`, while the final V3-A `MeliClient` native branch did not include that flag. No other skipped A1-A8 task or final-contract regression was found in the audited owners.

V3-B is being executed in deliberately small RED -> minimum GREEN -> full QA -> checkpoint blocks.

## B0 — preentry audit gap CLOSED

RED `6be2cb8060bbeb2cd3103722c4ea3b29d5c8d3e7` proved a literal JSON integer larger than `PHP_INT_MAX` became float/scientific notation in `orders.search`.

GREEN `b05bb8bd3a4f0eb67dc821cd8878b84e4e89931f` changed only native decode to:

```text
JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING
```

Fresh QA — run `38012299105`, job `114094780325`:

```text
PHP=8.5.11
PHPSTAN=0
PHPUNIT=160/160 PASS
ASSERTIONS=1058
MEMORY=20 MB
REAL_MELI_HTTP=0
```

## B1 — durable Sales Audit schema CLOSED

RED `e100e51af4fb4c9b7659be47b742c3f250e0236b` failed only because the Sales Audit tables did not yet exist.

GREEN `424111bfd3642e8de66c56ce805eba19e7707b82` edited `004_sales.sql` in place, because ERP2 remains pre-deploy, and added exactly:

```text
sales_audit_runs
sales_audit_orders
```

No surrogate audit-order id, `in_period`, page table, repair table, history table, raw JSON, new migration, handler or Work type was added.

Fresh QA — run `38012530735`, job `114095498948`:

```text
PHP=8.5.11
PHPSTAN=0
PHPUNIT=161/161 PASS
ASSERTIONS=1064
MEMORY=20 MB
REAL_MELI_HTTP=0
```

Checkpoint B0+B1 commit `bc1102a3000882375fa9bf04591fc64244264201` was itself verified green: 161/161, 1064 assertions, PHPStan 0.

## B2 — temporal contract + durable run creation CLOSED

RED commit:

```text
2602ce1d88fe56e2ea1be16cdb161c8e8a323ded
```

RED result — run `38012786522`, job `114096327063`:

```text
PHPSTAN=0
TESTS=166
EXPECTED NEW FAILURES=5
CAUSE=SalesAuditWindow / SalesAuditRepository did not exist
```

No unrelated regression appeared.

GREEN commits:

```text
f47e9fc1c8da4be1ee416d69189a41b6a31acbd4  SalesAuditWindow
62954301183f487382d79ca119e965d2165c6229  SalesAuditRepository
```

### `SalesAuditWindow`

Current historical support is intentionally narrow:

```text
site_id=MCO -> America/Bogota
other site -> fail closed
```

Canonical month:

```text
[first day 00:00 local, first day next month 00:00 local)
```

For `2026-10-01` MCO this becomes:

```text
canonical UTC: 2026-10-01T05:00:00Z -> 2026-11-01T05:00:00Z
remote guard:  2026-10-01T04:00:00Z -> 2026-11-01T06:00:00Z
```

The period key must be a real `YYYY-MM-01`; unsupported sites and invalid period keys fail closed.

### `SalesAuditRepository`

Added only domain-specific persistence needed immediately by Sales Audit:

- resolve `site_id` by exact company/account scope;
- reject a mismatched company/account scope;
- fixed contract version `seller-search-v1`;
- create durable run in `capturing` state;
- normalize supplied operational start time to UTC before persistence;
- validate site/period through `SalesAuditWindow` before creating the run.

Not added:

```text
SalesAuditHandler
sales.audit Work wiring
remote request
multipage capture
order.sync fan-out
REPAIR
VERIFY
CONFIRM
generic timezone resolver
generic history engine
```

Fresh B2 QA — run `38012892267`, job `114096660065`:

```text
PHP=8.5.11
PHPSTAN=0
PHPUNIT=166/166 PASS
ASSERTIONS=1089
MEMORY=20 MB
REAL_MELI_HTTP=0
```

## V3-A blockage audit result

Verified after the previous blockage:

- `MeliClient` classification remains fail-closed: only `READ|AUTH|WRITE`; unknown/missing blocked.
- exact-money lossless path remains active only where required.
- strict zoned remote timestamps and float-money rejection remain intact.
- Work automatic attempt cap remains `5`.
- 429/cooldown still uses non-penalizing defer.
- 5xx/transport still use bounded retry.
- crash recovery still fails terminal at cap.
- admin settings cannot enable `meli_writes_enabled`.
- settings fail-closed boolean and debug bounds remain restored.
- Work cleanup removes only demonstrably old `done/failed` rows by `finished_at`.
- API usage cleanup remains 90 days.
- no new queue/scheduler/retry engine/maintenance engine appeared.

Only the `orders.search` bigint decode gap was found; B0 closed it with RED/GREEN evidence.

## Current gate status

| Gate | Status | Evidence / boundary |
|---|---|---|
| `G1 REMOTE_TRUTH` | PASS for implemented boundary | exact decimals, strict timestamps, bigint-safe search, MCO time contract |
| `G2 WORK_SAFETY` | PASS | bounded retry/defer/recovery/retention |
| `G3 RATE_SAFETY` | PASS for implemented Sales paths | cooldown defer + bounded transient retry |
| `G4 SALES_AUDIT_TRUTH` | IN PROGRESS | schema + temporal/run foundation green; remote capture not implemented |
| `G5 BILLING_CURSOR_TRUTH` | BLOCKED | C0 real sanitized MCO cursor smoke required |
| `G6 FINANCIAL_NO_DOUBLE_COUNT` | NOT STARTED | later Financial block |
| `G7 WRITE_FAIL_CLOSED` | PASS | semantic classification + fuse + no admin activation path |
| `G8 HOSTING_REALITY` | NOT CERTIFIED | real Hostinger limits still pending |

## Exact next microblocks

To keep implementation small, original B3 is split operationally without changing its final contract:

### V3-B3a — evidence persistence primitives only

1. Persist one observed `(audit_run_id, external_order_id, remote_date_created)` fact.
2. Duplicate same ID inside same run must not create a second fact.
3. No remote HTTP.
4. No Work wiring.
5. No capture validation/hash yet.
6. Full RED -> GREEN -> QA.

### V3-B3b — one remote CAPTURE page

Only after B3a green:

1. add `sales.audit` one-page processing;
2. use B2 window and existing `orders.search`;
3. require ID + explicitly zoned `date_created`;
4. persist observations only;
5. CAPTURE must not enqueue `order.sync`;
6. preserve existing 429 defer / bounded 5xx+transport retry semantics;
7. no REPAIR/VERIFY/CONFIRM yet.

## Stop conditions

- `F6A Task 2` remains blocked until C0.
- no Billing handler.
- no merge.
- no deploy.
- no real Mercado Libre HTTP except separately authorized smoke.
- no remote writes.
- do not enable `meli_writes_enabled` before F16.
