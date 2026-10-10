# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Current implementation SHA:** `afb879328482528a57dd48d1e2cab62c90336b25`  
**Remote writes:** OFF  
**REAL_MELI_HTTP:** `0`

## Current truth

V3-A is closed/green. V3-B Sales Audit is in progress using small `RED -> minimum GREEN -> full QA -> checkpoint` blocks.

A post-blockage audit found one V3-A gap only: native `orders.search` lacked `JSON_BIGINT_AS_STRING`; B0 closed it. No other A1-A8 regression was found.

Closed V3-B blocks:

```text
B0  bigint-safe orders.search
B1  two-table Sales Audit schema
B2  MCO temporal contract + durable capturing run
B3a durable observation primitive
B3b1 one validated/atomic remote CAPTURE page
B3b2a1 429 + 5xx + transport semantics
```

## Current Sales Audit design

```text
sales_audit_runs
sales_audit_orders
```

MCO historical month:

```text
America/Bogota
local [month start, next month start)
remote query = canonical UTC +/-1h
unsupported site = fail closed
```

`SalesAuditHandler` currently:

- derives seller/site/period from durable scoped run;
- accepts only `capturing` + `seller-search-v1` + connected account;
- calls existing `orders.search` one page at a time;
- requires numeric order id + explicitly zoned `date_created`;
- validates full page before persistence;
- stores all guard-band observations;
- persists observations + Work completion atomically;
- duplicate evidence fails closed without overwrite;
- malformed page leaves zero partial evidence;
- CAPTURE enqueues zero `order.sync`;
- creates no continuation page yet;
- is NOT wired into `SalesWorkProcessor` yet.

## B3b2a1 — CLOSED

RED:

```text
080f9962f2bad9aee8319e1a6ad11ae1b7ec17ee
```

RED run `38013983417`, job `114100110831`:

```text
PHPSTAN=0
TESTS=172
ERRORS=3
only: 429, 503, transport escaped SalesAuditHandler
```

GREEN:

```text
afb879328482528a57dd48d1e2cab62c90336b25
```

Semantics now proven:

```text
429/cooldown -> defer same Work, attempts restored, no evidence
5xx         -> bounded retry same Work +30s, attempt consumed
transport   -> bounded retry same Work +30s, attempt consumed
```

No replacement Work chain, new table, setting, retry engine or scheduler was added.

Fresh QA — run `38014141832`, job `114100586878`:

```text
PHP=8.5.11
PHPSTAN=0
PHPUNIT=172/172 PASS
ASSERTIONS=1146
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

### V3-B3b2a2 — OAuth 401 + permanent remote rejection only

Still **no Work processor wiring**.

RED must prove:

1. first `orders.search` 401 triggers existing OAuth refresh and exactly one safe retry;
2. successful retry can persist/complete normally;
3. rate limit during refresh/retry defers without attempt burn;
4. 5xx after refresh uses bounded retry;
5. second 401 becomes terminal `meli_unauthorized`;
6. other non-401/non-5xx remote rejection becomes terminal `meli_remote_permanent`;
7. no replacement Work chain and no partial evidence.

Then minimum GREEN -> full QA -> checkpoint.

Only after B3b2a2 is green:

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
