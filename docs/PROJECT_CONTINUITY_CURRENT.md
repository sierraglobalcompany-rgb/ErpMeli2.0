# PROJECT CONTINUITY CURRENT — ERP MELI 2.0

**Status:** LIVE RECOVERY ANCHOR  
**Audit date:** 2026-10-17  
**Rule:** verify GitHub live before acting; this file is a recovery anchor, not a substitute for repository truth.

## Repository authority

```text
REPO=sierraglobalcompany-rgb/ErpMeli2.0
REMOTE=https://github.com/sierraglobalcompany-rgb/ErpMeli2.0.git
BRANCH=ui/design-master-v1-20261015
FUNCTIONAL_HEAD=22384c2c616da89d34e37543cd42e8ae13c54e06
FUNCTIONAL_HEAD_CI=39055307246 SUCCESS
PR=19 OPEN / DRAFT / NOT MERGED
BASE=ui/operator-shell-v1-20261014
BASE_SHA=c6f8af7f446cb327a905ae2f9bd9ee424c2d421f
LOCAL_WORKTREE=NOT_VERIFIED
```

The branch may have a later documentation-only descendant after this anchor is written. Always query live HEAD and inspect any delta before continuing. Final functional H7C-A GREEN `22384c2c...` must remain an ancestor.

## Project laws

```text
KISS=MANDATORY
ANTI_NOISE=MANDATORY
ANTI_PATCH=MANDATORY
HUMAN_FIRST=MANDATORY
TRUTH_BEFORE_BEAUTY=MANDATORY
UNKNOWN_NEVER_BECOMES_ZERO=MANDATORY
EVIDENCE_BEFORE_GREEN=MANDATORY
NO_MERGE_WITHOUT_EXPLICIT_USER_APPROVAL=MANDATORY
REMOTE_WRITES=OFF
REAL_MELI_HTTP=0
```

ChatGPT is the primary project driver. Antigravity is certification-only when real local-browser control is unavailable; it must not fix product code. Codex is reserved for material local-machine barriers, not routine implementation.

## Certified history

```text
H1_FIX1_SALE_DETAIL=GREEN
H2A_SKIP_TO_MAIN=GREEN
H2B_TYPOGRAPHY_TOUCH=GREEN
H3_DASHBOARD=GREEN
H4_SALES_LIST=FULLY_CLOSED
H5_CATALOG_INVENTORY=FULLY_CLOSED
H6A_H6F_BILLING=FULLY_CLOSED
H7A_GLOBAL_ADMIN_SHARED_SHELL=FULLY_CLOSED
H7B_USERS_PROGRESSIVE_DISCLOSURE=FULLY_CLOSED
```

H7B closure anchor:

```text
H7B_GREEN=e6711bd9ccbb5a6298c14885f4f2341040a94343
H7B_GREEN_CI=39043131947
H7B_PHP_8_3=823 tests / 6999 assertions / 0 failures
H7B_BROWSER_CERTIFIED_HEAD=16d50961cda86ef7f886affe8676d470422607cd
H7B_EVIDENCE_ZIP_SHA256=6aa6144e6f34432e1e5e86e2aeffbd9c99b387afbc39a0f5ed9e69576a692da7
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

## Current active block — H7C-A Companies

Initial presentation RED/GREEN:

```text
H7C_A_RED=1c8bae3374c7a36af075f3b5bb59c64c95cef763
H7C_A_RED_CI=39049451668 EXPECTED FAILURE
H7C_A_INITIAL_GREEN=d8cfbe04a924304cb1234153fe873a34bef4bab4
H7C_A_INITIAL_GREEN_CI=39049794704 SUCCESS
INITIAL_GREEN_PHP_8_3=824 tests / 7007 assertions / 0 failures
```

Forensic hardening before browser certification found one root-owner defect: HTML required `confirm_permanent_delete`, but `CompanyManagementController` did not require that acknowledgement server-side. A direct POST could therefore bypass the intended human confirmation.

Hardening RED/GREEN:

```text
H7C_A_ACK_RED=ff0255d4826e31948852c1fee5dff40131b705e6
H7C_A_ACK_RED_CI=39054722005 EXPECTED FAILURE
ACK_RED_PHP_8_3=825 tests / 7011 assertions / exactly 1 failure
ACK_RED_ONLY_FAILURE=H7CCompanyPermanentDeleteAcknowledgementTest
H7C_A_GREEN=22384c2c616da89d34e37543cd42e8ae13c54e06
H7C_A_GREEN_CI=39055307246 SUCCESS
GREEN_PHP_8_3=PASS
GREEN_PHP_8_4=PASS
GREEN_PHP_8_5=PASS
GREEN_PHPSTAN=PASS
GREEN_PHP_8_3_RESULT=825 tests / 7020 assertions / 0 failures
```

Final functional hardening diff is bounded:

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

Implemented H7C-A contract:
- archive behind native `<details>` with factual impact copy;
- `/archive` + CSRF preserved;
- `Reactivar` remains direct/restorative;
- permanent delete remains conditional on backend `deletable`;
- delete behind native `<details>`;
- required `confirm_permanent_delete` checkbox;
- backend independently requires `confirm_permanent_delete=1`;
- missing/invalid acknowledgement returns 422 without deletion;
- `/delete` + backend authority preserved;
- user remains preserved;
- no generic modal/framework/schema/permission/JS redesign.

## H7C-A — independently audited real-browser closure

Functional owner GREEN remains `22384c2c616da89d34e37543cd42e8ae13c54e06` (CI `39055307246` SUCCESS). Antigravity V3 ran real Chrome 155.0.8059.84 against disposable local TEST runtime at certified HEAD `95e15d84afe11fba4752d7bbdb6b32b456b58858`. ChatGPT independently verified SHA-256 manifest 29/29, 8 screenshots, structural/keyboard/accessibility JSON, HTTP 200/403, mobile geometry, shell, zero destructive posts and MELI browser calls, DB snapshots identical, and live GitHub lineage/PR/CI.

```text
H7C_A_AUTOMATED_GREEN=YES
H7C_A_ACK_SERVER_SIDE=GREEN
H7C_A_BROWSER_CERTIFIED=YES
H7C_A_INDEPENDENT_AUDIT=PASS
H7C_A_FULLY_CLOSED=YES
CERTIFIED_HEAD=95e15d84afe11fba4752d7bbdb6b32b456b58858
CERTIFIED_HEAD_CI=39055734137 SUCCESS
SYSTEM_ADMIN_HTTP=200
ORDINARY_COMPANY_ADMIN_HTTP=403
DESKTOP_1440x900=PASS
MOBILE_390x844=PASS
ARCHIVE_DELETE_DETAILS_AND_KEYBOARD=PASS
DELETE_ACK_NATIVE_VALIDITY=PASS
H7A_SHARED_SHELL=PASS
MOBILE_NAV_SLOTS=5
HORIZONTAL_OVERFLOW=NO
BOTTOM_NAV_OBSTRUCTION=NO
APPLICATION_5XX=0
APP_JS_ERRORS=0
DESTRUCTIVE_ROUTE_POSTS=0
REAL_MELI_BROWSER_NETWORK_CALLS=0
DB_BEFORE_AFTER=IDENTICAL
CERTIFIER_GIT_BEFORE_AFTER=CLEAN
CERTIFIER_REPO_CODE_TEST_DOC_WRITES=0
PR_MERGED=NO
STOP_SAFE=YES
EVIDENCE_MANIFEST=29/29 EXACT
EVIDENCE_ZIP_SHA256=177f76c87774f20962219743424341384349f0e30a0d086d2cb061eac87d1e88
```

Attached audit inputs (not source files in repository):
- `ERP_MELI_2_0_H7C_A_BROWSER_ANTIGRAVITY_REPORT_2026-10-17.md`
- `ERP_MELI_2_0_H7C_A_BROWSER_ANTIGRAVITY_EVIDENCE_2026-10-17.zip`
- `ERP_MELI_2_0_H7C_A_BROWSER_ANTIGRAVITY_EVIDENCE_2026-10-17.zip.sha256`

Minor notes: favicon 404 was classified infrastructure/static; Apps launcher focus-inside measured before animation frame, but Escape + focus-return passed. These are non-blockers, not grounds for a patch.

## H7C-B1 — disconnect UX FULLY CLOSED

Scope remained only `app/Modules/Settings/views/meli-accounts.php`: wrap connected-account disconnect in closed-by-default `<details>` explaining local credential removal, account deactivation inside this ERP, retained history, possible reauthorization. Existing POST/CSRF kept; no controller, route, schema, JS, CSS, OAuth, Work or Financial modification.

```text
H7C_B1_RED=d6b96bcbc5b7189b231ae84c8e03ed881858d912
H7C_B1_RED_CI=39070718318 EXPECTED FAILURE
RED_PHP_8_3=826 tests / 7022 assertions / 1 failure ONLY new B1 UX test
H7C_B1_GREEN=ab640fe222bc33fc9083fb25596b41133f37bab8
H7C_B1_GREEN_CI=39070978051 SUCCESS
GREEN_PHP_8_3=826 tests / 7027 assertions / 0 failures
GREEN_PHP_8_4=PASS
GREEN_PHP_8_5=PASS
GREEN_PHPSTAN=PASS
RED_GREEN_DIFF=view only +8/-4
CERTIFIED_HEAD=ce09d7b21bb6d619884307959b13c58cd9faf842
CERTIFIED_HEAD_CI=39071315439 SUCCESS
BROWSER_REAL_LOCAL_CHROME=YES
BROWSER_DESKTOP_1440x900=PASS
BROWSER_MOBILE_390x844=PASS
COMPANY_ADMIN_GET_200=PASS
NON_ADMIN_GET_403=PASS
DETAILS_KEYBOARD_ENTER_SPACE=PASS
SHARED_H7A_SHELL=PASS
MOBILE_NAV_SLOTS=5
APPLICATION_JS_ERRORS=0
APPLICATION_5XX=0
REAL_MELI_BROWSER_CALLS=0
DESTRUCTIVE_POSTS=0
DB_BEFORE_AFTER=IDENTICAL
CERTIFIER_WORKTREE_AFTER=CLEAN
CERTIFIER_WRITES=0
INDEPENDENT_AUDIT=PASS
H7C_B1_BROWSER_CERTIFIED=YES
H7C_B1_FULLY_CLOSED=YES
MANIFEST=26/26
ZIP_SHA256=ce44f76272dd5743abc23f60ef6ea95a151e9a34e8944a32180172aebca71350
PR_MERGED=NO
```

Independent audit inputs external to repository: `ERP_MELI_2_0_H7C_B1_ANTIGRAVITY_BROWSER_REPORT_2026-10-17.md`, `ERP_MELI_2_0_H7C_B1_ANTIGRAVITY_BROWSER_EVIDENCE_2026-10-17.zip`, and matching `.zip.sha256`. All 26/26 SHA-256 manifest entries verified, 5 screenshots reviewed, Chrome session network and DB before/after inspected, RED/GREEN and CI checked against live GitHub.

### Exact NEXT

```text
NEXT=H7C-B2 HARD DELETE CONFIRMATION — FIRST RED TEST
H7C_A_FULLY_CLOSED=YES
H7C_B1_FULLY_CLOSED=YES
H7C_B2_STARTED=NO
H7C_C_STARTED=NO
H7C_D_STARTED=NO
H7C_B2_OWNER=MeliAccountController::hardDelete + views/meli-accounts.php
H7C_B2_DEFECT=direct hard-delete POST can succeed without explicit confirmation acknowledgement
H7C_B2_GUARDS=company admin / CSRF / status disabled / persisted-history check MUST REMAIN
NO_REAL_MELI=YES
PR_19=OPEN / DRAFT / NOT MERGED
STOP_SAFE=YES
```

Prepare RED for missing/invalid `confirm_permanent_delete` on eligible disabled pristine account, proving HTTP 422, unchanged row; after RED verified apply minimal GREEN in owner and view, with existing successful deletion tests updated only for valid acknowledgment. Preserve 403/404/409/419 and no changes to history predicates or remote integrations. Chrome/browser independent QA follows GREEN.

## Recovery sources

Read, in order:

1. `docs/PROJECT_CONTINUITY_CURRENT.md`
2. `docs/CURRENT_CHECKPOINT.md`
3. `AGENTS.md`
4. `docs/ERP2_AUTHORITY.md`
5. `docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md`
6. `design-system/erp-meli-2-0/MASTER.md`
7. `docs/UX_HUMAN_FIRST_MASTER_AUDIT_AND_IMPROVEMENT_PLAN_2026-10-16.md`
8. `docs/ERP_MELI_2_0_RECOVERY_HANDOFF_H7C_A_AUTOMATED_GREEN_BROWSER_PENDING_2026-10-17.md`

Important drift note: the Human-First master plan still contains an old bottom `NEXT=H1`; that is historical plan text and is superseded by the live checkpoint/continuity anchor. Do not restart H1.

The older recovery handoff predates acknowledgement hardening; for the current functional target, this live anchor and `docs/CURRENT_CHECKPOINT.md` take precedence, after live GitHub verification.

UI UX Pro Max note: the official methodology references `nextlevelbuilder/ui-ux-pro-max-skill`, but no repository-local installation/version was verified on this active branch. Do not invent or auto-update it during H7C-A; the project Design System remains the versioned visual authority.

## Frozen

No PR merge or merge commit without explicit approval, Hostinger, cron, real MELI mutation, Financial/Work/429 redesign, retry/pacing/queue/batching transport changes, generic modal framework, UNKNOWN→0, patch-chain CSS, or reopening closed Human-First blocks without fresh evidence.
