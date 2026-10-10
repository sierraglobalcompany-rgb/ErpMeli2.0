# CURRENT CHECKPOINT — ERP MELI 2.0

**PAUSED — STOP AFTER THIS CHECKPOINT**  
**Date:** 2026-10-10  
**Branch:** `impl/v3-b-sales-audit-20261010`  
**Functional HEAD before this docs-only checkpoint:** `1931746b264866f5d45d8017ca79af8dc606d0a1`  
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

# 1. PHP-COMPAT-0 — CLOSED GREEN

Goal completed:

```text
ERP MELI 2.0 supports and is CI-certified on PHP 8.3, 8.4 and 8.5.
```

Persistent contract now:

```text
composer require.php = >=8.3 <8.6
RuntimePreflight PASS only for >=8.3.0 and <8.6.0
required runtime extensions include phar + zlib
one GitHub Actions QA matrix: 8.3 / 8.4 / 8.5
same composer.lock installs on all three runtimes
```

Functional GREEN SHA:

```text
1931746b264866f5d45d8017ca79af8dc606d0a1
fix(platform): keep explicit preflight range check
```

Final CI matrix:

```text
RUN=38079245005

PHP 8.3.35
JOB=114292569616
PHPSTAN=0
PHPUNIT=209/209 PASS
ASSERTIONS=1483
MEMORY=22 MB

PHP 8.4.26
JOB=114292569582
PHPSTAN=0
PHPUNIT=209/209 PASS
ASSERTIONS=1483
MEMORY=22 MB

PHP 8.5.11
JOB=114292569448
PHPSTAN=0
PHPUNIT=209/209 PASS
ASSERTIONS=1483
MEMORY=22 MB
```

All three also passed:

```text
syntax lint
Composer platform contract assertion
composer validate --strict
composer install from the same lock
REAL_MELI_HTTP=0
```

The usual benign Slim 404 trace from `BootstrapTest::testStoragePathIsNotExposedAsApplicationRoute` still appears while the suite remains GREEN.

## RED history

```text
3be0efc21157e2331188ce89b279a209e32622a0
test(platform): require PHP 8.3-8.5 contract

RUN=38078761003
PHPSTAN=0
PHPUNIT=209 tests
ASSERTIONS=1481
FAILURES=1 intended
Expected >=8.3 <8.6; actual ^8.5
```

First matrix attempt exposed one real PHPStan issue common to 8.3/8.4/8.5: under Composer's new platform range, PHPStan inferred the explicit `<8.6` check on `PHP_VERSION` as redundant. Minimal GREEN changed the runtime value source to `phpversion()`; PHPStan configuration was not weakened.

---

# 2. PHP-COMPAT NOISE AUDIT

Delta from pre-compatibility checkpoint:

```text
974d151c4845536f06ac73a296c5ff4c765a0189
```

to functional GREEN:

```text
1931746b264866f5d45d8017ca79af8dc606d0a1
```

contains exactly five persistent files:

```text
.github/workflows/qa.yml
app/Core/Runtime/RuntimePreflight.php
composer.json
composer.lock
tests/Integration/PlatformCompatibilityContractTest.php
```

No persistent temporary workflow.
No Sales/Billing/Financial behavior change.
No schema/table/column change.
No Work type/status/queue/engine change.
No OAuth/Mercado Libre behavior change.
No dependency-version drift during lock refresh.

---

# 3. SALES AUDIT — CURRENT FUNCTIONAL TRUTH

Last Sales Audit functional GREEN remains:

```text
0c164793e12f3f289b2f16e93e1bda265b49eff2
feat(v3-k6c1): guard divergent valid baseline
```

Core flow:

```text
Capture A
-> durable A evidence + canonical fingerprint
-> bounded repair / local verify
-> independent Capture B
-> A/B mismatch -> attention
-> A/B equality -> baseline comparison
```

Baseline lifecycle rule remains GREEN:

```text
no prior valid baseline
-> current internally confirmed run becomes valid

prior equivalent valid baseline
-> current run becomes valid
-> prior equivalent valid run removed
-> superseded A+B evidence pruned by FK cascade

prior divergent valid baseline
-> prior valid baseline preserved
-> current run becomes attention
-> current evidence preserved for diagnosis
```

No baseline pointer, history table, baseline engine, new Work type/status, second queue or cleanup engine was added.

`valid` remains relative to seller-search + the known run contract, not an absolute guarantee of the complete Mercado Libre historical universe.

---

# 4. CURRENT GAPS / NEXT WORK

Do not mix these together.

Sales Audit remaining:

```text
start UX + duplicate active-run guard
exact order 404 final audit classification
```

Other separate gaps:

```text
sale_fee schema/persistence alignment before Financial
webhook_events lifecycle / timezone hardening
MariaDB session timezone certification
Billing C0 sanitized MCO smoke
Financial no-double-count
Hosting/runtime/main protection
```

Gates:

```text
G1 REMOTE_TRUTH: PASS for implemented boundary + current guards
G2 WORK_SAFETY: PASS
G3 RATE_SAFETY: PASS current Sales
G4 SALES_AUDIT_TRUTH: IN PROGRESS — core A/B + baseline lifecycle GREEN; start/404 remain
G5 BILLING_CURSOR_TRUTH: BLOCKED on C0 sanitized MCO smoke
G6 FINANCIAL_NO_DOUBLE_COUNT: NOT STARTED
G7 WRITE_FAIL_CLOSED: PASS
G8 HOSTING_REALITY: NOT CERTIFIED
```

---

# 5. EXACT RESUME POINT

**STOP NOW.**

When work resumes:

1. verify branch HEAD equals the docs-only checkpoint commit created from this file;
2. confirm functional ancestor `1931746b264866f5d45d8017ca79af8dc606d0a1` still has matrix GREEN `38079245005`;
3. do not rerun or redesign PHP compatibility unless evidence changed;
4. README still needs its stack line synchronized from `PHP 8.5` to `PHP 8.3–8.5`; treat that as docs-only cleanup, not a new functional block;
5. then continue only one next microblock at a time from the frozen project gaps above.

Do not merge/deploy or enable remote Mercado Libre writes without explicit user authorization.
