# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED — STOP AFTER THIS CHECKPOINT**  
**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Functional HEAD before this docs-only checkpoint:** `1931746b264866f5d45d8017ca79af8dc606d0a1`  
**Previous checkpoint:** `d011683bccbf4bd20849f37c2b634b83da9efef8`  
**Remote writes:** OFF  
**REAL_MELI_HTTP:** `0`

## Execution law

```text
1 microblock at a time
RED -> confirm intended failure -> minimal GREEN -> full QA -> noise audit -> checkpoint
DELETE -> SIMPLIFY -> REUSE -> MERGE -> EXTEND -> ADD
```

Authority order:

```text
1. code/schema at branch HEAD
2. tests/CI at relevant SHA
3. this checkpoint
4. docs/ERP2_AUTHORITY.md
5. recent explicit user decisions
6. README.md
7. historical handoffs/plans
```

---

# 1. PHP 8.3–8.5 — CLOSED GREEN

ERP MELI 2.0 is CI-certified on PHP 8.3, 8.4 and 8.5.

Persistent contract:

```text
composer require.php = >=8.3 <8.6
RuntimePreflight PASS only for >=8.3.0 and <8.6.0
required extensions include phar + zlib
one GitHub Actions matrix: 8.3 / 8.4 / 8.5
same composer.lock on all three runtimes
```

Functional GREEN:

```text
1931746b264866f5d45d8017ca79af8dc606d0a1
fix(platform): keep explicit preflight range check
```

Final matrix:

```text
RUN=38079245005
PHP 8.3.35 JOB=114292569616 PHPSTAN=0 PHPUNIT=209/209 ASSERTIONS=1483 MEMORY=22MB
PHP 8.4.26 JOB=114292569582 PHPSTAN=0 PHPUNIT=209/209 ASSERTIONS=1483 MEMORY=22MB
PHP 8.5.11 JOB=114292569448 PHPSTAN=0 PHPUNIT=209/209 ASSERTIONS=1483 MEMORY=22MB
REAL_MELI_HTTP=0
```

README still has one docs-only stale stack label (`PHP 8.5`) that must later be synchronized to `PHP 8.3–8.5`.

---

# 2. SALES AUDIT — CURRENT FUNCTIONAL TRUTH

Last Sales Audit functional GREEN:

```text
0c164793e12f3f289b2f16e93e1bda265b49eff2
feat(v3-k6c1): guard divergent valid baseline
```

Core:

```text
Capture A
-> durable A evidence + canonical fingerprint
-> bounded repair / local verify
-> independent Capture B
-> A/B mismatch -> attention
-> A/B equality -> baseline comparison
```

Baseline lifecycle GREEN:

```text
no prior valid -> new internally confirmed run becomes valid
prior equivalent valid -> new valid + old equivalent run/evidence removed
prior divergent valid -> old baseline preserved + new run attention
```

`valid` remains relative to seller-search + the known contract, not absolute Mercado Libre historical truth.

---

# 3. FORENSIC AUDIT — SalesAuditHandler

This audit was performed before adding more G4 behavior specifically to avoid reproducing ERP1 complexity.

## 3.1 What is healthy and must remain

```text
one sales.audit Work type
one Work engine
one SalesAuditHandler for remote Capture A + Capture B traversal
one SalesAuditRepository for durable audit data
one SalesAuditRepairHandler for bounded repair orchestration
same A/B evidence table with capture_pass
no confirm engine
no baseline engine
no state-machine framework
no second queue
no extra Work statuses
```

Current durable run states remain understandable and justified:

```text
capturing
repairing
confirming
valid
attention
unavailable
```

Do NOT split A and B into separate handlers merely to reduce file size. They deliberately reuse the same remote traversal/source contract.

Do NOT merge SalesAuditRepairHandler into the main handler; that would make the main handler larger and blur a responsibility that is currently clear.

## 3.2 Main design debt found

`SalesAuditHandler` now owns too many responsibilities simultaneously:

```text
payload validation
run/context validation
source-horizon decision
OAuth
remote HTTP
remote error policy
page normalization
A evidence persistence
A terminal transition
B evidence persistence
B-vs-A comparison
baseline comparison
valid/attention transition
baseline pruning
continuation enqueue
```

The highest-risk part is not OAuth/HTTP. It is durable lifecycle SQL that accumulated directly inside the handler during K6b/K6c.

Direct durable SQL currently embedded in the handler includes:

```text
capturing -> unavailable
confirming -> attention on A/B mismatch
confirming -> attention on divergent prior valid baseline
confirming -> valid + completed_at
DELETE superseded equivalent valid baseline
```

This violates clean ownership because `SalesAuditRepository` already owns the other Sales Audit durable queries/transitions.

This is patch accumulation, not yet a functional failure, and should be simplified before adding more G4 behavior.

## 3.3 Correct target ownership

Binding design:

```text
SalesAuditHandler
  = remote boundary + orchestration only
  = payload/context -> OAuth -> orders.search -> normalize -> retry/defer policy
  = asks WorkRepository for atomic completion
  = asks SalesAuditRepository to mutate durable audit truth

SalesAuditRepository
  = all sales_audit_runs / sales_audit_orders durable truth
  = fingerprints
  = run transitions
  = baseline comparison/pruning

SalesAuditRepairHandler
  = one bounded repair child at a time
  = keep as-is unless new evidence appears

WorkRepository
  = Work claim/transaction/completion/retry/defer/fail
  = no business truth
```

No new `ConfirmRepository`, `BaselineService`, `AuditStateMachine`, `FinalizerEngine`, transaction manager or second handler is justified.

## 3.4 Minimal repository consolidation target

Prefer extending the existing repository with only behavior that it already logically owns.

Likely minimal methods:

```text
markUnavailable(...)
finalizeConfirmation(...)
```

`finalizeConfirmation(...)` should encapsulate the existing terminal-B durable lifecycle:

```text
A/B mismatch -> current run attention
A/B equality + prior divergent valid baseline -> current run attention
A/B equality + no divergent baseline -> current run valid + completed_at
then prune superseded equivalent valid baseline(s)
```

It must remain inside the SAME Work completion transaction and use the same PDO composition already used by `bin/work.php`.

Do not move OAuth, HTTP, page normalization, retries or Work enqueue into the repository.

## 3.5 Atomicity audit

Current production composition is correct:

```text
WorkRepository($pdo)
SalesAuditRepository($pdo)
```

share the same PDO in `bin/work.php`.

`WorkRepository::completeCurrentClaim()`:

```text
BEGIN
lock current running Work claim
persist business callback
mark Work done
COMMIT
```

and rolls back on Throwable.

Therefore current terminal evidence + audit transition + Work completion are atomic as long as repository consolidation continues using that same injected PDO.

Do NOT add a transaction abstraction merely to encode this.

## 3.6 Error-classification defect found

Current handler has a broad final:

```text
catch (RuntimeException)
-> Work failed
-> error_code = meli_sales_audit_contract
```

around the persistence/completion transaction.

That conflates two fundamentally different failures:

```text
REMOTE CONTRACT FAILURE
- drifting remote_total
- duplicate remote order evidence
- incomplete terminal remote evidence

INTERNAL DURABLE STATE/PERSISTENCE FAILURE
- fingerprint already persisted / invalid lifecycle
- transition no longer applicable
- baseline lifecycle persistence failure
- database/integrity failure
```

Internal failures must NOT be blamed on Mercado Libre.

A current test explicitly preserves this bad classification:

```text
SalesAuditTerminalFingerprintTest::testFingerprintPersistenceFailureRollsBackTerminalObservationAndFailsWork
```

It currently expects:

```text
meli_sales_audit_contract
```

when the actual failure is an internal run/fingerprint lifecycle conflict.

That test expectation is now considered superseded design debt and is the correct first RED.

Reuse the already-existing internal classification:

```text
sales_audit_state
```

rather than inventing an error taxonomy unless later evidence proves it necessary.

Remote page/remote evidence incoherence keeps:

```text
meli_sales_audit_contract
```

Rate limit / remote transient behavior stays unchanged:

```text
429 -> defer
5xx/transport -> bounded retry
```

## 3.7 Duplicate active-run invariant

Schema currently has only a normal scope index on `sales_audit_runs`; it does NOT prevent more than one active run for the same:

```text
company_id
account_id
period_key
contract_version
```

Active phases are:

```text
capturing
repairing
confirming
```

The global WorkRunner named lock means current production Work processing is serialized, so this is NOT currently a parallel-worker race.

It is still a correctness/efficiency gap because duplicate starts could create two independent active audits that:

```text
duplicate Mercado Libre calls
duplicate evidence
consume Work unnecessarily
later compete logically for baseline replacement/attention depending on completion order
```

The duplicate-active guard belongs to the future START microblock, after handler cleanup. It must be enforced robustly at the database/start boundary, not only disabled in UI.

Do not mix this with the handler refactor.

## 3.8 ERP1 lessons explicitly protected

The target design avoids the ERP1 failure patterns:

```text
NO parallel reconciler
NO business truth in Work rows
NO retry/recovery engine by domain
NO extra technical states
NO A/B second audit run
NO historical engine
NO handler-per-phase proliferation
NO patch SQL scattered across orchestrators
NO duplicate active historical jobs by design
```

---

# 4. KEEP / SIMPLIFY / MERGE / DELETE DECISIONS

```text
KEEP
- SalesAuditHandler as remote orchestrator
- SalesAuditRepairHandler
- SalesWorkProcessor
- SalesAuditWindow
- current run states
- A/B capture_pass evidence model
- Work transaction model
- global runner lock

SIMPLIFY / MERGE
- move direct audit lifecycle SQL from Handler into existing SalesAuditRepository
- move unavailable transition into existing SalesAuditRepository
- narrow persistence error classification so internal state != remote contract

DELETE
- after repository consolidation, delete the superseded direct SQL blocks from SalesAuditHandler
- replace the obsolete test expectation that internal fingerprint state failure is a Mercado Libre contract failure

DO NOT ADD
- ConfirmRepository
- BaselineService
- SalesAuditStateMachine
- FinalizerEngine
- extra queue
- extra Work type/status
- extra run state
- history table
- baseline pointer
```

---

# 5. EXACT NEXT MICROBLOCK — SAH-0 RED ONLY

**Do not refactor production yet.**

Next execution:

1. verify branch HEAD equals this docs-only checkpoint;
2. keep PHP 8.3/8.4/8.5 contract unchanged;
3. change/strengthen only the existing internal fingerprint persistence failure test so it requires:

```text
Work status = failed
last_error_code = sales_audit_state
terminal observation rolled back
run durable fingerprint/state not falsely mutated
```

4. run full matrix/QA as appropriate and confirm one intended failure caused only by current broad persistence catch returning `meli_sales_audit_contract`;
5. no GREEN in the RED microblock;
6. noise audit;
7. checkpoint and STOP.

After SAH-0 RED, expected sequence is:

```text
SAH-0 GREEN
  separate remote-contract failure classification from internal audit-state failure

SAH-1 SIMPLIFY
  behavior-preserving repository consolidation:
  markUnavailable + terminal-B lifecycle into SalesAuditRepository
  remove superseded direct SQL from SalesAuditHandler
  no new architecture

SAH-2 ACTIVE-RUN RED/GREEN
  one active audit per company/account/period/contract

then resume remaining G4:
  start UX
  exact-order 404 audit classification
```

---

# 6. OTHER PROJECT GAPS — DO NOT MIX NOW

```text
README PHP stack docs-only sync
webhook timestamp timezone hardening
webhook_events lifecycle
sale_fee alignment before Financial
MariaDB session timezone certification
Billing C0 sanitized MCO smoke
Financial no-double-count
Hosting/runtime/main protection
```

Gates remain:

```text
G1 REMOTE_TRUTH: PASS for implemented boundary + guards
G2 WORK_SAFETY: PASS
G3 RATE_SAFETY: PASS current Sales
G4 SALES_AUDIT_TRUTH: IN PROGRESS
G5 BILLING_CURSOR_TRUTH: BLOCKED on C0
G6 FINANCIAL_NO_DOUBLE_COUNT: NOT STARTED
G7 WRITE_FAIL_CLOSED: PASS
G8 HOSTING_REALITY: NOT CERTIFIED
```

Do not merge/deploy or enable remote Mercado Libre writes without explicit user authorization.
