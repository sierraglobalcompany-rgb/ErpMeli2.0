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

# 1. K6b-5 — TERMINAL B EQUALITY -> VALID — CLOSED GREEN

Final RED:

```text
34fe4fe2446512849eeb20e34ec0f17e2743e79a
test(v3-k6b5): require completed_at for valid audit
```

RED QA:

```text
RUN=38072597459
JOB=114272935153
PHP=8.5.11
PHPSTAN=0
PHPUNIT=206 tests
ASSERTIONS=1429
FAILURES=1
MEMORY=22 MB
REAL_MELI_HTTP=0
```

Single intended failure:

```text
Tests\Integration\SalesAuditConfirmValidTest::
testTerminalCaptureBEqualityCompletesWorkAndMovesRunToValid

Expected current Work status: done
Actual current Work status:   failed
```

Functional GREEN:

```text
81dd33c863bb1ec7eea1bb40cb51ad55167cbc69
feat(v3-k6b5): mark matching confirmation valid
```

Fresh full QA:

```text
RUN=38072885363
JOB=114273775595
PHP=8.5.11
PHPSTAN=0
PHPUNIT=206/206 PASS
ASSERTIONS=1441
MEMORY=22 MB
REAL_MELI_HTTP=0
```

---

# 2. K6b-5 GREEN RUNTIME CONTRACT

Terminal Capture B now has exactly two guarded outcomes after durable B integrity and canonical fingerprint derivation:

```text
terminal B
-> observationCount(runId,'B') == B traversal remote_total
-> canonicalFingerprint(runId, window, 'B')
-> compare against durable A canonical_count/set_hash

mismatch
-> confirming -> attention
-> Work done

equality
-> confirming -> valid
-> completed_at = UTC_TIMESTAMP(6)
-> Work done
```

Both paths execute inside the existing `completeCurrentClaim` transaction, so terminal B observation persistence + run transition + Work completion are atomic.

Equality requires both:

```text
canonical_count equal
AND set_hash equal
```

The durable A fingerprint remains unchanged. A and B observations remain preserved.

No continuation is enqueued on terminal B. No `order.sync` fanout occurs.

---

# 3. NOISE / KISS AUDIT

Delta from RED checkpoint `3e5b31a...` to functional GREEN `81dd33c...`:

```text
1 existing production file only
app/Modules/Sales/Audit/SalesAuditHandler.php
+21/-2

no schema
no table
no column
no new class
no repository
no handler
no state
no Work type
no Work status
no queue
no engine
no second audit run
```

Decision ladder result:

```text
DELETE   -> nothing obsolete introduced
SIMPLIFY -> reused existing terminal B branch and transaction
REUSE    -> existing B fingerprint + durable A fingerprint
MERGE    -> no parallel confirm path created
EXTEND   -> one guarded equality transition only
ADD      -> no architecture added
```

A single CASE/mega-update was not introduced; the existing guarded mismatch transition remains intact and equality is a second guarded transition, keeping each business outcome explicit and fail-closed.

The duplicated setup between confirm mismatch/equality integration tests remains test-only. It was not refactored in this microblock because doing so would broaden the change without reducing production complexity.

---

# 4. SALES AUDIT RUNTIME TRUTH NOW

Proven end-to-end core certification flow:

```text
CAPTURE A
-> durable A observations
-> stable A remote_total
-> canonical A fingerprint
-> missing ? repairing : confirming
-> bounded one-child REPAIR
-> local VERIFY
-> confirming
-> independent Capture B traversal
-> stable B remote_total in Work continuation payload
-> terminal B durable count/fingerprint
-> mismatch A/B -> attention
-> equality A/B -> valid + completed_at
```

Capture B reuses the same:

```text
sales.audit Work type
SalesAuditHandler
OAuth/MeliClient/orders.search path
source-horizon guard
page-contract/short-page guard
sales_audit_orders table with capture_pass='B'
```

No second CONFIRM architecture exists.

`valid` semantics remain:

> consistent/verified relative to seller-search and the known contract for that run, not an absolute guarantee of the complete historical Mercado Libre universe.

---

# 5. DOCUMENTATION SYNC REQUIRED NEXT

`README.md` and `docs/ERP2_AUTHORITY.md` were synchronized through K6b-4 and still state that equality -> `valid` is missing.

Before opening baseline lifecycle or another behavior microblock, perform one bounded documentation sync so living docs state:

```text
K6b-3 independent Capture B traversal = GREEN
K6b-4 terminal mismatch -> attention = GREEN
K6b-5 terminal equality -> valid + completed_at = GREEN
```

This must be documentation-only. No production/schema/test changes.

---

# 6. EXACT NEXT MICROBLOCK — K6b-DOC2 ONLY

When user says `continua`:

1. verify branch HEAD equals this checkpoint commit;
2. update only `README.md` and `docs/ERP2_AUTHORITY.md` to K6b-5 GREEN truth;
3. remove obsolete statements that say equality/valid is unimplemented;
4. preserve the seller-search-relative meaning of `valid`;
5. preserve baseline lifecycle as still open;
6. update G4 wording to show the core A/B certification path is GREEN while lifecycle/start operational gaps remain;
7. audit diff as documentation-only;
8. checkpoint and STOP.

Do not open baseline lifecycle in the same documentation microblock.

---

# 7. OPEN G4 GAPS AFTER CORE A/B CERTIFICATION

Do not mix these into K6b-DOC2:

```text
baseline lifecycle / superseded valid evidence
Sales Audit start UX + duplicate active-run guard
exact order 404 final audit classification
```

Other project gaps remain separate:

```text
sale_fee schema/persistence alignment before Financial
webhook_events lifecycle
MariaDB session timezone certification
Billing C0 sanitized MCO smoke
Financial no-double-count
Hosting/runtime/main protection
```

Baseline authority rule already frozen:

> conservar el más reciente válido; evidencia superseded equivalente se elimina. Si aparece diferencia, conservar baseline anterior + run attention hasta resolver.

Do not implement it until its own RED/design microblock.

---

# 8. GATES

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary + K6a guards |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for current Sales paths |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — core A capture/repair/verify + independent B traversal + mismatch attention + equality valid GREEN; baseline/start/404 lifecycle gaps remain |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

---

# 9. TOOLING NOTE

Active authoritative branch remains only:

```text
impl/v3-b-sales-audit-20261010
```

Two auxiliary refs (`tmp` and `impl/v3-b-sales-audit-20261010-red`) were created accidentally during prior tooling interaction and point only to an older documentation checkpoint. They are non-authoritative and must not be used for continuation. Clean them later when a deletion-capable Git tool is available.

---

# 10. STOP CONDITIONS

```text
STOP now until explicit user continua
NO baseline lifecycle yet
NO Sales Audit start UX yet
NO Billing
NO Financial
NO merge
NO deploy
NO real Mercado Libre HTTP
NO remote writes
```
