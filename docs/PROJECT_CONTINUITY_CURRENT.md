# PROJECT CONTINUITY CURRENT — ERP MELI 2.0

## Autoridad de recuperación — sin duplicar el checkpoint

El estado, SHA funcional, última QA, las cuatro leyes y el procedimiento Codex Luna se mantienen completos en **`docs/CURRENT_CHECKPOINT.md`** (leer primero). Esta referencia **reemplaza 46 líneas idénticas** antes copiadas aquí, demostradas por comparación literal, sin borrar evidencia histórica ni decisiones.

```text
REPO=sierraglobalcompany-rgb/ErpMeli2.0
BRANCH=ui/design-master-v1-20261015
PR_19=OPEN / DRAFT / NOT MERGED
FUNCTIONAL_H8A_SHA=5ce08784a55779886703ac6f2125224c764bcd1d
FUNCTIONAL_CI=39701894315 SUCCESS (842 tests / 7371 assertions, PHP 8.3/8.4/8.5)
PRIOR_CHECKPOINT_SHA=5af2ff8291a55f20da78efd99493da2b0b250702
PRIOR_CHECKPOINT_CI=39704436927 SUCCESS
H7C_PHASE_FULLY_CLOSED=YES
H8A_AUTOMATED_GREEN=YES
H8A_BROWSER_CERTIFIED=NO
H8A_FULLY_CLOSED=NO
H8B_STARTED=NO (READ-ONLY PREFLIGHT DONE, NO RED)
H9_STARTED=NO
NEXT=H8-A REAL CHROME QA BY CODEX LUNA -> INDEPENDENT CHATGPT AUDIT
```

### H8-B: hallazgo focal preparado, implementación AÚN NO iniciada (preauditoría 2026-10-21)

- **Evidencia directa, sin BD:** En PHP 8.4, `filter_var('abc', FILTER_VALIDATE_INT)` devuelve `false` y `filter_var('0', FILTER_VALIDATE_INT)` devuelve `0`; el `?: 7`/`?: 100` de `SystemSettingsController::update()` los sustituye silenciosamente por valores predeterminados. `'3.5'`, `''` y `'1e3'` también producen fallback. **Aún no se probó HTTP mediante RED**, por lo que 303 es consecuencia esperada del código, no observación E2E certificada.
- Falso positivo cerrado: `SystemSettingsInvalidInputHttpRouteTest` ya cubre 422 para enteros `5` días y `9` MB; `CompanySelectorHomeContractTest` ya certifica selector único → `/dashboard`. **No rehacer DM8-A1 ni el antiguo 500 de DM8-A3**.
- Próximo microbloque después del cierre completo H8-A: RED de entrada **presente** inválida (`abc`, `0`, `3.5`, campo vacío/no numérico) con System Admin+CSRF válido y estado inicial 14 días/200 MB, esperando 422, **sin mutación** (incluye toggles y `updated_by`). Probar también 403/419; los **campos ausentes** conservan fallback histórico 7/100, y `meli_writes_enabled` continúa falso.
- GREEN previsto y aislado: distinguir campo ausente frente a campo presente inválido; rechazar `FILTER_VALIDATE_INT === false` y dejar que el repositorio rechace `0`; cambiar sólo `SystemSettingsController.php` y la prueba focal. NO nuevos componentes, clases, rutas, SQL ni copy visual por esta incidencia.
- Importante: `TestDatabase::reset()` destruye la DB configurada. PHPUnit sólo en entorno efímero/CI o test DB comprobada. H8-B aún **NO** tiene RED, GREEN ni cambios de producto; `H8B_STARTED=NO`.
- H8-A sigue `AUTOMATED_GREEN=YES`, `BROWSER_CERTIFIED=NO`, `FULLY_CLOSED=NO`. Prompt Codex Luna browser V2 (adjunto) permanece próximo paso. No capturas ni mockup H8-A nuevo disponible en este turno; `APPROVED_MOCKUP_FIDELITY=NOT_VERIFIABLE` hasta evidencia aprobada.
- Checkpoint anterior: `5af2ff8291a55f20da78efd99493da2b0b250702`, CI `39704436927` **SUCCESS**; PR #19 `OPEN/DRAFT/NOT MERGED`. Continuar con leyes KISS, antirruido verificable, antiparches y UX/UI Pro/Human-First.

**Status:** LIVE RECOVERY ANCHOR  
**Audit date:** 2026-10-21
**Rule:** verify GitHub live before acting; this file is a recovery anchor, not a substitute for repository truth.

## Repository authority

```text
REPO=sierraglobalcompany-rgb/ErpMeli2.0
REMOTE=https://github.com/sierraglobalcompany-rgb/ErpMeli2.0.git
BRANCH=ui/design-master-v1-20261015
FUNCTIONAL_HEAD=5ce08784a55779886703ac6f2125224c764bcd1d
FUNCTIONAL_HEAD_CI=39701894315 SUCCESS
PR=19 OPEN / DRAFT / NOT MERGED
BASE=ui/operator-shell-v1-20261014
BASE_SHA=c6f8af7f446cb327a905ae2f9bd9ee424c2d421f
LOCAL_WORKTREE=UNVERIFIED_ON_THIS_PC; DO_NOT_ASSUME_PATH_OR_TREE
```

The branch may have a documentation-only descendant after this anchor is written. Always query live HEAD and inspect any delta before continuing. H7C-A GREEN `22384c2c...` remains an ancestor; current H8-A functional GREEN is listed in the checkpoint above.

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

## H7C-A Companies — historical implementation and audit

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

## H7C-B2 — permanent delete human consent, automated GREEN / browser PENDING

ROOT VERIFIED: a company admin could send a CSRF-valid POST for deleting an eligible disabled pristine account without explicit acknowledgement, and the backend returned 303 with deletion. RED asserts 422 and unchanged row.

H7C_B2_RED=3595880b6f4e6cdaeef6cc0ebff9f85c0e7cb723
H7C_B2_RED_CI=39072807611 EXPECTED FAILURE
RED_PHP_8_3=827 tests / 7028 assertions / 1 failure
RED_OBSERVED_HTTP=303
RED_EXPECTED_HTTP=422
H7C_B2_GREEN=d00eb26f37488d8552d2b1598d5b27dcb76967dd
H7C_B2_GREEN_CI=39073100289 SUCCESS
GREEN_PHP_8_3=828 tests / 7037 assertions / 0 failures
GREEN_PHP_8_4=828 tests / 7037 assertions / 0 failures
GREEN_PHP_8_5=828 tests / 7037 assertions / 0 failures
PHPSTAN=PASS

RED single test-only modified tests/Integration/MeliAccountLifecycleTest.php. GREEN bounded changes:
- app/Modules/Settings/MeliAccountController.php: requires confirm_permanent_delete exactly '1' after 404/409 history guards; missing/invalid returns 422 with rollback.
- app/Modules/Settings/views/meli-accounts.php: native closed-by-default delete disclosure, truthful local-only copy, required acknowledgement checkbox, original CSRF/action.
- tests/Integration/MeliAccountLifecycleTest.php: valid delete with explicit ack, invalid/missing ack and HTML tests.

No routes/schema/CSS/JS/OAuth/Work/Financial/Billing/retry/FIFO/429 redesign.

### H7C-B2 final browser certification — independently audited PASS

```text
H7C_B2_RED=3595880b6f4e6cdaeef6cc0ebff9f85c0e7cb723
H7C_B2_RED_CI=39072807611 EXPECTED FAILURE
H7C_B2_GREEN=d00eb26f37488d8552d2b1598d5b27dcb76967dd
H7C_B2_GREEN_CI=39073100289 SUCCESS
GREEN_QA=828 tests / 7037 assertions / 0 failures on PHP 8.3
PHP_8_4_8_5_AND_PHPSTAN=PASS
BROWSER_CERTIFIED_HEAD=21968d5ba0770f59db3e88348bb90d4ff6c506f6
BROWSER_HEAD_CI=39073464183 SUCCESS
H7C_B2_BROWSER_CERTIFIED=YES
H7C_B2_INDEPENDENT_AUDIT=PASS
H7C_B2_FULLY_CLOSED=YES
REAL_CHROME=155.0.8059.84
MOBILE=390x844 PASS
DESKTOP=1440x900 PASS
COMPANY_ADMIN_200=PASS
NONADMIN_403=PASS
KEYBOARD_ENTER_SPACE=PASS
REQUIRED_ACK_NATIVE_VALIDITY=PASS
BROWSER_NETWORK_MELI_CALLS=0
BROWSER_DESTRUCTIVE_POSTS=0
BUSINESS_DB_BEFORE_AFTER=IDENTICAL
GIT_CERTIFIER_WRITES=0
EVIDENCE_MANIFEST=26/26 VERIFIED
EVIDENCE_ZIP_SHA256=f9e95998b5cb0802bab5a7df271715552c9b55b039e3b10bac9583ee0abb8753
```

Independent ChatGPT audit of user uploads: the reported final ZIP and supplemental timestamp ZIP contain **byte-identical 27 members** (26 manifest entries + manifest) despite differing ZIP-level hashes. Five images, machine-readable DOM/keyboard/mobile/network, preserved DB and live GitHub CI checked. One raw console error refers to deliberate negative authorization 403 (`chrome-error://chromewebdata/`), not a JavaScript exception. Two favicon 404 are incidental. A standalone checksum sidecar was not uploaded; ChatGPT independently generated one and verified its contents against the reported final ZIP digest. No claim that Antigravity attached it.

## H7C-C — System clear debug (FULLY CLOSED; Codex V5 Chrome + independent audit)

Owner `SystemSettingsController::clearDebug()` + `views/system.php`; original `DebugMaintenance::clearDebug()`, route, schema, CSS, JS, memberships, integration logic untouched.

Observed root defect: with authenticated System Admin and valid CSRF a one-click `POST /settings/system/debug/clear` deleted recognized local debug files, no second acknowledgement. Test-only RED against disposable filesystem confirmed original HTTP 303 vs expected 422 and preserved files (the test cleans via finally).

```text
RED=b1fe1533db7809f689c398d111d4292474b5c31a
RED_CI=39079965535 EXPECTED FAILURE
RED_PHP_8_3=829 tests / 7040 assertions / exactly one targeted failure
GREEN=901b8652c825251f6189f6a11bea8c6ba7f198af
GREEN_CI=39080177081 SUCCESS
PHP_8_3=829 tests / 7049 assertions / 0 failures
PHP_8_4=829 tests / 7049 assertions / 0 failures
PHP_8_5=829 tests / 7049 assertions / 0 failures
PHPSTAN=PASS
GREEN_SCOPE=SystemSettingsController.php + views/system.php + DebugSettingsControllerTest.php
SERVER_CONFIRM_CLEAR_DEBUG=exactly '1'
MISSING_OR_INVALID_CONFIRM=422 (no debug deletion)
VALID_CONFIRM_WITH_VALID_CSRF=existing 303 success
SYSTEM_ADMIN_AUTH_PRESERVED=YES
INVALID_CSRF_419_PRESERVED=YES
DEBUG_SERVICE_MISSING_503_PRESERVED=YES
UNRELATED_LOGS_EXPORTS_PRESERVED=YES
REMOTE_MELI_CALLS=0
```

Human-First UI: native `<details data-system-destructive-action="clear-debug">` collapsed by default, factual scope and irreversibility copy, `required` checkbox `confirm_clear_debug=1`, original POST+CSRF inside. No generic modal or CSS/JS abstraction.

### V5 certification accepted independently — 2026-10-21

- Browser HEAD `970153033003547a16b2c968db6ba4d0264cdb03`, PR #19 OPEN/DRAFT/NOT MERGED; GitHub GREEN run `39080177081` and HEAD run `39080460052` SUCCESS, PHP 8.3/8.4/8.5; 829 tests, 7049 assertions.
- Three uploaded Codex files: `ERP_MELI_2_0_H7C_C_CODEX_LUNA_BROWSER_REPORT_V5_2026-10-21.md`, matching `ERP_MELI_2_0_H7C_C_CODEX_LUNA_BROWSER_EVIDENCE_V5_2026-10-21.zip` and `.zip.sha256`. ZIP SHA256 `c3db19754c9ea438032ddafe36827d32ab22d33e5300a04c209a42cfdf5c3f44`.
- Independent audit `ERP_MELI_2_0_H7C_C_AUDITORIA_INDEPENDIENTE_CIERRE_V5_2026-10-21.md`: 31/31 ZIP members, 30/30 SHA, CRC PASS, 5 images inspected, exact report comparison, 27 HTTP requests with only two synthetic login POST, 0 destructive POST/MELI/5xx/JS uncaught; 200/403 RBAC; native checkbox and keyboard; Apps focus/return; mobile no overflow.
- DB whitelist includes `system_settings`; before/after DB and synthetic debug + exports file-by-file SHA inventory identical. Chrome 155.0.8059.85 in isolated TEST only, no production or deletion action.
- V2 structure checker 108 PASS + 2 documentation-format false negatives (English `clean` string and full GREEN SHA absent from abbreviated report but present in two other signed manifest evidence files); resolved with manual evidence/GitHub corroboration. One RED expected/observed order typo in `03`: actual 303, expected 422 according to GitHub failure log. Do not rerun Codex for those.
- At the V5 evidence capture, H7C-C was CLOSED and H7C-D had only a read-only preflight. The current D1 result is recorded below. Do not restart A/B1/B2/C.

### Current exact NEXT — H8 Human-First read-only preflight

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
STOP_SAFE=YES

H7C-D2 evidence:

```text
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
TEST_DB_ISOLATED=YES (dedicated disposable MariaDB; erp_meli2_test)
BROWSER=Chrome 155.0.8059.85; desktop 1440x900; mobile 390x844
SYSTEM_ADMIN=HTTP 200; ordinary operator same-origin GET /system/users=403
MOBILE_NAV=5 slots; no horizontal overflow; consent checkbox and button clear of fixed nav
CONSENT=closed by default; required unchecked; checkValidity false→true→false; Enter/Space pass
APPS_KEYBOARD=4 Tab to launcher; Enter opens and focuses dialog; Tab stays inside; Escape closes and restores focus
BROWSER_PROHIBITED_POSTS=0 (only two synthetic login POSTs); external HTTP=0; application 5xx=0; uncaught JS=0
DB_BEFORE_AFTER=identical hashes/counts for users, company_users, companies, system_settings
FILESYSTEM_BEFORE_AFTER=identical 929-file inventory; filenames pseudonymized in evidence
CODE_OWNER_FILES_CHANGED=UserManagementController.php, views/users.php, UserManagementTest.php
D1_UNCHANGED=YES
EVIDENCE=H7C_D2_BROWSER_REPORT_2026-10-21.md + H7C_D2_BROWSER_EVIDENCE_2026-10-21.zip + .zip.sha256
```

The original browser and GitHub evidence independently pass; destructive revocation was tested in PHPUnit against disposable MariaDB (never submitted from Chrome). The later microcert confirms nonmutating filesystem under equivalent browser navigation on the same HEAD, not the chronology of the original 929-file interval. The remote PR head is authoritative. ChatGPT independently adjudicated the combined evidence and CLOSED D2 with the limitations documented below.

### H7C-D2 independent composite closure (2026-10-21)

- GitHub RED `56f99973ce9706657dde859aded519f685b2efec`, CI `39674454144`: exactly expected single 303-vs-422 failure. GREEN `715f4a9ae93452df75fc5d32eaa0514accf12ea7`, CI `39678811548` SUCCESS. Browser/docs HEAD `ec6afa00a8327a6b7e1aa1383dfa5869ee55ce0f`, CI `39681959280` SUCCESS; Linux PHP 8.3/8.4/8.5 840 tests/7318 assertions, PHPStan PASS. GREEN→HEAD docs-only.
- Full browser artifact `H7C_D2_BROWSER_EVIDENCE_2026-10-21.zip`, SHA256 `95e0076e410eb3c9256f59f9fb2ef017c9600a3f8eca487a3e2989614dbf84aa`, independently verified CRC, 41 members, 40/40 SHA, identical report and 5 screenshots. Two synthetic login POSTs; 200 System Admin/403 operator, native consent, keyboard and mobile PASS. DB before/after same; original 929-file filesystem hashes match, but original interval timing not proven.
- Supplemental `H7C_D2_FS_MICROCERT_V2_EVIDENCE_2026-10-21.zip`, SHA256 `a3d227b52aabc2287eaf6eb3287858432409b708e5d4c47ad20e16d72e7d2ffd`: CRC, 7 members, 6/6 SHA, same product HEAD; one-process monotonic timeline confirms BEFORE completed before first GET and AFTER after browser closed/network completed; all 12 isolated synthetic filesystem entries and hashes byte-identical, same storage root.
- **Keep microcert instrument result `BLOCKED`, not rewritten:** `POST /login` under manual redirect was marked `aborted`/opaqueredirect, no HTTP303 recorded; snapshot JSON SHA was calculated at packaging, not captured contemporaneously. Same run's protected GET `/system/users`=200 implies active DB-backed System Admin via unchanged `CompanyContext::assertSystemAdmin()`. Complementary original run already proved real 303 login/authorization. Overall composite closure does not claim the original 929-file interval was recovered or all microcert script gates passed.
- Independent signed-off reasoning report: `ERP_MELI_2_0_H7C_D2_AUDITORIA_INDEPENDIENTE_FINAL_COMPUESTA_2026-10-21.md` (conversation artifact). No product code changes at closure, no destructive Chrome POST or provider calls; no arbitrary deletion of retained staging, storage/private/runtime.json, installed.lock, 919 original exports or user files.
- H7C-D2 and entire H7C now CLOSED in isolated TEST. Next plan phase **H8** is read-only audit only until scope/owner verified; H8 implementation NOT started. PR #19 remains OPEN/DRAFT/NOT MERGED; no production deploy or writes.

### Independent evidence audit and closure (2026-10-21)

- Independently audited `H7C_D1_BROWSER_REPORT_2026-10-21.md`, `H7C_D1_BROWSER_EVIDENCE_2026-10-21.zip`, `.zip.sha256`. Independent report: `ERP_MELI_2_0_H7C_D1_AUDITORIA_INDEPENDIENTE_CIERRE_2026-10-21.md` (conversation artifact).
- Verified ZIP SHA-256 `b56332f1e88194298dc1b1761eda8bbebe84349c599ab31829955e61bd1a4cef`; all 23 members CRC PASS; 22/22 SHA manifest hashes PASS; report ZIP-vs-external exact.
- Audited three Chrome screenshots and evidence for System Admin 200 / restricted 403, native required consent, keyboard and mobile geometry; observed no destructive POST or MELI call, table-hash DB snapshots and storage inventory identical before/after. Server regressions validated by PHP unit tests and GitHub Linux CI, not browser destructive POST.
- Real RED CI `39661909819` one expected failure; GREEN `39666739964` and certified browser HEAD `39669622138` SUCCESS (PHP 8.3/8.4/8.5: 834 tests/7156 assertions, PHPStan PASS). GREEN→HEAD only two Markdown changes. No additional product code changed for closure.
- Limitations recorded: DB snapshots store row counts/hashes rather than raw rows, storage only six .gitkeep markers, 4 Chrome VERBOSE autocomplete notices, two login favicon404s and expected 403 console error. No production certification/merge.
- D1 is independently CLOSED; D2 had not started at the time of this D1 audit.

Chrome 155.0.8059.85 was observed in isolated TEST at desktop 1440×900 and mobile 390×844. Admin route 200, restricted user 403; native required acknowledgement, keyboard, Apps focus return, mobile geometry, and unchanged DB/filesystem snapshots passed. No browser revocation or real-provider POST occurred. Full evidence report: H7C_D1_BROWSER_REPORT_2026-10-21.md. ZIP: H7C_D1_BROWSER_EVIDENCE_2026-10-21.zip; SHA-256 b56332f1e88194298dc1b1761eda8bbebe84349c599ab31829955e61bd1a4cef.

Codex performed the real browser observation; ChatGPT independently checked the D1 report, screenshots, ZIP CRC and member hashes, matching report, database/filesystem snapshots, and live RED/GREEN/HEAD GitHub logs. D1 remains FULLY CLOSED in isolated TEST. H7C-D2 is now automated GREEN and browser-observed PASS; its evidence package awaits independent audit. No browser revocation POST or real-provider POST occurred. Do not reopen A/B1/B2/C/D1, merge PR #19, deploy, or broaden scope.
### H8-A current owner and gate (2026-10-21)

- Live RED `994744c819aa781b9c287e995bdac50617f5253a`, CI `39700960434` single expected failure on PHP 8.3/8.4/8.5: unconfirmed `action=clear` previously returned 303 instead of 422.
- First GREEN `042e52de4169a84ad96ee1a917123c12186c6b59`, CI `39701598048` SUCCESS; final functional GREEN `5ce08784a55779886703ac6f2125224c764bcd1d`, CI `39701894315` SUCCESS: 842 tests/7371 assertions per PHP 8.3/8.4/8.5 and PHPStan PASS.
- Exact string `confirm_clear_meli_credentials='1'` required *before opening* `install.guard`; malformed/missing ack 422, `runtime.json`/`installed.lock`/`install.guard` identical. `<details>` + unchecked required checkbox, preserved CSRF and original valid 303, 403/419/409/503. Three existing owner files touched; no architecture changes.
- Sensitive fixture warning: existing `MeliAppSettingsTest` writes checkout-local `storage/private/runtime.json` and `installed.lock` during setup; never run it on a shared user's checkout. GitHub QA ran in ephemeral CI. For real Chrome, create isolated disposable clone, MariaDB, synthetic credentials/storage; no destructive browser POST or real MELI calls.
- `H8A_BROWSER_CERTIFIED=NO`, `H8A_FULLY_CLOSED=NO`, `H8B_STARTED=NO`; `NEXT=H8-A APP CREDENTIAL CLEAR REAL-BROWSER CERTIFICATION ONLY`. ChatGPT independently audits Codex browser report+ZIP+SHA before closure. No merge/deploy.

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
