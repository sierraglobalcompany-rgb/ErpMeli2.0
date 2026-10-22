# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=H7C FULLY CLOSED / H8-A MELI CREDENTIAL CLEAR AUTOMATED GREEN / BROWSER CERT PENDING / H8-B NOT STARTED / PR #19 DRAFT UNMERGED / STOP-SAFE**

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
DATE=2026-10-21
REPO=sierraglobalcompany-rgb/ErpMeli2.0
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
BASE_BRANCH=ui/operator-shell-v1-20261014
BASE_SHA=c6f8af7f446cb327a905ae2f9bd9ee424c2d421f
FUNCTIONAL_HEAD=44e50342a5dcb606e90c07b4ad199102a434344d
FUNCTIONAL_HEAD_CI=39666739964 SUCCESS
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

## H7C-B2 — MELI hard delete, automated GREEN; real browser PENDING

Precise owner `app/Modules/Settings/MeliAccountController.php` and `app/Modules/Settings/views/meli-accounts.php`. Root human-confirmation defect was proven: a company-admin CSRF-valid POST deleting an eligible disabled pristine account without `confirm_permanent_delete` returned 303 instead of 422. Existing company/CSRF/status/history authorization gates remain authoritative and unchanged.

RED:
```text
H7C_B2_RED=3595880b6f4e6cdaeef6cc0ebff9f85c0e7cb723
H7C_B2_RED_CI=39072807611 EXPECTED FAILURE
RED_PHP_8_3=827 tests / 7028 assertions / 1 failure
RED_ONLY_FAILURE=MeliAccountLifecycleTest::testHardDeleteRequiresExplicitAcknowledgementEvenWithValidCsrf
RED_OBSERVED_HTTP=303
RED_EXPECTED_HTTP=422
RED_PRODUCTION_CHANGED=NO
```

GREEN:
```text
H7C_B2_GREEN=d00eb26f37488d8552d2b1598d5b27dcb76967dd
H7C_B2_GREEN_CI=39073100289 SUCCESS
GREEN_PHP_8_3=828 tests / 7037 assertions / 0 failures
GREEN_PHP_8_4=828 tests / 7037 assertions / 0 failures
GREEN_PHP_8_5=828 tests / 7037 assertions / 0 failures
PHPSTAN=PASS ALL 3
GREEN_FILES=app/Modules/Settings/MeliAccountController.php;app/Modules/Settings/views/meli-accounts.php;tests/Integration/MeliAccountLifecycleTest.php
ROUTES_CHANGED=NO
SCHEMA_CHANGED=NO
CSS_CHANGED=NO
JS_CHANGED=NO
WORK_CHANGED=NO
FINANCIAL_CHANGED=NO
OAUTH_CHANGED=NO
REAL_MELI_HTTP=0
```

Controller: on disabled pristine account after existing history checks, missing/invalid ack returns 422 without deletion, only exact `confirm_permanent_delete='1'` can proceed. UI: native `<details data-meli-destructive-action="delete">` closed by default, factual local-only copy, form CSRF, required checkbox, explicit destructive button. Positive delete and 404/409/419 regression tests retained; RED + HTML test now pass.

## H7C-B2 independent real-browser forensic closure — PASS

Antigravity certified real Chrome 155.0.8059.84 on an isolated TEST runtime. ChatGPT independently checked both attached ZIPs (same 27 internal file bytes but ZIP compression/order differing), every 26/26 manifest SHA-256, report byte equality, 5 screenshots, DOM/keyboard/HTML5 native validity, role 200/403, responsive mobile geometry, H7A shell, console/network, DB before/after, Git and live CI.

```text
H7C_B2_RED=3595880b6f4e6cdaeef6cc0ebff9f85c0e7cb723
H7C_B2_RED_CI=39072807611 EXPECTED FAILURE
H7C_B2_GREEN=d00eb26f37488d8552d2b1598d5b27dcb76967dd
H7C_B2_GREEN_CI=39073100289 SUCCESS
H7C_B2_GREEN_PHP_8_3=828 tests / 7037 assertions / 0 failures
H7C_B2_GREEN_PHP_8_4=PASS
H7C_B2_GREEN_PHP_8_5=PASS
H7C_B2_GREEN_PHPSTAN=PASS
BROWSER_CERTIFIED_HEAD=21968d5ba0770f59db3e88348bb90d4ff6c506f6
BROWSER_CERTIFIED_HEAD_CI=39073464183 SUCCESS
H7C_B2_BROWSER_CERTIFIED=YES
H7C_B2_INDEPENDENT_AUDIT=PASS
H7C_B2_FULLY_CLOSED=YES
BROWSER_CHROME=155.0.8059.84
DESKTOP=1440x900 PASS
MOBILE=390x844 PASS
COMPANY_ADMIN_GET=200
OPERATOR_GET=403
ELIGIBLE_DISABLED_PRISTINE_DELETE=VISIBLE_IN_CLOSED_DETAILS
CONNECTED_DELETE=HIDDEN
DISABLED_WITH_HISTORY_DELETE=HIDDEN
ATTENTION_DELETE=HIDDEN
DELETE_REQUIRED_ACK_DEFAULT_UNCHECKED=PASS
NATIVE_VALIDITY_UNCHECKED_FALSE_CHECKED_TRUE_RESTORED_FALSE=PASS
KEYBOARD_TAB_ENTER_SPACE=PASS
SHARED_SHELL_H7A=PASS
MOBILE_NAV_SLOTS=5
HORIZONTAL_OVERFLOW=NO
BOTTOM_NAV_OBSTRUCTION=NO
UNCAUGHT_JS=0
CONSOLE_ERROR_RAW_COUNT=1 EXPECTED_403_CHROME_ERROR_PAGE
FAVICON_404=2 INCIDENTAL
PRODUCT_JS_DEFECT_PROVEN=NO
BROWSER_REAL_MELI_CALLS=0
BROWSER_DESTRUCTIVE_POSTS=0
APPLICATION_5XX=0
DB_BEFORE_AFTER=IDENTICAL
CERTIFIER_REPO_CHANGES=0
CERTIFIER_COMMITS_PUSHES_MERGES=0
MANIFEST=26/26 EXACT
EVIDENCE_ZIP_SHA256=f9e95998b5cb0802bab5a7df271715552c9b55b039e3b10bac9583ee0abb8753
SUPPLEMENTARY_ZIP_SHA256=9342658d07222ef427f75b7dfc8324b1b60db8ab1931193bcce0b2f9619adccd
PR_19=OPEN / DRAFT / NOT MERGED
```

External evidence received:
- `ERP_MELI_2_0_H7C_B2_BROWSER_ANTIGRAVITY_REPORT_2026-10-17.md`
- `ERP_MELI_2_0_H7C_B2_BROWSER_ANTIGRAVITY_EVIDENCE_2026-10-17.zip`
- `20261017-172800.zip` (same internal contents, differing archive bytes)
- User supplied final ZIP hash in certifier output; separate `.zip.sha256` was NOT attached. ChatGPT independently calculated the final ZIP SHA and created the sidecar, so the verification is independent, not a claim of certifier delivery.

No product defect is proven by the known expected operator GET 403 Chrome console-network message; it remains transparently counted as one in raw evidence. No fixes/patches warranted.

## H7C-C — System clear debug — FULLY CLOSED (independent Chrome V5 audit)

Precise owner: `app/Modules/Settings/SystemSettingsController.php::clearDebug()` and `app/Modules/Settings/views/system.php`. Existing route `POST /settings/system/debug/clear`, existing `DebugMaintenance::clearDebug()` recognizes only local debug files; no schema/route/JS/CSS/remote MELI modifications.

Test-only RED:
```text
H7C_C_RED=b1fe1533db7809f689c398d111d4292474b5c31a
H7C_C_RED_CI=39079965535 EXPECTED FAILURE
RED_PHP_8_3=829 tests / 7040 assertions / exactly 1 failure
RED_ONLY_FAILURE=DebugSettingsControllerTest::testClearDebugRejectsMissingOrInvalidHumanConfirmationWithoutDeletingFiles
RED_OBSERVED=303 with valid CSRF and missing confirm_clear_debug
RED_EXPECTED=422 without deleting temp debug files
RED_PRODUCTION_CHANGED=NO
```

Minimal GREEN:
```text
H7C_C_GREEN=901b8652c825251f6189f6a11bea8c6ba7f198af
H7C_C_GREEN_CI=39080177081 SUCCESS
GREEN_PHP_8_3=829 tests / 7049 assertions / 0 failures
GREEN_PHP_8_4=829 tests / 7049 assertions / 0 failures
GREEN_PHP_8_5=829 tests / 7049 assertions / 0 failures
GREEN_PHPSTAN=PASS
RED_TO_GREEN_FILES=3
RED_TO_GREEN_CONTROLLER=app/Modules/Settings/SystemSettingsController.php (+3/-0)
RED_TO_GREEN_VIEW=app/Modules/Settings/views/system.php (+9/-4)
RED_TO_GREEN_TEST=tests/Integration/DebugSettingsControllerTest.php (+12/-1)
ROUTES_CHANGED=NO
SCHEMA_CHANGED=NO
CSS_CHANGED=NO
JS_CHANGED=NO
DEBUG_MAINTENANCE_ALGORITHM_CHANGED=NO
OAUTH_CHANGED=NO
WORK_CHANGED=NO
FINANCIAL_CHANGED=NO
REAL_MELI_HTTP=0
```

Existing System Admin DB authorization, CSRF 419, optional DebugMaintenance 503 and recognized-file deletion preserved. Added exact server acknowledgement `confirm_clear_debug='1'`, otherwise 422 without file deletion. UI now has native closed-by-default `<details data-system-destructive-action="clear-debug">`, factual scope copy (recognized local debug files only, preserving ZIP exports, unrelated files, business data), required checkbox, and original CSRF-protected POST inside.

### V5 — Real Chrome certification + independent audit (2026-10-21)

- Tester: Codex Luna in disposable loopback Chrome 155.0.8059.85, 1440×900 desktop and 390×844 mobile; product code and shared checkout unchanged.
- Exact certified browser HEAD: `970153033003547a16b2c968db6ba4d0264cdb03`; GREEN→HEAD changed only `docs/CURRENT_CHECKPOINT.md` and `docs/PROJECT_CONTINUITY_CURRENT.md` (two docs-only commits).
- Independent evidence: `ERP_MELI_2_0_H7C_C_CODEX_LUNA_BROWSER_REPORT_V5_2026-10-21.md`, `ERP_MELI_2_0_H7C_C_CODEX_LUNA_BROWSER_EVIDENCE_V5_2026-10-21.zip`, matching `.zip.sha256`; independent verdict `ERP_MELI_2_0_H7C_C_AUDITORIA_INDEPENDIENTE_CIERRE_V5_2026-10-21.md`.
- ZIP SHA-256 `c3db19754c9ea438032ddafe36827d32ab22d33e5300a04c209a42cfdf5c3f44`; 31 members, 30/30 manifest SHA, CRC PASS, external/internal report byte-identical.
- Server auth 200 System Admin / 403 non-System Admin; `<details>` closed by default; `confirm_clear_debug='1'` checked only when human intentionally toggles; native validity false→true→false, 26 real Tabs, Enter/Space/focus PASS.
- Mobile five navigation slots, no horizontal overflow, 35.734375px bottom-button clearance; H7A skip link and Apps opened by keyboard, focus inside and returned by Escape.
- Browser network 27 requests (25 GET, 2 synthetic login POST), 0 destructive/config/export/MELI POST, 0 JS uncaught and 0 HTTP 5xx. One raw console error attributable to intentional 403; not hidden.
- Before/after byte-identical deterministic DB snapshots including `system_settings`; debug and export filesystem inventories identical with file SHA-256.
- Independent V2 auditor: 108 PASS, two *non-substantive text-format false negatives*, adjudicated via original GitHub and artifacts; report's RED expected/observed order typo documented against official CI log (actual original 303 vs expected 422).
- Scope at the V5 capture: isolated TEST, no real destructive POST or production deployment. H7C-D1 status is recorded below. PR #19 remains OPEN/DRAFT/NOT MERGED.

## H7C-D1 — System Admin revocation confirmation (FULLY CLOSED; independent evidence audit PASS)

BASE_HEAD=27f6e222ae7077de3d4a33af1423c8bdc70d1aa2
RED_LOCAL=9d954ad17ced0d53ba3f62740f92403048b54dd3
RED=5950fd92cd0cc213a8a06c73f977c10f1e4c5b57
RED_CI=39661909819 EXPECTED_SINGLE_FAILURE
GREEN_LOCAL=6d7feafe52227187399a3d51e04d2581ad5a3982
GREEN=44e50342a5dcb606e90c07b4ad199102a434344d
GREEN_CI=39666739964 SUCCESS
PHP_8_3_8_4_8_5=834 tests / 7156 assertions / 0 failures per matrix
LOCAL_PHPUNIT=834 tests / 7153 assertions / 1 Windows symlink skip
PHPSTAN=PASS
TEST_DB_ISOLATED=YES
BROWSER_OBSERVED=PASS
BROWSER_INDEPENDENT_AUDIT=PASS_WITH_DOCUMENTED_LIMITATIONS
D1_FULLY_CLOSED=YES
D2_STARTED=NO
CODE_OWNER_FILES_CHANGED=3
REMOTE_WRITES=OFF
REAL_MELI_HTTP=0

- RED reproduced the intended defect: the valid, unconfirmed self-revocation returned 303 instead of 422. RED CI had that single failure in each PHP matrix.
- GREEN changes only UserManagementController.php, views/users.php and UserManagementTest.php. The exact string acknowledgement is checked after last-active-admin protection and before UPDATE. Existing route, transaction, authorization, CSRF, session and membership behavior remain covered.
- Chrome 155.0.8059.85 observed 200 for the System Admin and 403 for a restricted user. Desktop 1440×900 and mobile 390×844: collapsed disclosures, required checkbox, false→true→false native validity, Enter/Space/Tab, direct grant without confirmation, Apps Enter/Escape focus return, no horizontal overflow, and consent controls clear of the fixed mobile nav all PASS.
- Browser sent no revocation/configuration/export/MELI POST. Synthetic DB snapshots for users, company_users, companies and system_settings match before/after; relevant storage file hashes also match.
- Evidence: H7C_D1_BROWSER_REPORT_2026-10-21.md and H7C_D1_BROWSER_EVIDENCE_2026-10-21.zip. ZIP SHA-256: b56332f1e88194298dc1b1761eda8bbebe84349c599ab31829955e61bd1a4cef. Matching .zip.sha256 and per-member SHA256SUMS.txt are included. Codex observation is not independent certification.
- The local suite skipped the symlink test on Windows; CI Linux ran it, accounting for the three-assertion difference. PHPUnit local had zero failures/errors. The full suite output retains expected diagnostics from negative tests.

### Independent audit closure — 2026-10-21

- Inputs: `H7C_D1_BROWSER_REPORT_2026-10-21.md`, `H7C_D1_BROWSER_EVIDENCE_2026-10-21.zip`, matching `.zip.sha256`. Independent conclusion: `ERP_MELI_2_0_H7C_D1_AUDITORIA_INDEPENDIENTE_CIERRE_2026-10-21.md` (conversation artifact).
- ZIP SHA-256 `b56332f1e88194298dc1b1761eda8bbebe84349c599ab31829955e61bd1a4cef`, CRC and extraction PASS, 23 ZIP members/22 SHA manifest members verified, report outside = ZIP report byte-identical. Three screenshots inspected.
- Real GitHub confirms RED `5950fd92cd0cc213a8a06c73f977c10f1e4c5b57`, run `39661909819` one expected 303-vs-422 failure on each PHP 8.3/8.4/8.5; GREEN `44e50342a5dcb606e90c07b4ad199102a434344d`, run `39666739964` SUCCESS; browser HEAD `63ba16cc239339fa263afebe6e3093e4add97add`, run `39669622138` SUCCESS, 834 tests/7156 assertions per PHP version, PHPStan PASS. GREEN→HEAD docs-only.
- Owner server guard `confirm_remove_system_admin==='1'` for actual revocation after last-admin protection, before UPDATE; no-ack 422/DB unchanged; existing 403/419/404/409, valid 303, self-session cleanup, company membership preservation verified in code/tests.
- Isolated Chrome 155.0.8059.85 (1440×900 and 390×844), admin 200/non-System Admin 403; collapsed disclosure, required checkbox checkValidity false→true→false, keyboard, five-slot mobile nav, Apps focus return, no destructive browser POST/MELI call. DB JSON before/after byte-identical for users, company_users, companies, system_settings; six synthetic .gitkeep FS inventory entries byte-identical.
- Transparency: Chrome was executed by Codex, then its artifacts were independently checked, not replayed by ChatGPT. DB rows not contained in ZIP (table hashes and row counts only); storage inventory was empty placeholders, so it certifies absence of observed mutation, not a real revocation or deletion. Raw admin console has 4 VERBOSE autocomplete messages; two incidental login favicon404s; 403 console error expected. One Windows symlink test skip executed on green Linux CI.
- Product change frozen; this closure changes only continuity docs/PR, no production deployment or merge.

## Exact resume point

NEXT=H8-A APP CREDENTIAL CLEAR REAL-BROWSER CERTIFICATION ONLY
H7C_A_FULLY_CLOSED=YES
H7C_B1_FULLY_CLOSED=YES
H7C_B2_FULLY_CLOSED=YES
H7C_C_AUTOMATED_GREEN=YES
H7C_C_BROWSER_CERTIFIED=YES
H7C_C_INDEPENDENT_AUDIT=PASS_WITH_MINOR_DOCUMENTARY_NOTES
H7C_C_FULLY_CLOSED=YES
H7C_D_STARTED=YES
H7C_D1_AUTOMATED_GREEN=YES
H7C_D1_BROWSER_OBSERVED=PASS
H7C_D1_BROWSER_INDEPENDENTLY_CERTIFIED=YES
H7C_D1_INDEPENDENT_AUDIT=PASS_WITH_DOCUMENTED_LIMITATIONS
H7C_D1_FULLY_CLOSED=YES
H7C_D2_STARTED=YES
H7C_D2_AUTOMATED_GREEN=YES
H7C_D2_BROWSER_OBSERVED=PASS
H7C_D2_BROWSER_INDEPENDENTLY_CERTIFIED=YES_WITH_COMPOSITE_EVIDENCE_AND_LIMITATIONS
H7C_D2_FULLY_CLOSED=YES
H7C_PHASE_FULLY_CLOSED=YES
H8_STARTED=YES
H8A_AUTOMATED_GREEN=YES
H8A_BROWSER_CERTIFIED=NO
H8A_FULLY_CLOSED=NO
H8B_STARTED=NO
H9_STARTED=NO
PR_19=OPEN / DRAFT / NOT MERGED
MERGE_AUTHORIZED=NO
STOP_SAFE=YES

### H7C-D2 — confirmación humana para retirar membresías (2026-10-21)

START_HEAD=afeba7940aea51f0ceefa24ddba6c89c430599ac
RED_TEST_ONLY_SHA=56f99973ce9706657dde859aded519f685b2efec
RED_CI=39674454144 EXPECTED_SINGLE_FAILURE (PHP 8.3/8.4/8.5)
GREEN_SHA=715f4a9ae93452df75fc5d32eaa0514accf12ea7
GREEN_CI=39678811548 SUCCESS (PHP 8.3/8.4/8.5 + PHPStan)
HEAD_AT_BROWSER=715f4a9ae93452df75fc5d32eaa0514accf12ea7
LOCAL_PHPUNIT=840 tests / 7315 assertions / 1 Windows symlink skip / 0 failures
PHPSTAN=PASS
COMPOSER_VALIDATE=PASS
PHP_LINT=PASS (230 PHP files)
TEST_DB_ISOLATED=YES (dedicated disposable MariaDB; `erp_meli2_test`)
BROWSER=Chrome 155.0.8059.85; desktop 1440x900; mobile 390x844
SYSTEM_ADMIN=HTTP 200; ordinary operator same-origin GET `/system/users`=403
MOBILE_NAV=5 slots; no horizontal overflow; consent checkbox and button clear of fixed nav
CONSENT=closed by default; required unchecked; checkValidity false→true→false; Enter/Space pass
APPS_KEYBOARD=4 Tab to launcher; Enter opens and focuses dialog; Tab stays inside; Escape closes and restores focus
BROWSER_PROHIBITED_POSTS=0 (only two synthetic login POSTs); external HTTP=0; application 5xx=0; uncaught JS=0
DB_BEFORE_AFTER=identical hashes/counts for users, company_users, companies, system_settings
FILESYSTEM_BEFORE_AFTER=identical 929-file inventory; filenames pseudonymized in evidence
CODE_OWNER_FILES_CHANGED=UserManagementController.php, views/users.php, UserManagementTest.php
D1_UNCHANGED=YES
EVIDENCE=H7C_D2_BROWSER_REPORT_2026-10-21.md + H7C_D2_BROWSER_EVIDENCE_2026-10-21.zip + .zip.sha256

### H7C-D2 independent composite closure — 2026-10-21

- ChatGPT independently verified D2 browser ZIP `95e0076e410eb3c9256f59f9fb2ef017c9600a3f8eca487a3e2989614dbf84aa`: CRC, 41/41 members, 40/40 hashes, report identical, original real Chrome admin 200/non-admin 403, keyboard and responsive evidence. GitHub: RED `39674454144` expected single failure; GREEN `39678811548` and browser HEAD `39681959280` SUCCESS, PHP 8.3/8.4/8.5 840 tests / 7318 assertions.
- Supplemental FS microcert ZIP `a3d227b52aabc2287eaf6eb3287858432409b708e5d4c47ad20e16d72e7d2ffd`: CRC, 7/7 members, 6/6 SHA, same source HEAD `ec6afa00a8327a6b7e1aa1383dfa5869ee55ce0f`; monotonic BEFORE finished before first GET, browser closed before AFTER; 12 synthetic files unchanged byte-for-byte, 7 storage directories, 527 content bytes. No prohibited POST/MELI call.
- Limitations kept visible: original 929-file browser interval **not** proven chronologically; microcert tool marked `BLOCKED` because a manual redirect login `POST /login` yielded `opaqueredirect`/aborted without observable 303 and no SHA of snapshot JSON was persisted at instant of capture. Nonetheless the same microcert run returned HTTP 200 at `/system/users`; existing `CompanyContext::assertSystemAdmin` verifies active System Admin directly in DB for that route. Independent adjudication accepts separate full original browser authorization evidence plus equivalent new filesystem interval; it does **not** change `MICROCERT_RESULT=BLOCKED` or claim contemporaneous cryptographic attestation.
- PHPUnit tested destructive POST on disposable MariaDB, not real Chrome. No product files changed for closure, no merge/deploy. Pre-existing `runtime.json`, `installed.lock`, user exports/temporary files must not be deleted without provenance checks.
- Independent report (conversation artifact): `ERP_MELI_2_0_H7C_D2_AUDITORIA_INDEPENDIENTE_FINAL_COMPUESTA_2026-10-21.md`. Next H8 preflight only; H8 implementation NOT started.


## H8-A — MELI Developer app local credential clear (automated GREEN; Chrome pending)

- READ-only preflight checked `app/Modules/Settings/MeliAppSettingsController.php`, `views/mercadolibre.php`, `MeliAppSettingsTest.php`, design authority and H8 master plan. Defect proven: privileged CSRF-valid `POST /settings/mercadolibre` with `action=clear` had no second acknowledgement and returned 303 while clearing local `MELI_CLIENT_ID` and `MELI_CLIENT_SECRET`.
- Test-only RED `994744c819aa781b9c287e995bdac50617f5253a`; CI `39700960434` EXPECTED FAILURE. Exactly one failure in each PHP 8.3/8.4/8.5: `MeliAppSettingsTest::testClearRequiresExplicitHumanConfirmationAndPreservesPrivateRuntime`, observed 303 vs expected 422 (841 tests / 7321 assertions at failure).
- First GREEN `042e52de4169a84ad96ee1a917123c12186c6b59`, CI `39701598048` SUCCESS (842 tests / 7363 assertions). Final functional GREEN `5ce08784a55779886703ac6f2125224c764bcd1d`, CI `39701894315` SUCCESS in PHP 8.3/8.4/8.5: **842 tests, 7371 assertions each, PHPStan PASS**.
- Changed owner paths only: `app/Modules/Settings/MeliAppSettingsController.php`, `app/Modules/Settings/views/mercadolibre.php`, `tests/Integration/MeliAppSettingsTest.php`. No routes, schema, new classes/frameworks, CSS/JS, OAuth, MELI remote HTTP, Billing/Financial/Work/429 modifications.
- Server now requires exact `confirm_clear_meli_credentials='1'` **before acquiring `install.guard`**, so invalid/missing ack returns 422 without rewriting `runtime.json` or creating the lock; preserve 403/419/409/503, server-managed read-only and valid 303 clear behavior.
- UI has native closed-by-default `<details data-meli-app-destructive-action="clear-credentials">`, factual scope copy, original CSRF and `action=clear`, required unchecked checkbox, clear danger CTA. Saving app credentials and server-managed settings remain untouched. Regression tests check byte-identical `runtime.json`, `installed.lock`, `install.guard`, plus DOM structure, no nested forms and no leaked secret.
- **Safety:** `MeliAppSettingsTest` prepares `storage/private/runtime.json` and `installed.lock` in its checkout; NEVER run this test suite in a shared local user checkout or against user's pre-existing storage. GitHub Actions uses disposable CI. Browser certifier must use isolated TEST clone/DB/storage.
- `H8A_AUTOMATED_GREEN=YES`, `H8A_BROWSER_CERTIFIED=NO`, `H8A_FULLY_CLOSED=NO`. Browser certification only on functional HEAD above or proven docs-only descendant. **Codex Luna may certify browser only, not change code or Git**. Do not start H8-B/H9; do not reopen H7C. PR #19 OPEN/DRAFT/NOT MERGED, no deploy/remote write.

## ERP1 / 429 frozen truth

ERP1 had Billing/Financial/Work instability including 429/FIFO/timing symptoms and architecture drift. Universal root cause remains:

```text
UNIVERSAL_ERP1_429_ROOT_CAUSE=NOT_PROVEN
```

No Financial/Work/429 retry, pacing, queue, batching or transport redesign without fresh evidence.

## Frozen / prohibited

No PR merge or merge commit without explicit approval, Hostinger, cron, real MELI mutation, broad navigation redesign, generic modal framework, UNKNOWN->0, patch-chain CSS, speculative ERP1 architecture, or reopening closed Human-First blocks without evidence.
