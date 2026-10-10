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

# 1. CORE A/B SALES AUDIT CERTIFICATION — GREEN THROUGH K6b-5

Last functional GREEN:

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

Runtime truth now:

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
-> mismatch A/B -> attention + Work done atomically
-> equality A/B -> valid + completed_at + Work done atomically
```

`valid` means consistent/verified relative to seller-search and the known contract for that run. It is not an absolute guarantee of the full historical Mercado Libre universe.

A and B evidence remain durable. A canonical fingerprint remains the reference fingerprint for the run.

No second audit run, second queue, ConfirmRepository, ConfirmEngine, confirm table, confirm_count/hash columns, new Work type or new Work status exists.

---

# 2. K6b-DOC2 — CLOSED

Documentation sync commits:

```text
33fb2ff894a3baedad942674637fdde657ca7685
docs(v3-k6b): sync README through valid confirmation

737a1ba39714580be754d4c1f5f3003ecbe2253e
docs(v3-k6b): sync authority through valid confirmation
```

Documentation now states:

```text
K6b-3 independent Capture B traversal = GREEN
K6b-4 terminal B mismatch -> attention = GREEN
K6b-5 terminal B equality -> valid + completed_at = GREEN
```

Obsolete claims that equality/valid was still unimplemented were removed.

G4 remains `IN PROGRESS`, but only because lifecycle/start/404 operational gaps remain; the core A/B certification path itself is GREEN.

---

# 3. DOC2 NOISE AUDIT

Compared previous checkpoint:

```text
bba00aa0e8f842128f2d42d1821149b768f65524
```

to documentation-sync head before this checkpoint:

```text
737a1ba39714580be754d4c1f5f3003ecbe2253e
```

Exact changed files:

```text
README.md
  +38/-39

docs/ERP2_AUTHORITY.md
  +17/-17
```

Therefore DOC2 changed only documentation:

```text
no app code
no schema
no tests
no table/column
no state
no Work type/status
no queue
no engine
```

No new functional QA was required for DOC2 because no executable file changed. The authoritative functional QA remains run `38072885363` at `81dd33c...`.

---

# 4. BASELINE AUTHORITY RULE — FROZEN, NOT YET IMPLEMENTED

Binding rule:

> conservar el más reciente válido; evidencia superseded equivalente se elimina. Si aparece diferencia, conservar baseline anterior + run attention hasta resolver.

Implications to prove before implementation:

```text
new attention run must not destroy/degrade prior valid baseline
new valid run may supersede equivalent prior valid baseline
superseded evidence may be pruned by replacement, not blind age
business truth must not depend on retained Work rows
no history engine/table unless a real query/integrity need proves it
```

Do not assume the existing schema/start path already enforces this. Inspect first, then RED.

---

# 5. EXACT NEXT MICROBLOCK — K6c-0 RED ONLY

When user says `continua`:

1. verify branch HEAD equals this checkpoint commit;
2. inspect only the existing Sales Audit run creation/start/lifecycle queries and schema constraints relevant to multiple runs for the same company/account/period/contract;
3. do DELETE/SIMPLIFY/REUSE/MERGE check before proposing persistence;
4. add the minimum RED proving the baseline safety rule;
5. preferred first RED guarantee: an existing `valid` baseline survives unchanged when a newer independent run ends `attention`;
6. if the existing lifecycle already satisfies that guarantee accidentally, strengthen RED toward replacement semantics only as needed;
7. do not implement GREEN in the RED microblock;
8. run full QA and confirm the intended failure only;
9. noise-audit the RED;
10. checkpoint and STOP.

Do not invent a `baseline_id`, history table, baseline engine, new Work type/status or second queue without evidence from the RED.

---

# 6. NOT IN K6c-0 RED

```text
NO start UX / active-run guard implementation
NO exact order 404 final classification
NO sale_fee alignment
NO webhook cleanup
NO MariaDB timezone certification
NO Billing Task 2 before C0 sanitized smoke
NO Financial
NO merge
NO deploy
NO real Mercado Libre HTTP
NO remote writes
```

---

# 7. OPEN GAPS

G4 Sales Audit remaining:

```text
baseline/valid lifecycle
Sales Audit start UX + duplicate active-run guard
exact order 404 final audit classification
```

Other domains/ops:

```text
sale_fee schema/persistence alignment before Financial
webhook_events lifecycle
MariaDB session timezone certification
Billing C0 sanitized MCO smoke
Financial no-double-count
Hosting/runtime/main protection
```

---

# 8. GATES

| Gate | Status |
|---|---|
| G1 REMOTE_TRUTH | PASS for implemented boundary + K6a guards |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS for current Sales paths |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS — core A/B certification through valid GREEN; lifecycle/start/404 gaps remain |
| G5 BILLING_CURSOR_TRUTH | BLOCKED on C0 sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

---

# 9. TOOLING NOTE

Authoritative branch:

```text
impl/v3-b-sales-audit-20261010
```

Auxiliary refs `tmp` and `impl/v3-b-sales-audit-20261010-red` are accidental/non-authoritative and point to older checkpoints. Do not use them for continuation.

The user declined a Work-mode handoff in this turn. Continue through the GitHub connector unless the user later chooses otherwise.

---

# 10. STOP CONDITIONS

```text
STOP now until explicit user continua
NO K6c-0 RED yet
NO baseline implementation
NO start UX
NO Billing
NO Financial
NO merge
NO deploy
NO real Mercado Libre HTTP
NO remote writes
```
