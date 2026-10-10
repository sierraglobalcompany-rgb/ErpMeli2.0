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

# 1. LAST FUNCTIONAL GREEN — K6c-1 DIVERGENT BASELINE GUARD

Functional GREEN:

```text
0c164793e12f3f289b2f16e93e1bda265b49eff2
feat(v3-k6c1): guard divergent valid baseline
```

Fresh full QA:

```text
RUN=38076990793
JOB=114285940280
PHP=8.5.11
PHPSTAN=0
PHPUNIT=208/208 PASS
ASSERTIONS=1480
MEMORY=22 MB
REAL_MELI_HTTP=0
```

The normal expected Slim 404 output from `BootstrapTest::testStoragePathIsNotExposedAsApplicationRoute` appears in logs but all tests pass.

---

# 2. SALES AUDIT CORE TRUTH

```text
CAPTURE A
-> durable A evidence + canonical fingerprint
-> bounded REPAIR / local VERIFY
-> confirming
-> independent Capture B traversal
-> terminal B count/fingerprint
-> A/B mismatch -> attention + Work done atomically
-> A/B equality -> baseline comparison
```

`valid` means consistent/verified relative to seller-search and the known contract for that run, not an absolute guarantee of the complete historical Mercado Libre universe.

---

# 3. BASELINE AUTHORITY RULE — CLOSED GREEN

Binding rule:

```text
conservar el más reciente válido;
evidencia superseded equivalente se elimina.
Si aparece diferencia, conservar baseline anterior + run attention hasta resolver.
```

Runtime behavior now:

```text
no prior valid baseline
-> internally confirmed run becomes valid

prior valid baseline fingerprint == new internally confirmed fingerprint
-> new run becomes valid
-> prior equivalent valid run is deleted
-> prior A+B evidence pruned by existing ON DELETE CASCADE

prior valid baseline fingerprint != new internally confirmed fingerprint
-> prior baseline remains valid and untouched
-> new run becomes attention
-> new A+B evidence remains durable for diagnosis
```

No baseline pointer, history table, baseline engine, new Work type/status, second queue or cleanup engine was added.

---

# 4. K6c-1 RED / GREEN

RED:

```text
04fbc19d1a4eb99eec63cb6318cecbcfc0f9ccae
test(v3-k6c1): prove divergent baseline attention
```

RED QA:

```text
RUN=38075246021
JOB=114280747237
PHP=8.5.11
PHPSTAN=0
PHPUNIT=208 tests
ASSERTIONS=1475
FAILURES=1
MEMORY=22 MB
REAL_MELI_HTTP=0
```

Single intended failure:

```text
Tests\Integration\SalesAuditBaselineLifecycleTest::
testNewInternallyConfirmedDifferentFingerprintPreservesPriorBaselineAndEndsAttention

Expected: attention
Actual:   valid
```

GREEN:

```text
0c164793e12f3f289b2f16e93e1bda265b49eff2
feat(v3-k6c1): guard divergent valid baseline
```

Minimal implementation:

```text
terminal B first keeps existing B-vs-A mismatch guard
then, only when A == B internally:
  scoped self-JOIN checks prior valid baseline(s)
  same company/account/period/contract
  if any prior valid fingerprint differs -> current confirming -> attention
  otherwise current confirming -> valid
  then K6c-0 equivalent pruning remains unchanged
```

Everything remains inside existing `WorkRepository::completeCurrentClaim` transaction, so terminal B evidence + lifecycle transition + Work completion are atomic.

---

# 5. K6c-1 GREEN NOISE AUDIT

Delta from RED checkpoint `e8f668b90b80d86363cba70534abfbe4361bcb90` to functional GREEN `0c164793e12f3f289b2f16e93e1bda265b49eff2`:

```text
1 production file only
app/Modules/Sales/Audit/SalesAuditHandler.php
+33/-0
```

No:

```text
schema change
table/column
new class
repository
engine
state
Work type/status
queue
cron
```

K6c-0 equivalent replacement test remains GREEN and K6c-1 divergent baseline test is now GREEN.

---

# 6. BASELINE LIFECYCLE STATUS

```text
K6c-0 equivalent replacement = GREEN
K6c-1 divergent baseline preservation/attention = GREEN
```

Baseline lifecycle is functionally closed for the currently frozen authority rule.

Do not expand it into history/versioning/recovery abstractions without new evidence.

---

# 7. EXACT NEXT MICROBLOCK — K6c-DOC ONLY

When user says `continua`:

1. verify branch HEAD equals this checkpoint commit;
2. update `README.md` to state baseline lifecycle GREEN through K6c-1;
3. update `docs/ERP2_AUTHORITY.md` with the exact three-case baseline rule:
   - no prior baseline -> valid;
   - equivalent prior valid -> replace/prune old;
   - divergent prior valid -> preserve old + new attention;
4. remove obsolete statements that baseline lifecycle remains unimplemented;
5. keep G4 `IN PROGRESS` because start UX / duplicate-active guard and exact-order 404 classification remain;
6. no app/schema/test changes;
7. audit diff is documentation-only;
8. update this checkpoint and STOP.

Do not open start UX or exact-order 404 in the same microblock.

---

# 8. OPEN G4 GAPS AFTER K6c-1

```text
baseline lifecycle = GREEN
Sales Audit start UX + duplicate active-run guard = OPEN
exact order 404 final audit classification = OPEN
```

Other domains/ops remain separate:

```text
sale_fee schema/persistence alignment before Financial
webhook_events lifecycle
MariaDB session timezone certification
Billing C0 sanitized MCO smoke
Financial no-double-count
Hosting/runtime/main protection
```

---

# 9. GATES

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary + K6a guards |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for current Sales paths |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — core A/B + baseline lifecycle GREEN; start/duplicate-active + exact-order 404 remain |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

---

# 10. TOOLING NOTE

Authoritative branch:

```text
impl/v3-b-sales-audit-20261010
```

Auxiliary refs `tmp` and `impl/v3-b-sales-audit-20261010-red` remain accidental/non-authoritative older refs. Do not use them for continuation.

The user has declined Work-mode handoff. Continue through the GitHub connector unless the user later explicitly chooses otherwise.

---

# 11. STOP CONDITIONS

```text
STOP now until explicit user continua
NO K6c-DOC yet
NO start UX
NO duplicate-active guard implementation
NO exact-order 404 work
NO Billing
NO Financial
NO merge
NO deploy
NO real Mercado Libre HTTP
NO remote writes
```
