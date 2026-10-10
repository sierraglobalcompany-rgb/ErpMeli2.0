# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Base V3-A checkpoint:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Audited functional HEAD before this checkpoint:** `81068b6e4cca808e3534e275ed01edc9e9204fb1`  
**Remote writes:** OFF  
**REAL_MELI_HTTP:** `0`

## Current truth

V3-A is closed/green. V3-B Sales Audit remains active.

The old checkpoint stopped at B3e3b3a and is superseded by this file. The implementation has now advanced through bounded REPAIR runtime and local VERIFY, but **remote CONFIRM/capture B is not wired yet**.

Closed and verified before this checkpoint:

```text
B0        bigint-safe orders.search
B1        two-table Sales Audit schema
B2        MCO temporal contract + durable capturing run
B3a       durable observation primitive
B3b1      one validated/atomic CAPTURE page
B3b2a     429/5xx/transport/OAuth/permanent failure semantics
B3b2b1    sales.audit activated in SalesWorkProcessor
B3b2b2    obsolete orders reconciler deleted
B3c1      stable durable remote_total contract
B3c2      stable-total page continuation
B3c3      terminal traversal integrity by durable observed count
B3d1      canonical local set fingerprint primitive
B3d2      terminal CAPTURE fingerprint wiring
B3e1      deterministic local missing-set primitive
B3e2      guarded post-CAPTURE state decision
B3e3a     repairing-phase missing-set read
B3e3b1    single deterministic repair candidate
B3e3b2    enqueue one bounded repair candidate
B3e3b3a   block automatic recreation after terminal repair sync
K1        collapse full missing-list reads to deterministic LIMIT 1
K2        runtime REPAIR dispatch through existing sales.audit Work
K3        terminal repair child + persistent gap -> durable run attention
K4        terminal CAPTURE -> repairing + one sales.audit continuation when needed
K5        local VERIFY: repairing -> confirming only when no canonical gap remains
K5b       terminal CAPTURE fast-path: repairing if missing, confirming if already covered
```

## Current verified evidence

Functional HEAD:

```text
81068b6e4cca808e3534e275ed01edc9e9204fb1
feat(v3-k5b): advance terminal capture directly to next durable state
```

Fresh QA:

```text
RUN=38057589089
JOB=114229115845
PHP=8.5.11
PHPSTAN=0
PHPUNIT=199/199 PASS
ASSERTIONS=1355
MEMORY=22 MB
REAL_MELI_HTTP=0
```

## Architecture after K1-K5b

Still exactly:

```text
1 Work table
Work states: pending / running / done / failed
Work types for Sales: order.sync / sales.audit
1 WorkRunner
1 MeliClient
1 Sales audit run state machine
```

No new:

```text
repair engine
recovery engine
priority queue
domain scheduler
repair table
repair history table
child-state table
extra Work status
extra Sales Work type
```

Important runtime semantics:

1. CAPTURE never fans out `order.sync` directly.
2. Terminal CAPTURE fingerprints the canonical set.
3. Post-CAPTURE decision is one durable transition:
   - canonical gap exists -> `repairing` + exactly one `sales.audit` continuation;
   - no canonical gap -> `confirming`, with no repair continuation.
4. REPAIR handles one deterministic missing order at a time.
5. Parent REPAIR defers while the same child `order.sync` is pending/running and does not burn an attempt.
6. Terminal same-ID child + persistent local gap is not recreated; the audit run becomes durable `attention` and parent Work can finish.
7. When no canonical gap remains, the run moves `repairing -> confirming` inside the parent claim transaction.
8. Work remains execution state only; terminal Work cleanup does not erase the durable business outcome.

## ERP1 lessons explicitly preserved

Do not reintroduce:

- multi-state queue churn such as running/waiting/ready mismatches;
- generic recovery/retry engines;
- historical backfill machinery coupled to current work;
- domain schedulers or priority queues;
- business truth depending on retained Work history;
- retries that keep historical gaps alive forever.

Git preserves history; productive ERP2 code must stay smaller than the ERP1 failure patterns it replaces.

## Mercado Libre contract revalidated 2026-10-10

Official docs currently state:

1. Orders are retained/searchable only up to **12 months**.
2. Searching orders as **seller filters cancelled orders**.
3. `orders/search` exposes `paging.total`, `paging.offset`, `paging.limit` and generic Mercado Libre pagination uses `limit` + `offset` blocks.
4. Do not infer all-time seller truth from seller search.

Official references:

- https://developers.mercadolibre.com.co/es_ar/mercadolider-tiendas-oficiales/gestiona-ventas
- https://developers.mercadolibre.com.co/es_co/consideraciones-de-diseno

## Audit finding: truth gates still open before CONFIRM

Two capture-safety gaps must be closed **before wiring independent capture B or setting `valid`**:

### 1. Source horizon

A closed month cannot be certified from seller search if any part of its canonical month is outside the current 12-month source horizon.

Required behavior:

```text
outside supported source horizon
-> durable unavailable
-> no empty=complete inference
-> no retry forever
-> no valid
```

No historical engine or alternate source is added in this block.

### 2. Short non-terminal page

Current pagination advances by requested `limit`. Until the orders endpoint contract demonstrates that every non-terminal page is full, a response where:

```text
count(results) < paging.limit
AND offset + limit < total
```

must fail closed rather than advance an offset that could skip unseen positions.

This is a guard, not a new pagination engine.

## Gates

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary; source-horizon/short-page hardening next |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for active Sales paths |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — CAPTURE + bounded REPAIR + local VERIFY wired; source horizon, short page, independent capture B, final confirmation/validity and baseline policy remain |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 real sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

## Exact next microblock

### K6a — CAPTURE source-truth hardening RED

Scope only:

1. RED: a requested canonical month whose start is outside the seller-search 12-month horizon must not call remote search and must end durably `unavailable`.
2. RED: a short non-terminal page must fail closed and must not persist partial page evidence or enqueue a continuation.
3. Preserve current 429/5xx/OAuth semantics.
4. No capture B yet.
5. No `valid` yet.
6. No alternate historical source, import engine, retry engine, new table, Work type, queue, scheduler or UI.
7. GREEN minimal -> full QA -> checkpoint.

After K6a passes, audit whether any superseded capture helper/test/document can be deleted or merged before starting independent capture B.

## Stop conditions

- no independent capture B until K6a is green;
- no `valid` until independent capture B proves same canonical count/hash;
- no Billing handler / F6A Task2 before C0;
- no merge;
- no deploy;
- no real Mercado Libre HTTP except separately authorized smoke;
- no remote writes;
- `meli_writes_enabled` remains OFF until F16.
