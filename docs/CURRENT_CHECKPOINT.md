# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=H7B USERS PROGRESSIVE DISCLOSURE FULLY CLOSED / H7C-A COMPANIES DESTRUCTIVE UX READY FOR TDD / H7C-B/C/D NOT STARTED / STOP-SAFE**

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

## TDD authority

```text
H7B_RED=73a8a92f6d31f1f6619dc6d4f2e3b2214032c3cc
H7B_RED_CI=39042876994
H7B_GREEN=e6711bd9ccbb5a6298c14885f4f2341040a94343
H7B_GREEN_CI=39043131947
GREEN_PHP_8_3=PASS
GREEN_PHP_8_4=PASS
GREEN_PHP_8_5=PASS
GREEN_PHPSTAN=PASS
GREEN_PHPUNIT_PHP_8_3=823 tests / 6999 assertions / 0 failures
```

Canonical RED→GREEN production diff:

```text
COMMITS=1
FILES=1
FILE=app/Core/Http/views/users.php
ADDITIONS=31
DELETIONS=24
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

## Real-browser certification — independently audited

Certified HEAD:

```text
16d50961cda86ef7f886affe8676d470422607cd
```

External evidence artifacts:

```text
ERP_MELI_2_0_H7B_BROWSER_ANTIGRAVITY_REPORT_2026-10-17.md
ERP_MELI_2_0_H7B_BROWSER_ANTIGRAVITY_EVIDENCE_2026-10-17.zip
ZIP_SHA256=6aa6144e6f34432e1e5e86e2aeffbd9c99b387afbc39a0f5ed9e69576a692da7
MANIFEST=18/18 exact SHA-256
REPORT_EXTERNAL_EQUALS_INTERNAL=YES
```

Independent audit result:

```text
DESKTOP_1440x900=PASS
MOBILE_390x844=PASS
HTTP_200=PASS
H7A_SHARED_SHELL=PASS
CREATE_USER_FORM_VISIBLE_OUTSIDE_DETAILS=PASS
USER_A_FACTS=active + System Admin + 1 membership PASS
USER_B_FACTS=disabled + non-System-Admin + 0 memberships PASS
DETAILS_PER_USER=3
DETAILS_DEFAULT_CLOSED=PASS
SUMMARY_LABELS_EXACT=PASS
IDENTITY_OWNER=/update only PASS
SECURITY_OWNER=/status + /system-admin + /password PASS
MEMBERSHIP_OWNER=/membership + /remove-membership as factual fixture permits PASS
KEYBOARD_FOCUS_VISIBLE=PASS
ENTER_TOGGLE=PASS
SPACE_TOGGLE=PASS
UNRELATED_TOGGLE=NO
MOBILE_NAV=5 slots PASS
HORIZONTAL_OVERFLOW=NO
CONTROL_OVERLAP=0
BOTTOM_NAV_OBSTRUCTION=NO
CONSOLE_APP_ERRORS=0
UNCAUGHT_JS=0
APPLICATION_5XX=0
REAL_MELI_NETWORK_CALLS=0
BUSINESS_SIDE_EFFECTS=0
DB_BUSINESS_TABLE_HASHES_BEFORE_AFTER=IDENTICAL
WORKING_TREE_BEFORE=CLEAN
WORKING_TREE_AFTER=CLEAN
CERTIFIER_CODE_CHANGES=NO
CERTIFIER_TEST_CHANGES=NO
CERTIFIER_DOC_CHANGES=NO
CERTIFIER_COMMITS=NO
PUSH=NO
MERGE=NO
```

The raw snapshot files differ only in encoding/BOM bytes; normalized content and every business-table row/hash are identical. This is evidence-file encoding noise, not a DB mutation.

Therefore:

```text
H7B_BROWSER_PASS=YES
H7B_BROWSER_INDEPENDENTLY_AUDITED=YES
H7B_FULLY_CLOSED=YES
```

# H7C — Destructive actions

H7C authority is UX-018 / T3 Security-Authorization. It is NOT generic company progressive disclosure.

Current sequence:

```text
H7C_A=Companies: archive + permanent delete
H7C_B=MELI accounts: disconnect + hard delete
H7C_C=System settings: clear debug
H7C_D=Users: remove System Admin privilege + remove company membership
```

Backend authorization/CSRF/domain guards already exist and must not be redesigned merely for presentation.

## H7C-A Companies — approved bounded design

Truth owner remains:

```text
GET/POST /system/companies
POST /system/companies/{id}/archive
POST /system/companies/{id}/reactivate
POST /system/companies/{id}/delete
CompanyManagementController
```

Design contract:

```text
ACTIVE_COMPANY:
  archive is not a one-click destructive POST
  use native deliberate disclosure/second step
  explain factual impact: normal operation/selection stops, history is preserved, reactivation remains possible
  keep existing /archive route and CSRF

ARCHIVED_COMPANY:
  reactivate remains direct because it is restorative, not destructive

DELETABLE_ARCHIVED_COMPANY:
  delete remains rendered only when existing backend deletable fact is true
  place permanent delete behind deliberate disclosure
  preserve factual copy: archived + pristine/no prior use + sole admin membership; user account is not deleted
  require explicit native acknowledgement checkbox before browser submits
  keep existing /delete route and backend authority unchanged

NO generic modal architecture
NO controller change unless a RED proves presentation cannot be implemented safely without it
NO permission-model change
NO schema/JS/framework change
```

T3 verification requirements:

```text
SYSTEM_ADMIN_POSITIVE=required
ORDINARY_COMPANY_ADMIN_NEGATIVE=required
CSRF_GUARDS=preserved
DOMAIN_ARCHIVE_BLOCKERS=preserved
DELETE_PRISTINE_RULES=preserved
DESKTOP_MOBILE_BROWSER=required after automated GREEN
KEYBOARD_DISCLOSURE=required
ZERO_BUSINESS_SIDE_EFFECTS during browser certification
```

## Exact resume point

```text
NEXT=H7C-A TDD RED — Companies destructive-action presentation contract
H7B_FULLY_CLOSED=YES
H7C_A_PRODUCTION_STARTED=NO
H7C_B_STARTED=NO
H7C_C_STARTED=NO
H7C_D_STARTED=NO
PR_MERGED=NO
STOP_SAFE=YES
```

## Frozen / prohibited

No merge, Hostinger, cron, real MELI mutation, generic modal framework, broad navigation redesign, Financial/Work/429 redesign, retry/pacing/queue/batching transport changes, UNKNOWN→0, patch-chain CSS, speculative ERP1 429 architecture.
