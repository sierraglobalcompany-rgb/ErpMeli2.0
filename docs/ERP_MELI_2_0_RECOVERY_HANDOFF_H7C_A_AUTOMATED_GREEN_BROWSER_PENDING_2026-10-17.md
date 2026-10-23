# ERP MELI 2.0 — RECOVERY HANDOFF — H7C-A AUTOMATED GREEN / BROWSER PENDING

**DATE=2026-10-17**  
**PURPOSE=Recover the project, its truth, methodology and exact next action without depending on any ChatGPT conversation.**

---

# 1. REPOSITORY AUTHORITY

```text
REPO=sierraglobalcompany-rgb/ErpMeli2.0
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
BASE_BRANCH=ui/operator-shell-v1-20261014
BASE_SHA=c6f8af7f446cb327a905ae2f9bd9ee424c2d421f
CHECKPOINT_AT_HANDOFF_START=e4a428c9a4f0e5d9aff20906230dd691b4c00a1f
H7C_A_GREEN=d8cfbe04a924304cb1234153fe873a34bef4bab4
H7C_A_GREEN_CI=39049794704
REMOTE_WRITES=OFF
REAL_MELI_HTTP=0
HOSTINGER_DEPLOYED=NO
REAL_MELI_WRITES=NO
MERGE_AUTHORIZED=NO
STOP_SAFE=YES
```

`main` is NOT the current Human-First product authority. Resume from `ui/design-master-v1-20261015` and PR #19.

Primary recovery files:

```text
AGENTS.md
docs/ERP2_AUTHORITY.md
design-system/erp-meli-2-0/MASTER.md
docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md
docs/UX_HUMAN_FIRST_MASTER_AUDIT_AND_IMPROVEMENT_PLAN_2026-10-16.md
docs/CURRENT_CHECKPOINT.md
this handoff
```

---

# 2. NON-NEGOTIABLE PROJECT LAWS

## KISS

```text
simple
efficient
optimized
effective
direct A -> B
no architecture for architecture's sake
```

## Anti-noise

Do not drag historical, speculative or duplicated behavior into ERP2. Keep, remove, merge or optimize only with evidence.

## Anti-patch

Fix root owners and root causes. Do not create patch chains, page-specific overrides or compensating hacks when the shared owner is wrong.

## Human-First / Truth Before Beauty

```text
UNKNOWN/null NEVER becomes 0
UI NEVER invents data, state, permissions, totals, stock or ownership
one page = one primary job/action
technical detail = secondary / progressive disclosure
mobile is not shrunk desktop
WCAG 2.2 AA
visible human language before machine identifiers when both exist
evidence before GREEN
```

## Merge law

```text
NO MERGE WITHOUT EXPLICIT USER APPROVAL
```

---

# 3. ERP1 FAILURE CONTEXT — DO NOT REPEAT IT

ERP1 had serious instability around Billing/Financial/Work, including 429 symptoms, FIFO/work queue problems and timing/wait behavior. Architecture also drifted toward batch/job behavior where the intended boundary was simpler HTTP behavior.

Important truth:

```text
UNIVERSAL_429_ROOT_CAUSE=NOT_PROVEN
```

Therefore Human-First work MUST NOT redesign Financial/Work/429 retry/pacing/queue/batching/transport behavior without fresh evidence. Historical suspicion is not authority.

---

# 4. WORKING METHODOLOGY

Use Superpowers-style TDD and evidence discipline.

For every bounded implementation block:

```text
1. READ-ONLY PREFLIGHT
2. IDENTIFY ONE ROOT DEFECT / OWNER
3. PRESENT SHORT DESIGN WHEN REQUIRED
4. USER APPROVAL FOR BOUNDED DESIGN
5. WRITE REAL RED TEST FIRST
6. COMMIT RED
7. RUN CI AND PROVE EXPECTED FAILURE
8. APPLY MINIMAL GREEN IN ROOT OWNER
9. COMMIT GREEN
10. RUN FRESH FULL CI
11. IF UI/RESPONSIVE/KEYBOARD CHANGED -> REAL BROWSER CERTIFICATION
12. INDEPENDENTLY AUDIT BROWSER EVIDENCE
13. UPDATE CURRENT_CHECKPOINT + PR
14. ONLY THEN MOVE TO NEXT MICROBLOCK
```

Never claim PASS because code looks correct. PASS requires fresh evidence.

Expected test-intent Slim traces may appear in logs (e.g. deliberate missing-table, invalid loopback URL, deliberate hard-delete absence). Treat them as failures only if PHPUnit/CI actually reports them as failures.

---

# 5. TOOL / AGENT RESPONSIBILITIES

## ChatGPT

ChatGPT is the primary project driver and should perform as much as possible directly:

```text
audit
GitHub contrast
root-cause reasoning
design
TDD contracts
implementation
CI inspection
documentation
checkpointing
independent evidence audit
```

Do NOT delegate routine implementation to Codex merely because Codex exists.

## Antigravity

Use Antigravity primarily when a real local Chrome/browser/runtime gate cannot be controlled from ChatGPT.

Antigravity certification prompts must be:

```text
CERTIFICATION ONLY
NO code changes
NO test changes
NO docs changes
NO commits
NO push
NO merge
NO product fixes
zero business side effects
```

If a browser gate fails, Antigravity captures evidence and stops. ChatGPT owns diagnosis and TDD repair.

## Codex

Use Codex only where local-machine execution or another barrier materially requires it. Prompts to Codex should be delivered as downloadable Markdown and should not expand scope.

---

# 6. CERTIFIED HUMAN-FIRST HISTORY

```text
H1_FIX1_SALE_DETAIL=GREEN
H2A_SKIP_TO_MAIN=GREEN
H2B_TYPOGRAPHY_TOUCH=GREEN
H3_DASHBOARD=GREEN
H4_SALES_LIST=FULLY_CLOSED
H5_CATALOG_INVENTORY=FULLY_CLOSED
H6A_H6F_BILLING=FULLY_CLOSED
BILLING_HUMAN_FIRST_CLOSURE=YES
H7A_GLOBAL_ADMIN_SHARED_SHELL=FULLY_CLOSED
H7B_USERS_PROGRESSIVE_DISCLOSURE=FULLY_CLOSED
```

Do not reopen a closed block without new evidence, regression or requirement.

---

# 7. H7A — GLOBAL ADMIN SHARED SHELL — CLOSED

Global pages were moved from a second `simple-page/simple-nav` navigation model into the shared ERP shell without changing authorization or business owners.

Covered:

```text
/system/companies
/system/users
/settings/system
/settings/mercadolibre
```

Both System Admin states were certified:

```text
no active company
active company
```

Browser evidence confirmed shared shell, exact five mobile slots, disabled company-bound navigation when no active company, Apps behavior, skip link, no overflow, zero business side effects and no real MELI calls.

---

# 8. H7B — USERS PROGRESSIVE DISCLOSURE — FULLY CLOSED

## TDD

```text
H7B_RED=73a8a92f6d31f1f6619dc6d4f2e3b2214032c3cc
H7B_RED_CI=39042876994
H7B_GREEN=e6711bd9ccbb5a6298c14885f4f2341040a94343
H7B_GREEN_CI=39043131947
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
PHPSTAN=PASS
PHP_8_3_RESULT=823 tests / 6999 assertions / 0 failures
```

Canonical GREEN production diff:

```text
FILE=app/Core/Http/views/users.php
FILES=1
ADDITIONS=31
DELETIONS=24
CONTROLLERS_CHANGED=NO
ROUTES_CHANGED=NO
SCHEMA_CHANGED=NO
CSS_CHANGED=NO
JS_CHANGED=NO
PERMISSIONS_CHANGED=NO
```

Behavior:

```text
Create user remains directly visible.
Each user card keeps compact factual summary visible.
Exactly 3 native disclosures:
  Identidad
  Seguridad y acceso global
  Acceso por empresa
All closed by default.
Existing POST owners/routes remain unchanged.
```

## Browser evidence

```text
CERTIFIED_HEAD=16d50961cda86ef7f886affe8676d470422607cd
ZIP_SHA256=6aa6144e6f34432e1e5e86e2aeffbd9c99b387afbc39a0f5ed9e69576a692da7
MANIFEST=18/18 exact SHA-256
DESKTOP_1440x900=PASS
MOBILE_390x844=PASS
KEYBOARD_ENTER_SPACE=PASS
FORM_OWNERSHIP=PASS
H7A_SHELL_REGRESSION=PASS
CONSOLE_APP_ERRORS=0
APPLICATION_5XX=0
REAL_MELI_NETWORK_CALLS=0
DB_BUSINESS_TABLE_HASHES_BEFORE_AFTER=IDENTICAL
BUSINESS_SIDE_EFFECTS=0
WORKING_TREE_BEFORE_AFTER=CLEAN
```

Raw snapshot files differed only in encoding/BOM bytes; normalized content and business-table hashes were identical. That is evidence-file noise, not a DB mutation.

---

# 9. H7C AUTHORITY — DESTRUCTIVE-ACTION UX

H7C is NOT generic progressive disclosure. Its authority is destructive-action Human-First UX / T3 Security-Authorization.

Sequence:

```text
H7C-A Companies: archive + permanent delete
H7C-B MELI accounts: disconnect + hard delete
H7C-C System settings: clear debug
H7C-D Users: remove System Admin privilege + remove company membership
```

One owner per microblock. Do not implement all H7C owners in one broad abstraction.

---

# 10. H7C-A COMPANIES — CURRENT ACTIVE BLOCK

## Backend/domain authority remains unchanged

```text
GET/POST /system/companies
POST /system/companies/{id}/archive
POST /system/companies/{id}/reactivate
POST /system/companies/{id}/delete
OWNER=CompanyManagementController
```

Existing authorization, CSRF and domain rules are authoritative. H7C-A is a presentation/decision-quality improvement, not a backend redesign.

## Approved bounded design

### Active company — archive

Before H7C-A, `Archivar` was a direct one-click POST.

Required behavior:

```text
native <details> second step
summary = Archivar empresa
explain that normal operation/selection stops
history is preserved
reactivation remains possible
existing /archive route preserved
CSRF preserved
```

### Archived company — reactivate

```text
Reactivar remains direct
```

Reason: restorative action, not destructive.

### Deletable archived company — permanent delete

Required behavior:

```text
render only when existing backend deletable fact is true
native <details> deliberate disclosure
summary = Eliminar definitivamente
preserve factual prerequisites/copy
required native acknowledgement checkbox
existing /delete route preserved
backend authority preserved
```

No generic modal framework. No JS dependency. No controller/schema/permission redesign.

---

# 11. H7C-A TDD EVIDENCE

## RED

```text
H7C_A_RED=1c8bae3374c7a36af075f3b5bb59c64c95cef763
H7C_A_RED_CI=39049451668
RED_PHP_8_3=FAIL EXPECTED
PHPSTAN=PASS
PHPUNIT=824 tests / 7002 assertions / exactly 1 failure
FAILED_TEST=Tests\Integration\H7CCompanyDestructiveActionUxTest::testCompanyDestructiveActionsRequireDeliberateHumanConfirmation
CAUSE=archive still rendered as direct POST with no deliberate disclosure
PRODUCTION_CHANGED_IN_RED=NO
```

This was a valid RED, not infrastructure noise.

## GREEN

```text
H7C_A_GREEN=d8cfbe04a924304cb1234153fe873a34bef4bab4
H7C_A_GREEN_CI=39049794704
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
PHPSTAN=PASS
PHP_8_3_RESULT=824 tests / 7007 assertions / 0 failures
```

RED -> GREEN production diff:

```text
COMMITS=1
FILES=1
FILE=app/Core/Http/views/companies.php
ADDITIONS=27
DELETIONS=12
CONTROLLER_CHANGED=NO
ROUTES_CHANGED=NO
SCHEMA_CHANGED=NO
CSS_CHANGED=NO
JS_CHANGED=NO
PERMISSIONS_CHANGED=NO
WORK_CHANGED=NO
FINANCIAL_CHANGED=NO
429_ARCHITECTURE_CHANGED=NO
```

Automated result:

```text
H7C_A_AUTOMATED_GREEN=YES
H7C_A_BROWSER=PENDING
H7C_A_FULLY_CLOSED=NO
```

---

# 12. EXACT NEXT STEP

Do NOT start H7C-B yet.

Exact resume:

```text
NEXT=H7C-A REAL-BROWSER CERTIFICATION ONLY
TARGET_GREEN=d8cfbe04a924304cb1234153fe873a34bef4bab4
H7C_A_AUTOMATED_GREEN=YES
H7C_A_BROWSER=PENDING
H7C_A_FULLY_CLOSED=NO
H7C_B_STARTED=NO
H7C_C_STARTED=NO
H7C_D_STARTED=NO
PR_MERGED=NO
STOP_SAFE=YES
```

Browser certification must verify at minimum:

```text
/system/companies HTTP 200
H7A shared shell preserved
active company archive disclosure closed by default
archive summary keyboard reachable and toggles natively
archive impact copy is readable
archive form remains /system/companies/{id}/archive
archive form is NOT submitted
archived company Reactivar remains direct
pristine/deletable archived company permanent-delete disclosure closed by default
delete disclosure contains factual impact/prerequisite copy
delete form remains /system/companies/{id}/delete
required acknowledgement checkbox exists
browser cannot submit delete without required acknowledgement
DO NOT actually submit delete
System Admin positive case PASS
ordinary company-admin negative authorization case PASS
desktop PASS
mobile PASS
5-slot mobile shell preserved
no horizontal overflow
no bottom-nav obstruction
console app errors=0
application 5xx=0
real MELI calls=0
business DB before/after identical
working tree before/after clean
certifier makes no code/test/docs commits/push/merge
```

Because the browser gate is side-effect-free, do not execute archive or delete merely to prove the UI. Existing backend tests remain authority for destructive execution semantics.

If ChatGPT cannot control the real browser, create a downloadable Antigravity Markdown with `CERTIFICATION ONLY / NO FIXES` and independently audit the returned report + ZIP before closing H7C-A.

---

# 13. AFTER H7C-A CLOSES

Only after automated + browser evidence are independently PASS:

```text
H7C_A_FULLY_CLOSED=YES
NEXT=H7C-B MELI ACCOUNTS DESTRUCTIVE UX READ-ONLY PREFLIGHT
```

H7C-B expected owner surface:

```text
/mercadolibre/accounts
disconnect
hard delete
```

Do not blindly clone the Companies presentation. Audit the factual MELI semantics first.

Then H7C-C, then H7C-D.

---

# 14. FROZEN / PROHIBITED

Until new evidence or explicit scope says otherwise:

```text
NO merge
NO Hostinger deployment
NO cron changes
NO real MELI mutation
NO generic modal framework
NO broad navigation redesign
NO Financial redesign
NO Work redesign
NO speculative 429 fix
NO retry/pacing/queue/batching/transport redesign
NO UNKNOWN -> 0
NO patch-chain CSS
NO reopening closed H1-H7B without new evidence
```

---

# 15. RECOVERY PROCEDURE FOR A NEW CHAT / AGENT

A new conversation MUST do this before changing code:

```text
1. Read this handoff.
2. Read docs/CURRENT_CHECKPOINT.md.
3. Read AGENTS.md and docs/ERP2_AUTHORITY.md.
4. Read Human-First master + Design Master authority.
5. Query GitHub PR #19 and confirm:
   state=open
   draft=true
   merged=false
   head=ui/design-master-v1-20261015
6. Confirm branch HEAD is this handoff/checkpoint or a safe descendant.
7. Confirm d8cfbe04a924304cb1234153fe873a34bef4bab4 is an ancestor.
8. If later commits exist, inspect them instead of assuming chat memory is newer.
9. Do not use main as product truth.
10. Resume only from the exact NEXT marker.
```

If repository evidence contradicts this handoff, repository evidence wins after auditing why the drift occurred. Never silently reconcile contradictions.

---

# 16. CURRENT RECOVERY MARKERS

```text
RECOVERY_HANDOFF_VERSION=2026-10-17-H7C-A-AUTOMATED-GREEN
H1_H6=CERTIFIED/CLOSED AS RECORDED
H7A_FULLY_CLOSED=YES
H7B_FULLY_CLOSED=YES
H7C_A_RED_PROVEN=YES
H7C_A_AUTOMATED_GREEN=YES
H7C_A_BROWSER=PENDING
H7C_A_FULLY_CLOSED=NO
H7C_B_STARTED=NO
H7C_C_STARTED=NO
H7C_D_STARTED=NO
NEXT_HUMAN_FIRST=H7C-A REAL-BROWSER CERTIFICATION
PR_MERGED=NO
STOP_SAFE=YES
```
