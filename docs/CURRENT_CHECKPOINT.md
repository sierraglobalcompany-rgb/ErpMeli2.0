# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Current implementation SHA:** `d55ed5e9bd78c7a9e90c46f560295e395c75113d`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**V3-B plan:** `docs/superpowers/plans/2026-10-10-v3-b-sales-audit.md`  
**Remote writes:** OFF  
**Real Mercado Libre HTTP in QA:** OFF

## Current state

V3-A remains closed and green. V3-B is being executed in deliberately small RED -> minimum GREEN -> full QA -> checkpoint blocks.

A post-blockage audit found one V3-A gap only: native `orders.search` decoding lacked `JSON_BIGINT_AS_STRING`. B0 closed it. No other skipped A1-A8 final-contract regression was found in the audited owners.

## B0 — bigint-safe seller search CLOSED

RED `6be2cb8060bbeb2cd3103722c4ea3b29d5c8d3e7` proved a JSON integer larger than `PHP_INT_MAX` became float/scientific notation.

GREEN `b05bb8bd3a4f0eb67dc821cd8878b84e4e89931f` changed native decode only to:

```text
JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING
```

QA run `38012299105`: PHPStan 0, 160/160, 1058 assertions, 20 MB, REAL_MELI_HTTP=0.

## B1 — durable audit schema CLOSED

RED `e100e51af4fb4c9b7659be47b742c3f250e0236b`.

GREEN `424111bfd3642e8de66c56ce805eba19e7707b82` edited pre-release `004_sales.sql` in place and added exactly:

```text
sales_audit_runs
sales_audit_orders
```

No surrogate audit-order id, `in_period`, page table, repair table, history table, raw JSON, new migration, handler or Work type.

QA run `38012530735`: PHPStan 0, 161/161, 1064 assertions, 20 MB.

## B2 — temporal contract + durable run CLOSED

RED `2602ce1d88fe56e2ea1be16cdb161c8e8a323ded`.

GREEN:

```text
f47e9fc1c8da4be1ee416d69189a41b6a31acbd4  SalesAuditWindow
62954301183f487382d79ca119e965d2165c6229  SalesAuditRepository
```

Historical support is intentionally narrow:

```text
MCO -> America/Bogota
other site -> fail closed
```

Canonical MCO October 2026:

```text
canonical UTC: 2026-10-01T05:00:00Z -> 2026-11-01T05:00:00Z
remote guard:  2026-10-01T04:00:00Z -> 2026-11-01T06:00:00Z
```

QA run `38012892267`: PHPStan 0, 166/166, 1089 assertions, 20 MB.

Checkpoint `9122700a86cecccb9a1a1cd85c9cbd7e56a870a8` was also green.

## B3a — durable observation primitive CLOSED

RED `12f19e0046f70265f88f6ff527f32977454f671a`.

GREEN `acb0bca2f0cfa0673e3cc5d17569f679fe308f3c` added only:

```text
SalesAuditRepository::recordObservation(...): bool
```

Semantics:

- first observation inserts durable evidence;
- timestamp persisted UTC;
- only MariaDB duplicate key 1062 returns `false`;
- duplicate never overwrites original evidence;
- all other DB errors propagate fail-closed;
- no `INSERT IGNORE`.

QA run `38013378562`: PHPStan 0, 167/167, 1094 assertions, 22 MB.

Checkpoint `46062cf7f60530e282e33a828e7d1f5cca47162d` was verified green.

## B3b1 — one validated remote CAPTURE page CLOSED

RED commit:

```text
1693b5cdf5372e7da8ea58aa02c56aca3d78b54c
```

RED run `38013645299`, job `114099050592`:

```text
PHPSTAN=0
TESTS=169
ERRORS=2
CAUSE=SalesAuditHandler did not exist
```

No unrelated regression appeared.

GREEN commits:

```text
ae50fef1c2e472428a55bb1fdb5ed85ca91ff6dd  scoped capture context
d55ed5e9bd78c7a9e90c46f560295e395c75113d  one-page SalesAuditHandler
```

B3b1 behavior now proven:

- handler derives period/site/seller from the durable run + exact company/account scope;
- run must remain `capturing`, fixed contract `seller-search-v1`, account connected;
- remote window comes from `SalesAuditWindow`, never from Work payload dates;
- calls existing `orders.search` once for the requested page;
- requires every result to contain numeric order ID and explicitly zoned `date_created`;
- preserves IDs larger than `PHP_INT_MAX` through the B0 bigint-safe boundary;
- validates the entire page before persistence;
- persists all observed guard-band facts, including observations outside the canonical month;
- persistence and Work completion are one transaction through existing `completeCurrentClaim()`;
- duplicate order ID inside the same run is detected and cannot overwrite prior evidence;
- malformed page fails `meli_sales_audit_contract`;
- malformed page leaves zero partial evidence from that page;
- CAPTURE enqueues zero `order.sync`;
- no next-page Work is created yet.

Fresh GREEN QA — run `38013774324`, job `114099454094`:

```text
PHP=8.5.11
PHPSTAN=0
PHPUNIT=169/169 PASS
ASSERTIONS=1115
MEMORY=22 MB
REAL_MELI_HTTP=0
```

Intentionally NOT wired yet:

```text
SalesWorkProcessor -> SalesAuditHandler
429 defer path
401 refresh+one retry path
5xx/transport bounded retry path
next-page continuation
stable total validation
hash
REPAIR
VERIFY
CONFIRM
```

The old `ReconcileOrdersHandler` still exists only because the final `sales.audit` handler is not safe to activate until its remote failure semantics are proven. It will be removed in the swap block; it is not the target architecture.

## Current gate status

| Gate | Status | Evidence / boundary |
|---|---|---|
| `G1 REMOTE_TRUTH` | PASS for implemented boundary | exact numbers/timestamps, bigint-safe search, MCO month contract |
| `G2 WORK_SAFETY` | PASS | bounded generic Work retry/defer/recovery/retention |
| `G3 RATE_SAFETY` | PASS for existing active Sales paths | new SalesAuditHandler not activated yet |
| `G4 SALES_AUDIT_TRUTH` | IN PROGRESS | durable run + evidence + atomic one-page capture green |
| `G5 BILLING_CURSOR_TRUTH` | BLOCKED | C0 real sanitized MCO cursor smoke required |
| `G6 FINANCIAL_NO_DOUBLE_COUNT` | NOT STARTED | later Financial block |
| `G7 WRITE_FAIL_CLOSED` | PASS | writes fuse OFF; no admin activation path |
| `G8 HOSTING_REALITY` | NOT CERTIFIED | real Hostinger limits pending |

## Exact next microblocks

### V3-B3b2a — remote failure semantics only

No Work wiring yet.

Prove directly on `SalesAuditHandler`:

1. 429 / active cooldown -> `deferCurrentClaim()`, no attempt burn, no evidence.
2. 401 -> existing OAuth refresh + one safe retry only.
3. 5xx -> bounded `retryCurrentClaim()`.
4. transport failure -> bounded `retryCurrentClaim()`.
5. permanent non-401/5xx -> terminal fail.
6. no replacement Work chain.
7. RED -> minimum GREEN -> full QA -> checkpoint.

### V3-B3b2b — final Work swap

Only after B3b2a is green:

1. wire `sales.audit` into `SalesWorkProcessor`;
2. remove `orders.reconcile` dispatch;
3. delete `ReconcileOrdersHandler` and superseded reconciliation tests;
4. prove unsupported/malformed work remains terminal;
5. still no next-page continuation, REPAIR, VERIFY or CONFIRM;
6. full QA -> checkpoint.

## Stop conditions

- `F6A Task 2` remains blocked until C0.
- no Billing handler.
- no merge.
- no deploy.
- no real Mercado Libre HTTP except separately authorized smoke.
- no remote writes.
- do not enable `meli_writes_enabled` before F16.
