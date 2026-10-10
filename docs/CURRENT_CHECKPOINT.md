# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Current verified SHA:** `f7574c02104fbdcec2cdd198f0ccaf08d0e157df`  
**Remote writes:** OFF  
**REAL_MELI_HTTP:** `0`

## Current truth

V3-A is closed/green. V3-B Sales Audit is in progress in small verified blocks.

Closed:

```text
B0        bigint-safe orders.search
B1        two-table Sales Audit schema
B2        MCO temporal contract + durable capturing run
B3a       durable observation primitive
B3b1      one validated/atomic remote CAPTURE page
B3b2a1    429 + 5xx + transport semantics
B3b2a2    OAuth 401 recovery + permanent failure semantics
```

`SalesAuditHandler` is complete enough to activate for one-page CAPTURE, but is still NOT wired into `SalesWorkProcessor` at this checkpoint.

## Proven SalesAuditHandler behavior

- exact company/account/run scope;
- only `capturing` + `seller-search-v1` + connected account;
- MCO month/window derives from durable run;
- one `orders.search` page;
- numeric exact order ID + zoned `date_created` required;
- validate full page before persistence;
- evidence + Work completion atomic;
- duplicate evidence fails closed without overwrite;
- malformed page leaves zero partial evidence;
- CAPTURE enqueues zero `order.sync`;
- no continuation page yet;
- 429/cooldown -> defer same Work, no attempt burn;
- 5xx/transport -> bounded retry same Work +30s, attempt consumed;
- first 401 -> existing OAuth refresh -> exactly one retry;
- second 401 -> terminal `meli_unauthorized`;
- 429 during OAuth recovery -> defer without attempt burn;
- 5xx after OAuth recovery -> bounded retry;
- non-401/non-5xx remote rejection -> terminal `meli_remote_permanent`;
- no replacement Work chain.

## B3b2a2 evidence

RED 401 commit:

```text
2bef9806366cd44d8bff3a187f9d225be7221c08
```

RED run `38014320179`: 174 tests, exactly 2 failures proving first 401 was terminal.

GREEN product commit:

```text
57629ce9a78a235ff84352d3fe9aa9aa25d81478
```

QA run `38014453196`, job `114101524210`:

```text
PHP=8.5.11
PHPSTAN=0
PHPUNIT=174/174 PASS
ASSERTIONS=1169
MEMORY=22 MB
REAL_MELI_HTTP=0
```

Verification-only commit:

```text
f7574c02104fbdcec2cdd198f0ccaf08d0e157df
```

It added only tests for rate limit during OAuth refresh, 5xx after refresh and permanent 403. No product change was needed.

QA run `38014672791`, job `114102202622`:

```text
PHP=8.5.11
PHPSTAN=0
PHPUNIT=177/177 PASS
ASSERTIONS=1202
MEMORY=22 MB
REAL_MELI_HTTP=0
```

## Gates

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for new handler semantics; handler not active yet |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 real sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

## Exact next microblocks

### B3b2b1 — activate `sales.audit`

1. RED: processor dispatches `sales.audit` and terminally rejects old `orders.reconcile`.
2. GREEN: replace Reconcile handler dependency in `SalesWorkProcessor` with `SalesAuditHandler`.
3. Update `bin/work.php` construction only.
4. Keep old Reconcile files temporarily inactive; do not delete in this block.
5. Full QA -> checkpoint.

### B3b2b2 — delete obsolete reconciler

Only after B3b2b1 green:

1. delete `ReconcileOrdersHandler`;
2. delete/supersede reconciliation-only tests;
3. grep/audit no active `orders.reconcile` references remain;
4. full QA -> checkpoint.

## Stop conditions

- no next-page continuation yet;
- no VALIDATE/hash/REPAIR/VERIFY/CONFIRM yet;
- no Billing handler / F6A Task2 before C0;
- no merge;
- no deploy;
- no real Mercado Libre HTTP except separately authorized smoke;
- no remote writes;
- `meli_writes_enabled` remains OFF until F16.
