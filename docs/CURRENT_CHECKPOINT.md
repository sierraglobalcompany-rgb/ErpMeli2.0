# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Current verified SHA:** `d76ef6d841aba98d9593ca30dbeeca2da0d012f2`  
**Remote writes:** OFF  
**REAL_MELI_HTTP:** `0`

## Current truth

V3-A is closed/green. V3-B Sales Audit remains in progress in small verified blocks.

Closed:

```text
B0        bigint-safe orders.search
B1        two-table Sales Audit schema
B2        MCO temporal contract + durable capturing run
B3a       durable observation primitive
B3b1      one validated/atomic remote CAPTURE page
B3b2a1    429 + 5xx + transport semantics
B3b2a2    OAuth 401 recovery + permanent failure semantics
B3b2b1    sales.audit active in SalesWorkProcessor / orders.reconcile unsupported
```

## Active runtime contract

`SalesWorkProcessor` now accepts only:

```text
order.sync
sales.audit
```

Any other type, including legacy `orders.reconcile`, is terminally failed with:

```text
unsupported_work_type
```

`bin/work.php` now composes:

```text
OrderSyncWorkProcessor
SalesAuditHandler + SalesAuditRepository
```

and no longer imports or constructs `ReconcileOrdersHandler`.

The generic unsupported-work poison-loop guard remains active on the new processor. The obsolete `ReconcileMalformedWorkTest` was deleted because its only contract no longer exists.

## B3b2b1 evidence

RED:

```text
f12f47a72e3f382d2b5813963c6fb47cf347d87b
```

RED run `38014803229`, job `114102596240`:

```text
PHPSTAN=0
TESTS=177
ERRORS=1
CAUSE=SalesWorkProcessor constructor still required ReconcileOrdersHandler
```

GREEN path:

```text
20522a0...  SalesWorkProcessor -> SalesAuditHandler
4ebf78f...  bin/work.php active composition
34c0f9e...  CLI composition contract
```

First GREEN QA exposed only two legacy test constructors still injecting the old handler. No production regression appeared.

Cleanup inside this block:

```text
d722a814...  preserve generic poison-loop guard on active processor
d76ef6d8...  delete obsolete ReconcileMalformedWorkTest
```

Fresh QA — run `38015079749`, job `114103436832`:

```text
PHP=8.5.11
PHPSTAN=0
PHPUNIT=176/176 PASS
ASSERTIONS=1194
MEMORY=22 MB
REAL_MELI_HTTP=0
```

## Current gates

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for active Sales Audit handler |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 real sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

## Exact next microblock

### B3b2b2 — DELETE obsolete reconciler

Scope only:

1. delete `app/Modules/Sales/ReconcileOrders/ReconcileOrdersHandler.php`;
2. delete `tests/Integration/ReconcileOrdersHandlerTest.php`;
3. repo-wide audit on the branch for active `orders.reconcile` / `ReconcileOrdersHandler` references;
4. keep only intentional historical text in checkpoint/plan if needed; runtime/test code must have zero references;
5. full QA -> checkpoint.

No next-page continuation, VALIDATE/hash, REPAIR, VERIFY or CONFIRM in this block.

## Stop conditions

- no Billing handler / F6A Task2 before C0;
- no merge;
- no deploy;
- no real Mercado Libre HTTP except separately authorized smoke;
- no remote writes;
- `meli_writes_enabled` remains OFF until F16.
