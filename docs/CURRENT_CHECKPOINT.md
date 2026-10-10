# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Current implementation SHA:** `424111bfd3642e8de66c56ce805eba19e7707b82`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**V3-B plan:** `docs/superpowers/plans/2026-10-10-v3-b-sales-audit.md`  
**Remote writes:** OFF  
**Real Mercado Libre HTTP in QA:** OFF

## Current state

V3-A remains closed and green. Before starting Sales Audit, the branch was audited against authority, code, diff and QA to detect gaps left by the previous chat blockage.

One concrete gap was found and closed in B0: V3.2 required native `orders.search` decoding with `JSON_BIGINT_AS_STRING`, while the final V3-A `MeliClient` native branch did not include that flag. No other skipped A1-A8 task or final-contract regression was found in the audited owners.

V3-B is now being executed in deliberately small blocks.

## B0 — preentry audit gap CLOSED

RED commit:

```text
6be2cb8060bbeb2cd3103722c4ea3b29d5c8d3e7
```

The regression test sent a literal JSON integer larger than `PHP_INT_MAX` through `orders.search` and proved it became PHP float/scientific notation.

GREEN commit:

```text
b05bb8bd3a4f0eb67dc821cd8878b84e4e89931f
```

Minimal fix:

```text
native json_decode -> JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING
```

No `LosslessJsonDecoder` was enabled for `orders.search`; exact-decimal lossless scanning remains reserved for operations such as `orders.get` and `billing.period.details`.

Fresh B0 QA — GitHub Actions run `38012299105`, job `114094780325`:

```text
PHP=8.5.11
PHPSTAN=0
PHPUNIT=160/160 PASS
ASSERTIONS=1058
MEMORY=20 MB
REAL_MELI_HTTP=0
```

## B1 — durable Sales Audit schema CLOSED

RED commit:

```text
e100e51af4fb4c9b7659be47b742c3f250e0236b
```

Expected RED:

```text
161 tests
1 failure
Missing sales audit table: sales_audit_runs
PHPSTAN=0
```

GREEN implementation commit:

```text
424111bfd3642e8de66c56ce805eba19e7707b82
```

Because ERP2 remains pre-deploy with no persistent installation to preserve, `004_sales.sql` was edited in place rather than creating migration `006` only to preserve development history.

Added exactly two durable evidence tables.

### `sales_audit_runs`

```text
id
company_id
account_id
period_key
contract_version
status
remote_total
canonical_count
set_hash
started_at
completed_at
updated_at
```

Allowed statuses:

```text
capturing
repairing
confirming
valid
attention
unavailable
```

The run is scoped to existing company/account ownership and indexed for account/period/status lookup.

### `sales_audit_orders`

```text
audit_run_id
external_order_id
remote_date_created
PRIMARY KEY(audit_run_id, external_order_id)
```

`audit_run_id` references the run with cascade delete.

Intentionally NOT added:

```text
surrogate id on sales_audit_orders
in_period
page table
repair table
history table
raw JSON
snapshot JSON
new migration file
new handler
new Work type
```

Fresh B1 QA — GitHub Actions run `38012530735`, job `114095498948`:

```text
PHP=8.5.11
PHPSTAN=0
PHPUNIT=161/161 PASS
ASSERTIONS=1064
MEMORY=20 MB
REAL_MELI_HTTP=0
```

## V3-A regression audit result

Audited after the previous blockage:

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

Only the `orders.search` bigint decode gap above was found; B0 closed it with RED/GREEN evidence.

## Current gate status

| Gate | Status | Evidence / boundary |
|---|---|---|
| `G1 REMOTE_TRUTH` | PASS for implemented boundary | exact decimals, strict timestamps, bigint-safe native search decode |
| `G2 WORK_SAFETY` | PASS | bounded retry/defer/recovery/retention |
| `G3 RATE_SAFETY` | PASS for implemented Sales paths | cooldown defer + bounded transient retry |
| `G4 SALES_AUDIT_TRUTH` | IN PROGRESS | B1 durable evidence schema green; capture behavior not implemented yet |
| `G5 BILLING_CURSOR_TRUTH` | BLOCKED | C0 real sanitized MCO cursor smoke required |
| `G6 FINANCIAL_NO_DOUBLE_COUNT` | NOT STARTED | later Financial block |
| `G7 WRITE_FAIL_CLOSED` | PASS | semantic classification + fuse + no admin activation path |
| `G8 HOSTING_REALITY` | NOT CERTIFIED | real Hostinger limits still pending |

## Exact next microblock

```text
V3-B2 — temporal contract + durable run creation
```

Scope only:

1. Historical certification supports `site_id=MCO` → `America/Bogota`.
2. Unsupported site fails closed.
3. Canonical month is local `[first day 00:00, next first day 00:00)`.
4. Remote seller-search window is canonical UTC with ±1h guard-band.
5. Create a durable `capturing` run for company/account/period.
6. No multipage remote capture yet.
7. No `order.sync` enqueue.
8. No REPAIR, VERIFY or CONFIRM yet.

B2 must execute RED → minimum GREEN → full QA before B3.

## Stop conditions

- `F6A Task 2` remains blocked until C0.
- no Billing handler.
- no merge.
- no deploy.
- no real Mercado Libre HTTP except separately authorized smoke.
- no remote writes.
- do not enable `meli_writes_enabled` before F16.
