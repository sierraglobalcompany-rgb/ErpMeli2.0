# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=H7B FULLY CLOSED / H7C-A AUTOMATED GREEN / REAL-BROWSER CERTIFICATION PENDING / H7C-A NOT CLOSED / H7C-B-C-D NOT STARTED / STOP-SAFE**

Esta es la autoridad operativa corta. Para recuperación completa, metodología, leyes y contexto forense, leer inmediatamente:

```text
docs/ERP_MELI_2_0_RECOVERY_HANDOFF_H7C_A_AUTOMATED_GREEN_BROWSER_PENDING_2026-10-17.md
RECOVERY_HANDOFF_COMMIT=68a98e9728335053ade3e96ec2718255e4a08223
```

## Repository authority

```text
DATE=2026-10-17
REPO=sierraglobalcompany-rgb/ErpMeli2.0
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
BASE_BRANCH=ui/operator-shell-v1-20261014
BASE_SHA=c6f8af7f446cb327a905ae2f9bd9ee424c2d421f
REMOTE_WRITES=OFF
REAL_MELI_HTTP=0
HOSTINGER_DEPLOYED=NO
REAL_MELI_WRITES=NO
MERGE_AUTHORIZED=NO
STOP_SAFE=YES
```

`main` is not current Human-First product authority.

## Mandatory laws

```text
KISS=MANDATORY
ANTI_NOISE=MANDATORY
ANTI_PATCH=MANDATORY
HUMAN_FIRST=MANDATORY
TRUTH_BEFORE_BEAUTY=MANDATORY
UNKNOWN_NEVER_BECOMES_ZERO=MANDATORY
EVIDENCE_BEFORE_GREEN=MANDATORY
NO_MERGE_WITHOUT_EXPLICIT_USER_APPROVAL=MANDATORY
```

## Working method

```text
READ-ONLY PREFLIGHT
-> one bounded owner/root defect
-> approved design when required
-> real RED test first
-> commit RED
-> prove expected RED in CI
-> minimal root-owner GREEN
-> commit GREEN
-> fresh full CI
-> real-browser certification when UI/responsive/keyboard changed
-> independent evidence audit
-> durable checkpoint + PR sync
-> only then next microblock
```

ChatGPT is primary driver. Antigravity is certification-only when local real-browser control is unavailable; no fixes/commits/push/merge. Codex is for material local-machine barriers, not routine implementation.

## Certified Human-First history

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

Do not reopen certified blocks without new evidence/regression/new requirement.

## H7B closure anchor

```text
H7B_RED=73a8a92f6d31f1f6619dc6d4f2e3b2214032c3cc
H7B_GREEN=e6711bd9ccbb5a6298c14885f4f2341040a94343
H7B_GREEN_CI=39043131947
H7B_PHP_8_3=823 tests / 6999 assertions / 0 failures
H7B_BROWSER_CERTIFIED_HEAD=16d50961cda86ef7f886affe8676d470422607cd
H7B_EVIDENCE_ZIP_SHA256=6aa6144e6f34432e1e5e86e2aeffbd9c99b387afbc39a0f5ed9e69576a692da7
H7B_MANIFEST=18/18 exact SHA-256
H7B_BROWSER_INDEPENDENT_AUDIT=PASS
H7B_FULLY_CLOSED=YES
```

## H7C authority

```text
H7C_A=Companies: archive + permanent delete
H7C_B=MELI accounts: disconnect + hard delete
H7C_C=System settings: clear debug
H7C_D=Users: remove System Admin privilege + remove company membership
```

H7C is destructive-action UX / T3 Security-Authorization, not generic progressive disclosure. One owner per microblock.

# H7C-A — Companies destructive UX

Truth owner remains:

```text
GET/POST /system/companies
POST /system/companies/{id}/archive
POST /system/companies/{id}/reactivate
POST /system/companies/{id}/delete
OWNER=CompanyManagementController
```

Backend authorization, CSRF and domain rules remain authoritative.

## RED — proven

```text
H7C_A_RED=1c8bae3374c7a36af075f3b5bb59c64c95cef763
H7C_A_RED_CI=39049451668
RED_WORKFLOW=EXPECTED FAILURE
RED_PHPSTAN=PASS
RED_PHP_8_3=824 tests / 7002 assertions / exactly 1 failure
RED_ONLY_FAILURE=H7CCompanyDestructiveActionUxTest
PROVEN_DEFECT=active company archive was direct one-click POST
PRODUCTION_CHANGED_AT_RED=NO
```

## GREEN — automated PASS

```text
H7C_A_GREEN=d8cfbe04a924304cb1234153fe873a34bef4bab4
H7C_A_GREEN_CI=39049794704
GREEN_WORKFLOW=SUCCESS
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
PHPSTAN=PASS
PHP_8_3_RESULT=824 tests / 7007 assertions / 0 failures
REAL_MELI_HTTP=0
```

Canonical RED->GREEN production diff:

```text
COMMITS=1
FILES=1
FILE=app/Core/Http/views/companies.php
ADDITIONS=27
DELETIONS=12
CONTROLLERS_CHANGED=NO
ROUTES_CHANGED=NO
SCHEMA_CHANGED=NO
CSS_CHANGED=NO
JS_CHANGED=NO
PERMISSIONS_CHANGED=NO
WORK_CHANGED=NO
FINANCIAL_CHANGED=NO
429_ARCHITECTURE_CHANGED=NO
```

Implemented:

```text
ACTIVE COMPANY:
  archive behind native <details>
  summary=Archivar empresa
  factual impact copy
  /archive + CSRF preserved

ARCHIVED COMPANY:
  Reactivar remains direct/restorative

DELETABLE ARCHIVED COMPANY:
  delete still depends on backend deletable fact
  native <details>
  factual delete conditions preserved
  required checkbox confirm_permanent_delete
  /delete + backend authority preserved

NO generic modal
NO controller/schema/permission/JS architecture change
```

## Remaining gate — REAL BROWSER

H7C-A is NOT fully closed until independent browser evidence passes.

Required certification, side-effect-free:

```text
SYSTEM_ADMIN_POSITIVE=PASS required
ORDINARY_COMPANY_ADMIN_NEGATIVE=PASS required
DESKTOP_1440x900=PASS required
MOBILE_390x844=PASS required
H7A_SHARED_SHELL_REGRESSION=PASS required
MOBILE_NAV=exactly 5 slots
ARCHIVE_DETAILS_DEFAULT_CLOSED=YES
ARCHIVE_KEYBOARD_ENTER_SPACE=PASS
ARCHIVE_OWNER=/system/companies/{id}/archive
REACTIVATE_DIRECT_RESTORATIVE=PASS
DELETE_DETAILS_DEFAULT_CLOSED=YES when deletable=true
DELETE_REQUIRED_ACK=PASS
DELETE_OWNER=/system/companies/{id}/delete
CSRF_PRESENT=YES
NO_HORIZONTAL_OVERFLOW=YES
NO_BOTTOM_NAV_OBSTRUCTION=YES
CONSOLE_APP_ERRORS=0
UNCAUGHT_JS=0
APPLICATION_5XX=0
REAL_MELI_NETWORK_CALLS=0
BUSINESS_SIDE_EFFECTS=0
DB_BEFORE_AFTER=IDENTICAL
WORKING_TREE_BEFORE_AFTER=CLEAN
CERTIFIER_CODE_TEST_DOC_CHANGES=0
PUSH=0
MERGE=0
```

Do NOT submit archive/delete/reactivate during certification.

## Exact resume point

```text
NEXT=H7C-A REAL-BROWSER CERTIFICATION ONLY
TARGET_GREEN=d8cfbe04a924304cb1234153fe873a34bef4bab4
AUTOMATED_GREEN_CI=39049794704
H7C_A_AUTOMATED_GREEN=YES
H7C_A_BROWSER_CERTIFIED=NO
H7C_A_FULLY_CLOSED=NO
H7C_B_STARTED=NO
H7C_C_STARTED=NO
H7C_D_STARTED=NO
DO_NOT_START_H7C_B_BEFORE_H7C_A_BROWSER_INDEPENDENT_AUDIT
PR_MERGED=NO
STOP_SAFE=YES
```

## ERP1 / 429 frozen truth

ERP1 had Billing/Financial/Work instability including 429/FIFO/timing symptoms and architecture drift. Universal root cause remains:

```text
UNIVERSAL_ERP1_429_ROOT_CAUSE=NOT_PROVEN
```

No Financial/Work/429 retry, pacing, queue, batching or transport redesign without fresh evidence.

## Frozen / prohibited

No merge, Hostinger, cron, real MELI mutation, broad navigation redesign, generic modal framework, UNKNOWN->0, patch-chain CSS, speculative ERP1 architecture, or reopening closed Human-First blocks without evidence.
