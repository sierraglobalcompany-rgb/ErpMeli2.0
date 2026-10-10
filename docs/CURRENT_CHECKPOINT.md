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

# 1. LAST FUNCTIONAL GREEN — CORE A/B THROUGH K6b-5

```text
81dd33c863bb1ec7eea1bb40cb51ad55167cbc69
feat(v3-k6b5): mark matching confirmation valid
```

Fresh full QA for that functional SHA:

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

Core runtime truth:

```text
CAPTURE A
-> durable A evidence + canonical fingerprint
-> bounded REPAIR / local VERIFY
-> confirming
-> independent Capture B traversal
-> terminal B count/fingerprint
-> mismatch A/B -> attention + Work done atomically
-> equality A/B -> valid + completed_at + Work done atomically
```

`valid` remains relative to seller-search and the known contract for that run, not an absolute guarantee of the complete historical Mercado Libre universe.

---

# 2. BASELINE AUTHORITY RULE — BINDING

```text
conservar el más reciente válido;
evidencia superseded equivalente se elimina.
Si aparece diferencia, conservar baseline anterior + run attention hasta resolver.
```

No `baseline_id`, history table, baseline engine, second queue or new Work status is justified by current evidence.

---

# 3. K6c-0 DISCOVERY

Existing schema/runtime facts:

1. `sales_audit_runs` has no uniqueness constraint for `(company_id, account_id, period_key, contract_version)`.
2. `createCapturingRun()` simply inserts a new independent `capturing` run.
3. terminal mismatch/valid transitions are scoped by current run `id`.
4. therefore a newer `attention` run naturally leaves an older `valid` run untouched.
5. there is currently no baseline replacement/pruning logic.
6. therefore a newer equivalent run reaching `valid` leaves both old and new runs as durable `valid` baselines.
7. `sales_audit_orders` has `ON DELETE CASCADE` from `sales_audit_runs`, so deleting a superseded run can prune its evidence without a second cleanup mechanism.

Because the preferred first safety guarantee (attention preserves old valid) already holds structurally, K6c-0 RED was strengthened to the missing replacement guarantee.

---

# 4. K6c-0 — EQUIVALENT VALID BASELINE REPLACEMENT — RED CONFIRMED

RED commit:

```text
115365d955ac90ce87970ad8455305f1432bd3db
test(v3-k6c0): prove equivalent valid baseline replacement
```

Test:

```text
tests/Integration/SalesAuditBaselineLifecycleTest.php
```

Scenario:

```text
existing baseline run:
  scope = company 1 / account 1 / 2026-10-01 / seller-search-v1
  status = valid
  canonical_count = 2
  set_hash = hash({200000000100, 200000000101})
  durable A+B evidence exists

new independent run:
  same scope/contract
  A fingerprint exactly equals prior baseline
  B independently confirms same A fingerprint
  new run reaches valid
```

Required lifecycle contract:

```text
new equivalent run remains valid
current Work done
exactly one durable valid baseline remains for scope/contract
old equivalent valid run is removed
old superseded evidence is removed by replacement
new A+B evidence remains durable
```

Current behavior:

```text
new run -> valid
old run -> still valid
valid baseline count = 2
```

This is the exact missing behavior.

---

# 5. K6c-0 RED QA

```text
RUN=38074404366
JOB=114278236404
PHP=8.5.11
PHPSTAN=0
PHPUNIT=207 tests
ASSERTIONS=1455
FAILURES=1
MEMORY=22 MB
REAL_MELI_HTTP=0
```

Single intended failure:

```text
Tests\Integration\SalesAuditBaselineLifecycleTest::
testNewEquivalentValidRunSupersedesPriorValidBaselineAndPrunesItsEvidence

Equivalent valid replacement must leave exactly one durable valid baseline.
Failed asserting that 2 is identical to 1.
```

Failure line:

```text
tests/Integration/SalesAuditBaselineLifecycleTest.php:165
```

The normal expected Slim 404 output from `BootstrapTest::testStoragePathIsNotExposedAsApplicationRoute` also appears in logs but is benign test noise and not a failure.

---

# 6. RED NOISE AUDIT

Delta from prior checkpoint `207d18b...` to RED `115365d...`:

```text
1 file added only
tests/Integration/SalesAuditBaselineLifecycleTest.php
+213/-0

no app code
no schema
no table/column
no state
no Work type/status
no queue
no engine
```

---

# 7. EXACT NEXT MICROBLOCK — K6c-0 GREEN ONLY

When user says `continua`:

1. verify branch HEAD equals this checkpoint commit;
2. confirm RED SHA `115365d...`, run `38074404366`, exactly one intended failure;
3. inspect only the existing terminal B equality path and current transaction boundary;
4. implement the minimum equivalent-baseline replacement semantics;
5. only on the equality-success path, prune prior `valid` run(s) with the same:
   - company/account;
   - period_key;
   - contract_version;
   - canonical_count;
   - set_hash;
   - and `id <> current run`;
6. preserve the current run as the newest `valid` baseline;
7. rely on existing FK `ON DELETE CASCADE` for superseded `sales_audit_orders` evidence; do not add cleanup engine/table;
8. keep replacement inside the existing `completeCurrentClaim` transaction so terminal B evidence + valid transition + baseline replacement + Work completion remain atomic;
9. do not touch mismatch -> attention behavior;
10. do not yet implement the different-fingerprint baseline rule; that requires its own RED after K6c-0 GREEN;
11. run fresh full QA;
12. noise-audit RED -> GREEN;
13. checkpoint and STOP.

Preferred KISS direction:

```text
EXTEND the existing terminal B equality transaction
with one guarded delete of prior equivalent valid run(s)
```

No new repository/engine/table/state is justified unless the minimal path proves impossible.

---

# 8. FOLLOWING BASELINE RED — DO NOT OPEN YET

After K6c-0 GREEN, the next lifecycle behavior should prove:

```text
prior valid baseline fingerprint != new internally-confirmed A/B fingerprint
-> prior valid baseline survives unchanged
-> new run must end attention, not become a second conflicting valid baseline
```

Do not implement this preemptively in K6c-0 GREEN.

---

# 9. OPEN G4 GAPS

```text
baseline lifecycle:
  - equivalent replacement RED confirmed, GREEN next
  - differing fingerprint -> preserve baseline + new attention still needs RED/GREEN
Sales Audit start UX + duplicate active-run guard
exact order 404 final audit classification
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

# 10. GATES

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary + K6a guards |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for current Sales paths |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — core A/B certification GREEN; baseline equivalent replacement RED confirmed; lifecycle/start/404 remain |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

---

# 11. TOOLING NOTE

Authoritative branch:

```text
impl/v3-b-sales-audit-20261010
```

Auxiliary refs `tmp` and `impl/v3-b-sales-audit-20261010-red` remain accidental/non-authoritative older refs. Do not use them for continuation.

The user has declined Work-mode handoff. Continue through the GitHub connector unless the user later explicitly chooses otherwise.

---

# 12. STOP CONDITIONS

```text
STOP now until explicit user continua
NO K6c-0 GREEN yet
NO different-baseline implementation yet
NO start UX
NO exact-order 404 work
NO Billing
NO Financial
NO merge
NO deploy
NO real Mercado Libre HTTP
NO remote writes
```
