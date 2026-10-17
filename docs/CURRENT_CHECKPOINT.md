# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=H7B FULLY CLOSED / H7C-A COMPANIES DESTRUCTIVE UX AUTOMATED GREEN / REAL-BROWSER CERTIFICATION PENDING / NOT CLOSED / H7C-B/C/D NOT STARTED / STOP-SAFE**

Autoridad operativa de recuperación. No depender del chat.

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

## Mandatory laws

```text
KISS=MANDATORY
ANTI_NOISE=MANDATORY
ANTI_PATCH=MANDATORY
HUMAN_FIRST=MANDATORY
UNKNOWN_NEVER_BECOMES_ZERO=MANDATORY
EVIDENCE_BEFORE_GREEN=MANDATORY
```

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

# H7B — Users progressive disclosure — FULLY CLOSED

```text
H7B_RED=73a8a92f6d31f1f6619dc6d4f2e3b2214032c3cc
H7B_RED_CI=39042876994
H7B_GREEN=e6711bd9ccbb5a6298c14885f4f2341040a94343
H7B_GREEN_CI=39043131947
H7B_BROWSER_CERTIFIED_HEAD=16d50961cda86ef7f886affe8676d470422607cd
H7B_EVIDENCE_ZIP_SHA256=6aa6144e6f34432e1e5e86e2aeffbd9c99b387afbc39a0f5ed9e69576a692da7
H7B_MANIFEST=18/18 exact SHA-256
H7B_BROWSER_INDEPENDENT_AUDIT=PASS
H7B_FULLY_CLOSED=YES
```

Browser evidence certified desktop/mobile layout, keyboard disclosure, form ownership, shared shell, 5-slot mobile nav, zero horizontal overflow, zero relevant console/JS/5xx/MELI calls, business-table hashes identical before/after, clean git state and zero certifier code/test/doc changes.

# H7C — Destructive actions

H7C authority is UX-018 / T3 Security-Authorization. It is NOT generic progressive disclosure.

```text
H7C_A=Companies: archive + permanent delete
H7C_B=MELI accounts: disconnect + hard delete
H7C_C=System settings: clear debug
H7C_D=Users: remove System Admin privilege + remove company membership
```

Backend authorization, CSRF and domain guards remain authoritative and are not to be redesigned merely for presentation.

# H7C-A — Companies destructive UX

## Truth owner

```text
GET/POST /system/companies
POST /system/companies/{id}/archive
POST /system/companies/{id}/reactivate
POST /system/companies/{id}/delete
CompanyManagementController
```

## TDD RED — CONFIRMED

```text
H7C_A_RED=1c8bae3374c7a36af075f3b5bb59c64c95cef763
H7C_A_RED_CI=39049451668
RED_WORKFLOW=FAILURE AS EXPECTED
RED_PHPSTAN=PASS
RED_PHP_8_3=824 tests / 7002 assertions / exactly 1 failure
RED_ONLY_FAILURE=Tests\Integration\H7CCompanyDestructiveActionUxTest::testCompanyDestructiveActionsRequireDeliberateHumanConfirmation
```

Observed RED defect:

```text
ACTIVE_COMPANY_ARCHIVE=direct one-click POST
EXPECTED=deliberate native second step with factual consequence copy
PRODUCTION_CHANGED_AT_RED=NO
```

Existing Slim error traces in unrelated tests were expected test-intent output and did not count as PHPUnit failures.

## Minimal GREEN — AUTOMATED PASS

```text
H7C_A_GREEN=d8cfbe04a924304cb1234153fe873a34bef4bab4
H7C_A_GREEN_CI=39049794704
GREEN_WORKFLOW=SUCCESS
GREEN_PHP_8_3=PASS
GREEN_PHP_8_4=PASS
GREEN_PHP_8_5=PASS
GREEN_PHPSTAN=PASS
GREEN_PHP_8_3_PHPUNIT=824 tests / 7007 assertions / 0 failures
REAL_MELI_HTTP=0
```

Canonical RED→GREEN production diff:

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
DEPENDENCIES_CHANGED=NO
PERMISSIONS_CHANGED=NO
WORK_CHANGED=NO
FINANCIAL_CHANGED=NO
429_ARCHITECTURE_CHANGED=NO
```

Implemented presentation contract:

```text
ACTIVE_COMPANY:
  archive moved behind native <details>
  summary=Archivar empresa
  copy explains operation/selection stops, history remains, reactivation remains possible
  existing /archive route and CSRF preserved

ARCHIVED_COMPANY:
  reactivate remains direct and restorative
  existing /reactivate route preserved

DELETABLE_ARCHIVED_COMPANY:
  permanent delete remains conditional on existing backend deletable fact
  delete moved behind native <details>
  existing factual deletion conditions preserved
  explicit required checkbox confirm_permanent_delete added before browser submission
  existing /delete route and backend deletion authority preserved

NO generic modal architecture
NO controller change
NO permission-model change
NO schema change
NO JS/framework change
```

## Remaining H7C-A gate

Automated GREEN is not enough to close H7C-A.

Required real-browser certification:

```text
SYSTEM_ADMIN_POSITIVE=required
ORDINARY_COMPANY_ADMIN_NEGATIVE=required
DESKTOP_1440x900=required
MOBILE_390x844=required
H7A_SHARED_SHELL_REGRESSION=required
MOBILE_NAV_5_SLOTS=required
ARCHIVE_DETAILS_DEFAULT_CLOSED=required
ARCHIVE_KEYBOARD_ENTER_SPACE=required
ARCHIVE_ROUTE_OWNER=/system/companies/{id}/archive
REACTIVATE_DIRECT_RESTORATIVE=required
DELETE_DETAILS_DEFAULT_CLOSED=required when deletable=true
DELETE_REQUIRED_ACK=required
DELETE_ROUTE_OWNER=/system/companies/{id}/delete
CSRF_PRESENT=required
NO_HORIZONTAL_OVERFLOW=required
NO_BOTTOM_NAV_OBSTRUCTION=required
CONSOLE_APP_ERRORS=0
UNCAUGHT_JS=0
APPLICATION_5XX=0
REAL_MELI_NETWORK_CALLS=0
BUSINESS_SIDE_EFFECTS=0
DB_BEFORE_AFTER=IDENTICAL
CERTIFIER_CODE_CHANGES=0
CERTIFIER_TEST_CHANGES=0
CERTIFIER_DOC_CHANGES=0
PUSH=0
MERGE=0
```

Do NOT submit archive/delete/reactivate during browser certification. Verify presentation, DOM ownership, keyboard behavior, authorization visibility and zero side effects without mutating business state.

## Current status

```text
H7C_A_RED_CONFIRMED=YES
H7C_A_AUTOMATED_GREEN=YES
H7C_A_BROWSER_CERTIFIED=NO
H7C_A_FULLY_CLOSED=NO
H7C_B_STARTED=NO
H7C_C_STARTED=NO
H7C_D_STARTED=NO
PR_MERGED=NO
STOP_SAFE=YES
```

## Exact resume point

```text
NEXT=H7C-A REAL-BROWSER CERTIFICATION ON GREEN d8cfbe04a924304cb1234153fe873a34bef4bab4
AUTOMATED_GREEN_CI=39049794704
DO_NOT_START_H7C_B_BEFORE_H7C_A_BROWSER_AUDIT
```

## Frozen / prohibited

No merge, Hostinger, cron, real MELI mutation, generic modal framework, broad navigation redesign, Financial/Work/429 redesign, retry/pacing/queue/batching transport changes, UNKNOWN→0, patch-chain CSS, speculative ERP1 429 architecture.
