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

# 1. LAST FUNCTIONAL GREEN — K6c-0 EQUIVALENT BASELINE REPLACEMENT

Functional GREEN:

```text
436210d0ff576de8189dfb35434305de271b9ce0
feat(v3-k6c0): replace equivalent valid baseline
```

Fresh full QA:

```text
RUN=38074658105
JOB=114278993275
PHP=8.5.11
PHPSTAN=0
PHPUNIT=207/207 PASS
ASSERTIONS=1460
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
-> mismatch A/B -> attention + Work done atomically
-> equality A/B -> valid + completed_at + Work done atomically
```

`valid` means consistent/verified relative to seller-search and the known contract for that run, not an absolute guarantee of the complete historical Mercado Libre universe.

---

# 3. BASELINE AUTHORITY RULE — BINDING

```text
conservar el más reciente válido;
evidencia superseded equivalente se elimina.
Si aparece diferencia, conservar baseline anterior + run attention hasta resolver.
```

No `baseline_id`, history table, baseline engine, second queue or new Work status is justified by current evidence.

---

# 4. K6c-0 — EQUIVALENT VALID REPLACEMENT — CLOSED GREEN

RED:

```text
115365d955ac90ce87970ad8455305f1432bd3db
test(v3-k6c0): prove equivalent valid baseline replacement
```

RED proved the exact missing behavior:

```text
old equivalent run = valid
new equivalent run = valid
valid baseline count = 2
```

GREEN now extends only the existing terminal B equality transaction.

After the current run transitions `confirming -> valid`, one guarded `DELETE ... JOIN` removes other runs that are simultaneously:

```text
status = valid
same company_id
same account_id
same period_key
same contract_version
same canonical_count
same set_hash
id != current run
```

The current run remains the newest valid baseline.

Existing FK semantics do the evidence pruning:

```text
sales_audit_orders.audit_run_id
-> sales_audit_runs.id
ON DELETE CASCADE
```

Therefore no cleanup engine/table/repository was added.

All of this runs inside the same existing `WorkRepository::completeCurrentClaim` transaction, so:

```text
terminal B observation
+ valid transition
+ equivalent baseline replacement
+ superseded evidence cascade
+ Work done
```

commit atomically.

Mismatch -> `attention` behavior remains untouched.

---

# 5. K6c-0 GREEN NOISE AUDIT

Delta from RED checkpoint `fea0e88ac7954df52bde8ea44765477161242ea9` to functional GREEN `436210d0ff576de8189dfb35434305de271b9ce0`:

```text
1 production file only
app/Modules/Sales/Audit/SalesAuditHandler.php
+15/-0
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

The existing K6c-0 RED test now passes and verifies that the old baseline row and its A+B evidence are gone while the new baseline A+B evidence remains.

---

# 6. IMPORTANT REMAINING BASELINE GAP

K6c-0 intentionally handles only equivalent replacement.

The following authority rule is still NOT implemented/proved:

```text
prior valid baseline fingerprint != new run fingerprint
AND new run A == new run B internally
-> prior valid baseline must survive
-> new run must end attention
-> new run must NOT become a second conflicting valid baseline
```

Current terminal B equality code can still mark such a new internally-consistent but baseline-different run `valid` because it only compares B against that same run's A before the equivalent-pruning query.

This is the next RED.

---

# 7. EXACT NEXT MICROBLOCK — K6c-1 RED ONLY

When user says `continua`:

1. verify branch HEAD equals this checkpoint commit;
2. keep K6c-0 GREEN unchanged;
3. add the minimum integration RED with an existing durable `valid` baseline for the same company/account/period/contract;
4. baseline fingerprint example: `{200000000100, 200000000101}`;
5. new run A and B must independently agree with each other but use a different canonical set, e.g. `{200000000100, 200000000102}`;
6. require terminal Work `done`;
7. require old baseline remains `valid` with its A+B evidence intact;
8. require new run ends `attention`, not `valid`;
9. require new run A+B evidence remains durable for diagnosis;
10. require no continuation and no `order.sync` fanout;
11. do NOT implement GREEN in the RED microblock;
12. run full QA and confirm exactly one intended failure;
13. noise-audit;
14. checkpoint and STOP.

Do not add a baseline pointer/table/history engine. The existing run table should remain the first KISS target.

---

# 8. OPEN G4 GAPS

```text
baseline lifecycle:
  - equivalent valid replacement GREEN
  - differing fingerprint -> preserve prior valid + new attention needs RED/GREEN
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

# 9. GATES

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary + K6a guards |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for current Sales paths |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — core A/B + equivalent baseline replacement GREEN; divergent baseline/start/404 remain |
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
NO K6c-1 RED yet
NO divergent-baseline implementation yet
NO start UX
NO exact-order 404 work
NO Billing
NO Financial
NO merge
NO deploy
NO real Mercado Libre HTTP
NO remote writes
```
