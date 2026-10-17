# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=H7C-A FULLY CLOSED / H7C-B1 FULLY CLOSED (INDEPENDENT BROWSER EVIDENCE PASS) / H7C-B2 RED NEXT / H7C-C-D NOT STARTED / STOP-SAFE**

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

## H7C-B1 — MELI account disconnect (FULLY CLOSED)

Owner: `app/Modules/Settings/views/meli-accounts.php`; the controller, routes, CSRF, company scoping, work guard and account token/status behavior are unchanged.

```text
H7C_B1_RED=d6b96bcbc5b7189b231ae84c8e03ed881858d912
H7C_B1_RED_CI=39070718318 EXPECTED FAILURE
RED_PHP_8_3=826 tests / 7022 assertions / exactly 1 failure
RED_ONLY_FAILURE=MeliAccountLifecycleUiTest::testDisconnectIsBehindNativeHumanDisclosureWithFactualImpact
H7C_B1_GREEN=ab640fe222bc33fc9083fb25596b41133f37bab8
H7C_B1_GREEN_CI=39070978051 SUCCESS
GREEN_PHP_8_3=826 tests / 7027 assertions / 0 failures
GREEN_PHP_8_4=PASS
GREEN_PHP_8_5=PASS
GREEN_PHPSTAN=PASS
RED_TO_GREEN_ONLY=app/Modules/Settings/views/meli-accounts.php (+8/-4)
CONTROLLER_CHANGED=NO
ROUTES_CHANGED=NO
CSS_CHANGED=NO
JS_CHANGED=NO
SCHEMA_CHANGED=NO
WORK_CHANGED=NO
FINANCIAL_CHANGED=NO
```

The direct `Desconectar` one-click POST is now in a native `<details>` (closed by default). Factual copy explains local credential deletion, ERP disablement, history preservation and possible later reauthorization. POST and CSRF retained within the disclosure.

### Independent real-browser audit — PASS

Antigravity Chrome 155 on isolated disposable TEST runtime certified **HEAD** `ce09d7b21bb6d619884307959b13c58cd9faf842`. ChatGPT independently validated all attached artifact hashes and ZIP entries; reviewed desktop/mobile screenshots, HTML/DOM, keyboard, authorization, shell, console/network, before/after database, Git trace and live GitHub CI.

```text
H7C_B1_BROWSER_RESULT=PASS
H7C_B1_INDEPENDENT_AUDIT=PASS
H7C_B1_FULLY_CLOSED=YES
CERTIFIED_HEAD=ce09d7b21bb6d619884307959b13c58cd9faf842
CERTIFIED_HEAD_CI=39071315439 SUCCESS
ZIP_SHA256=ce44f76272dd5743abc23f60ef6ea95a151e9a34e8944a32180172aebca71350
MANIFEST=26/26 EXACT
ZIP_ENTRIES=27
REPORT_MATCHES_ZIP=YES
REAL_LOCAL_CHROME=YES
DESKTOP_1440x900=PASS
MOBILE_390x844=PASS
COMPANY_ADMIN_HTTP=200
NON_ADMIN_HTTP=403
DETAILS_DEFAULT_CLOSED=YES
KEYBOARD_ENTER_SPACE=PASS
POST_AND_CSRF_INSIDE_DISCLOSURE=PASS
DIRECT_DISCONNECT_OUTSIDE_DISCLOSURE=NO
REAUTHORIZE_UNCHANGED=YES
H7A_SHELL=PASS
MOBILE_NAV_SLOTS=5
HORIZONTAL_OVERFLOW=NO
BOTTOM_NAV_OBSTRUCTION=NO
APP_JS_ERRORS=0
APP_5XX=0
DESTRUCTIVE_POSTS=0
REAL_MELI_BROWSER_CALLS=0
DB_BEFORE_AFTER=IDENTICAL
CERTIFIER_WORKTREE_AFTER=CLEAN
CERTIFIER_CODE_TEST_DOC_WRITES=0
CERTIFIER_COMMITS=0
CERTIFIER_PUSH=0
CERTIFIER_MERGE=0
PR_19=OPEN / DRAFT / NOT MERGED
```

Evidence originals (external to repository):
- `ERP_MELI_2_0_H7C_B1_ANTIGRAVITY_BROWSER_REPORT_2026-10-17.md`
- `ERP_MELI_2_0_H7C_B1_ANTIGRAVITY_BROWSER_EVIDENCE_2026-10-17.zip`
- `ERP_MELI_2_0_H7C_B1_ANTIGRAVITY_BROWSER_EVIDENCE_2026-10-17.zip.sha256`

Incidental favicon 404 and deliberate non-admin HTTP 403 are classified, not evidence of application malfunction. Browser-session zero network calls does not imply machine-wide capture.

## Exact resume point — H7C-B2

```text
NEXT=H7C-B2 HARD DELETE HUMAN-FIRST RED TEST
H7C_A_FULLY_CLOSED=YES
H7C_B1_FULLY_CLOSED=YES
H7C_B2_STARTED=NO
H7C_B2_OWNER=app/Modules/Settings/MeliAccountController.php + views/meli-accounts.php
H7C_B2_SCOPE=disabled pristine MELI account hard-delete UX + server-side acknowledgement
H7C_C_STARTED=NO
H7C_D_STARTED=NO
PR_MERGED=NO
MERGE_AUTHORIZED=NO
STOP_SAFE=YES
```

Do not change account history-sufficiency/deletability, OAuth, Work, Financial, Billing, FIFO, retry/429, remote MELI, Hostinger or cron. RED must demonstrate missing human acknowledgement on a CSRF-valid direct POST without deleting test data; GREEN minimal and scoped. Preserve 403/404/409/419 and existing successful deletion with proper acknowledgement. Validate CI before browser certification.

## ERP1 / 429 frozen truth

ERP1 had Billing/Financial/Work instability including 429/FIFO/timing symptoms and architecture drift. Universal root cause remains:

```text
UNIVERSAL_ERP1_429_ROOT_CAUSE=NOT_PROVEN
```

No Financial/Work/429 retry, pacing, queue, batching or transport redesign without fresh evidence.

## Frozen / prohibited

No PR merge or merge commit without explicit approval, Hostinger, cron, real MELI mutation, broad navigation redesign, generic modal framework, UNKNOWN->0, patch-chain CSS, speculative ERP1 architecture, or reopening closed Human-First blocks without evidence.
