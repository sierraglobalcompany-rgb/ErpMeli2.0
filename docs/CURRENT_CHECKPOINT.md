# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=H7B FULLY CLOSED / H7C-A FULLY CLOSED (INDEPENDENT BROWSER AUDIT PASS) / H7C-B READ-ONLY PREFLIGHT NEXT / H7C-B-C-D NOT STARTED / STOP-SAFE**

Esta es la autoridad operativa corta.

Para recuperación rápida, leer primero:

```text
docs/PROJECT_CONTINUITY_CURRENT.md
```

Para recuperación detallada, metodología, leyes y contexto forense, leer después:

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
FUNCTIONAL_HEAD=22384c2c616da89d34e37543cd42e8ae13c54e06
FUNCTIONAL_HEAD_CI=39055307246 SUCCESS
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

ChatGPT is primary driver. Antigravity is certification-only when local real-browser control is unavailable; no product fixes, commits, push, merge commit or PR merge. A same-branch local fast-forward sync is allowed only when required by the certification prompt. Codex is for material local-machine barriers, not routine implementation.

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

Backend authorization, CSRF, domain rules and permanent-delete acknowledgement are authoritative.

## Initial RED — proven

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

## Initial GREEN — automated PASS

```text
H7C_A_INITIAL_GREEN=d8cfbe04a924304cb1234153fe873a34bef4bab4
H7C_A_INITIAL_GREEN_CI=39049794704
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
PHPSTAN=PASS
PHP_8_3_RESULT=824 tests / 7007 assertions / 0 failures
```

Initial RED->GREEN production diff:

```text
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

## Acknowledgement hardening — RED proven

Forensic review before browser certification found that the HTML checkbox was `required` but the backend accepted a direct POST without `confirm_permanent_delete`. Browser-native validity alone was therefore insufficient.

```text
H7C_A_ACK_RED=ff0255d4826e31948852c1fee5dff40131b705e6
H7C_A_ACK_RED_CI=39054722005
ACK_RED_WORKFLOW=EXPECTED FAILURE
ACK_RED_PHP_8_3=825 tests / 7011 assertions / exactly 1 failure
ACK_RED_ONLY_FAILURE=H7CCompanyPermanentDeleteAcknowledgementTest
ACK_PROVEN_DEFECT=direct POST could bypass permanent-delete acknowledgement
PRODUCTION_CHANGED_AT_ACK_RED=NO
```

## Acknowledgement hardening — GREEN PASS

```text
H7C_A_GREEN=22384c2c616da89d34e37543cd42e8ae13c54e06
H7C_A_GREEN_CI=39055307246
GREEN_WORKFLOW=SUCCESS
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
PHPSTAN=PASS
PHP_8_3_RESULT=825 tests / 7020 assertions / 0 failures
REAL_MELI_HTTP=0
```

ACK RED->GREEN diff is bounded to:

```text
PRODUCTION_FILE=app/Core/Http/CompanyManagementController.php
TEST_COMPAT_FILE=tests/Integration/CompanyManagementTest.php
NEW_RED_TEST=tests/Integration/H7CCompanyPermanentDeleteAcknowledgementTest.php
ROUTES_CHANGED=NO
SCHEMA_CHANGED=NO
CSS_CHANGED=NO
JS_CHANGED=NO
PERMISSIONS_CHANGED=NO
WORK_CHANGED=NO
FINANCIAL_CHANGED=NO
429_ARCHITECTURE_CHANGED=NO
```

Implemented contract:

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
  backend also requires confirm_permanent_delete=1
  missing/invalid acknowledgement returns 422 without deletion
  /delete + backend authority preserved
  user remains preserved

NO generic modal
NO schema/permission/JS architecture change
```

## H7C-A FINAL — independent real-browser evidence PASS

Antigravity V3 certified actual local Chrome on the **disposable TEST runtime**; ChatGPT independently verified the uploaded ZIP digest, exact 29/29 manifest hashes, report equality, screenshots, JSON gates, browser network log, DB snapshots and live GitHub ancestry/CI/PR.

```text
H7C_A_BROWSER_RESULT=PASS
H7C_A_INDEPENDENT_AUDIT=PASS
H7C_A_FULLY_CLOSED=YES
CERTIFIED_HEAD=95e15d84afe11fba4752d7bbdb6b32b456b58858
FUNCTIONAL_GREEN=22384c2c616da89d34e37543cd42e8ae13c54e06
FUNCTIONAL_GREEN_CI=39055307246 SUCCESS
CERTIFIED_HEAD_CI=39055734137 SUCCESS
REAL_LOCAL_CHROME=YES
BROWSER=Google Chrome 155.0.8059.84
DESKTOP=1440x900 PASS
MOBILE=390x844 PASS
SYSTEM_ADMIN_HTTP=200
COMPANY_ADMIN_NON_SYSTEM_HTTP=403
ARCHIVE_DELETE_DISCLOSURES=PASS
KEYBOARD_ENTER_SPACE=PASS
DELETE_CHECKBOX_NATIVE_VALIDITY=PASS
H7A_SHARED_SHELL=PASS
MOBILE_NAV_SLOTS=5
HORIZONTAL_OVERFLOW=NO
BOTTOM_NAV_OBSTRUCTION=NO
APPLICATION_5XX=0
APPLICATION_UNCAUGHT_JS_ERRORS=0
REAL_MELI_BROWSER_NETWORK_CALLS=0
DESTRUCTIVE_ROUTE_POSTS=0
DB_BEFORE_AFTER=IDENTICAL
BUSINESS_SIDE_EFFECTS=0
CERTIFIER_GIT_BEFORE_AFTER=CLEAN
CERTIFIER_CODE_TEST_DOC_EDITS=0
CERTIFIER_PUSH=NO
CERTIFIER_MERGE=NO
PR_19=OPEN / DRAFT / NOT MERGED
EVIDENCE_ZIP_SHA256=177f76c87774f20962219743424341384349f0e30a0d086d2cb061eac87d1e88
EVIDENCE_MANIFEST=29/29 EXACT
ZIP=ERP_MELI_2_0_H7C_A_BROWSER_ANTIGRAVITY_EVIDENCE_2026-10-17.zip
REPORT=ERP_MELI_2_0_H7C_A_BROWSER_ANTIGRAVITY_REPORT_2026-10-17.md
SIDECAR=ERP_MELI_2_0_H7C_A_BROWSER_ANTIGRAVITY_EVIDENCE_2026-10-17.zip.sha256
```

Minor non-blocking observations: the Chrome favicon GET returned 404, and the Apps launcher focus-inside snapshot was taken before waiting for its animation-frame focus; Escape and focus-return behavior are evidenced. They do not justify reopening H7C-A without fresh proof.

## Exact resume point

```text
NEXT=H7C-B READ-ONLY PREFLIGHT
H7C_A_BROWSER_CERTIFIED=YES
H7C_A_INDEPENDENT_AUDIT=PASS
H7C_A_FULLY_CLOSED=YES
H7C_B_STARTED=NO
H7C_C_STARTED=NO
H7C_D_STARTED=NO
H7C_B_SCOPE=MELI accounts disconnect + hard delete destructive UX only
H7C_B_PREFLIGHT=OWNER / ROUTES / FACTS / CURRENT TESTS / HUMAN-FIRST CONTRACT
PR_MERGED=NO
MERGE_AUTHORIZED=NO
STOP_SAFE=YES
```

No H7C-B product change until bounded owner, precise observed UX defect and RED->GREEN plan are verified. No broader Work/Financial/429 changes or remote mutations.

## ERP1 / 429 frozen truth

ERP1 had Billing/Financial/Work instability including 429/FIFO/timing symptoms and architecture drift. Universal root cause remains:

```text
UNIVERSAL_ERP1_429_ROOT_CAUSE=NOT_PROVEN
```

No Financial/Work/429 retry, pacing, queue, batching or transport redesign without fresh evidence.

## Frozen / prohibited

No PR merge or merge commit without explicit approval, Hostinger, cron, real MELI mutation, broad navigation redesign, generic modal framework, UNKNOWN->0, patch-chain CSS, speculative ERP1 architecture, or reopening closed Human-First blocks without evidence.
