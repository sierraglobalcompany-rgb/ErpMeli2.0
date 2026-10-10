# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Current implementation SHA:** `57629ce9a78a235ff84352d3fe9aa9aa25d81478`  
**Remote writes:** OFF  
**REAL_MELI_HTTP:** `0`

## Current truth

V3-A is closed/green. V3-B Sales Audit is in progress in deliberately small blocks.

Closed V3-B blocks:

```text
B0        bigint-safe orders.search
B1        two-table Sales Audit schema
B2        MCO temporal contract + durable capturing run
B3a       durable observation primitive
B3b1      one validated/atomic remote CAPTURE page
B3b2a1    429 + 5xx + transport semantics
B3b2a2.1  401 refresh + exactly one safe retry
```

`SalesAuditHandler` remains inactive: it is still NOT wired into `SalesWorkProcessor`.

## Current handler guarantees

- exact company/account/run scope;
- only `capturing` + `seller-search-v1` + connected account;
- MCO month derives from durable run, not Work dates;
- one `orders.search` page;
- numeric exact order ID + zoned `date_created` required;
- whole page validates before persistence;
- evidence + Work completion atomic;
- duplicate evidence fails closed without overwrite;
- malformed page leaves zero partial evidence;
- CAPTURE enqueues zero `order.sync`;
- no continuation page yet;
- 429/cooldown -> defer same Work, no attempt burn;
- 5xx/transport -> bounded retry same Work +30s, attempt consumed;
- first 401 -> existing OAuth `refreshAfterUnauthorized()` -> exactly one retry;
- second 401 after refresh -> terminal `meli_unauthorized`;
- no replacement Work chain.

## B3b2a2.1 — CLOSED

RED:

```text
2bef9806366cd44d8bff3a187f9d225be7221c08
```

RED run `38014320179`, job `114101127967`:

```text
PHPSTAN=0
PHPUNIT=174 tests
FAILURES=2
only: first 401 was terminal instead of refresh+retry
```

GREEN:

```text
57629ce9a78a235ff84352d3fe9aa9aa25d81478
```

Implementation reused the existing `OAuthRefreshService::refreshAfterUnauthorized()` and extracted only the existing search call for one retry. There is no retry loop, OAuth engine, table or setting.

Fresh QA — run `38014453196`, job `114101524210`:

```text
PHP=8.5.11
PHPSTAN=0
PHPUNIT=174/174 PASS
ASSERTIONS=1169
MEMORY=22 MB
REAL_MELI_HTTP=0
```

## Current gates

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for implemented handler semantics; handler still inactive |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 real sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

## Exact next microblock

### V3-B3b2a2.2 — verify remaining post-401/permanent boundaries

Still **no Work processor wiring**.

Add focused tests only for:

1. 429 during OAuth refresh or retry search -> defer, attempts restored, zero evidence;
2. 5xx after refresh/retry -> bounded retry +30s, attempt consumed, zero evidence;
3. initial non-401/non-5xx rejection (e.g. 403) -> terminal `meli_remote_permanent`, zero evidence;
4. no replacement Work chain.

If these tests pass on current code, make **no product change**; record it as verification, not fake RED.

Only after this verification block is green:

```text
B3b2b = wire sales.audit + delete orders.reconcile/ReconcileOrdersHandler
```

## Stop conditions

- no next-page continuation yet;
- no VALIDATE/hash/REPAIR/VERIFY/CONFIRM yet;
- no Billing handler / F6A Task2 before C0;
- no merge;
- no deploy;
- no real Mercado Libre HTTP except separately authorized smoke;
- no remote writes;
- `meli_writes_enabled` remains OFF until F16.
