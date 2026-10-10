# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED — STOP AFTER THIS CHECKPOINT**  
**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Master map:** `README.md`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Engineering law:** `AGENTS.md`  
**Remote writes:** OFF  
**REAL_MELI_HTTP:** `0`

## Execution constraint

```text
1 microblock at a time
RED -> confirm intended failure -> minimal GREEN -> full QA -> noise audit
checkpoint after 1-2 microblocks maximum
STOP after checkpoint when context grows
```

## Authority order

```text
1. code/schema at branch HEAD
2. tests/CI at relevant SHA
3. this checkpoint
4. ERP2_AUTHORITY.md
5. recent explicit user decisions
6. README.md master map
7. historical handoffs/plans
```

---

# 1. K6b-3 — CLOSED GREEN

RED:

```text
1fab5dbbda7dee4649e10318f7872b0ef6a37028
test(v3-k6b3): prove confirming dispatch starts independent capture B
```

RED QA:

```text
RUN=38069661480
JOB=114264310611
PHP=8.5.11
PHPSTAN=0
PHPUNIT=204 tests
ASSERTIONS=1393
FAILURES=1
MEMORY=22 MB
REAL_MELI_HTTP=0
```

Single intended failure proved `confirming` still fell through to `sales_audit_state`.

Functional GREEN:

```text
3240dfd23a9d5d79dcc1818004721fd23faad1ae
feat(v3-k6b3): dispatch confirming through capture B
```

Fresh full QA:

```text
RUN=38070168398
JOB=114265772278
PHP=8.5.11
PHPSTAN=0
PHPUNIT=204/204 PASS
ASSERTIONS=1400
MEMORY=22 MB
REAL_MELI_HTTP=0
```

Noise audit from prior checkpoint `545c594...` to GREEN `3240dfd...`:

```text
3 existing production files only
app/Modules/Sales/Audit/SalesAuditHandler.php      +64/-17
app/Modules/Sales/Audit/SalesAuditRepository.php   +13/-4
app/Modules/Sales/SalesWorkProcessor.php             +2/-1

no schema
no table
no Work type
no Work status
no queue
no new handler/repository/engine class
```

---

# 2. RUNTIME TRUTH NOW

Current proven Sales Audit flow:

```text
CAPTURE A
-> durable A observations
-> stable source total
-> canonical A fingerprint
-> missing ? repairing : confirming
-> bounded one-child REPAIR
-> local VERIFY
-> confirming
-> independent Capture B page traversal
```

K6b-3 behavior now GREEN:

```text
run status confirming
-> same sales.audit Work type
-> same SalesAuditHandler
-> same OAuth/MeliClient/orders.search path
-> same source-horizon guard
-> same page normalization + short-page fail-closed
-> observations persist only as capture_pass='B'
-> A evidence remains untouched
-> one page per Work
-> non-terminal B page enqueues exactly one continuation
-> B remote_total travels only in continuation payload
-> run remains confirming
```

B continuation payload:

```text
{run_id, offset, limit, remote_total}
```

B does **not** overwrite `sales_audit_runs.remote_total`; that durable field remains Capture A source evidence.

`captureContext()` now accepts only:

```text
capturing
confirming
```

with default `capturing`, preserving existing callers.

---

# 3. DELIBERATE FAIL-CLOSED BOUNDARY

Terminal Capture B is intentionally **not implemented yet**.

Current behavior if a B page is terminal:

```text
fail closed
-> no partial terminal B commit
-> no false valid
```

This is deliberate separation of microblocks, not the final terminal behavior.

Do not patch around it.

---

# 4. SOURCE / A-B CONTRACT STILL BINDING

Seller-search guards already GREEN:

```text
outside supported horizon -> unavailable before OAuth/HTTP
short non-terminal page -> fail closed
remote dates zoned
bigint-safe ids
```

Evidence model:

```text
sales_audit_orders
PRIMARY KEY(audit_run_id, capture_pass, external_order_id)
capture_pass ENUM('A','B')
```

Repository primitives:

```text
recordObservation(..., capturePass='A')
observationCount(..., capturePass='A')
canonicalFingerprint(..., capturePass='A')
```

Repair/verify remains explicitly A-only.

No second run/table/queue/Work type/ConfirmRepository/ConfirmEngine.

---

# 5. EXACT NEXT MICROBLOCK — K6b-4 RED ONLY

```text
K6b-4 — terminal Capture B integrity + A/B mismatch attention
```

RED should prove terminal B behavior without implementing `valid` yet:

1. terminal B requires durable B observation count equal to B traversal `remote_total`;
2. derive B canonical fingerprint using existing `canonicalFingerprint(...,'B')`;
3. compare B `canonical_count/set_hash` against durable A `canonical_count/set_hash`;
4. mismatch must transition run `confirming -> attention` atomically with current Work completion;
5. A evidence remains unchanged;
6. B evidence remains durable when mismatch is accepted as attention;
7. no new table/column/state/Work type/engine.

Keep equality/`valid` separate unless the RED proves one atomic transition is materially simpler and still easy to reason about. Default plan: **K6b-4 handles mismatch->attention only**; `valid` follows as its own microblock.

K6b-4 starts RED only, then checkpoint if context grows.

---

# 6. DO NOT OPEN NOW

```text
NO valid yet
NO baseline lifecycle
NO Billing Task 2 before C0
NO Financial
NO merge
NO deploy
NO real Mercado Libre HTTP
NO remote writes
```

---

# 7. DOCUMENTATION NOTE

`README.md` and `ERP2_AUTHORITY.md` were synchronized through K6b-2/K6a before K6b-3. This checkpoint is the exact higher-authority execution state for K6b-3. To avoid documentation churn, synchronize the living README/Authority again after the next bounded CONFIRM terminal microblock or sooner if a binding contract changes.
