# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=H7A GLOBAL ADMIN SHARED SHELL / AUTOMATED GREEN / REAL-BROWSER CERTIFICATION PENDING / NOT CLOSED / H7B NOT STARTED / STOP-SAFE**

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

Read before continuing:

```text
AGENTS.md
docs/ERP2_AUTHORITY.md
docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md
design-system/erp-meli-2-0/MASTER.md
docs/UX_HUMAN_FIRST_MASTER_AUDIT_AND_IMPROVEMENT_PLAN_2026-10-16.md
```

Historical ERP1 429 causality remains `NOT_PROVEN`. Do not encode guesses about FIFO, batching, gRPC, waits, retries, pacing or transport as ERP2 architecture without fresh evidence.

## Certified Human-First history

```text
H1_FIX1_SALE_DETAIL=GREEN
H2A_SKIP_TO_MAIN=GREEN
H2B_TYPOGRAPHY_TOUCH=GREEN
H3_DASHBOARD=GREEN
H4_SALES_LIST=FULLY_CLOSED
H5_CATALOG_INVENTORY=FULLY_CLOSED
H6A_BILLING_DOCUMENT_LABELS=FULLY_CLOSED
H6B_BILLING_TECHNICAL_CURSOR=FULLY_CLOSED
H6C_BILLING_SELECTED_ACCOUNT_IDENTITY=FULLY_CLOSED
H6D_BILLING_HISTORY_ACCOUNT_IDENTITY=FULLY_CLOSED
H6E_BILLING_DISABLED_HISTORY_ACCOUNT_IDENTITY=FULLY_CLOSED
H6F_BILLING_ZERO_CONNECTED_EMPTY_STATE=FULLY_CLOSED
BILLING_HUMAN_FIRST_CLOSURE=YES
BILLING_RESIDUAL_PREFLIGHT=PASS
```

Billing final durable closure before H7A:

```text
BILLING_CLOSURE_CHECKPOINT=45d9183ecb05ef3ddbcd76eb424914572f57b603
H6F_ROOT_FIX_GREEN=cb628bfd144447cc4939c48e8ff58fc10ef11e97
H6F_ROOT_FIX_CI=39001247996
PHPUNIT_AT_H6F=820 tests / 6869 assertions / 0 failures
H6F_FINAL_BROWSER=PASS
H6F_DESKTOP_OVERLAP=0 px
H6F_MOBILE_OVERLAP=0 px
H6F_DB_BEFORE_AFTER=IDENTICAL
H6G_CREATED=NO
```

## H7A — Global Admin shared shell

### Problem proved before implementation

Global administration pages used a second `simple-page/simple-nav` navigation model:

```text
/system/companies
/system/users
/settings/system
/settings/mercadolibre
```

The approved Human-First direction is one product shell, without inventing new owners or changing permissions.

Read-only preflight proved:

```text
navigation.php can conceptually represent company=null
pure System Admin with no active company is a valid state
CompanyContext::memberships() does not require an active company
/settings/mercadolibre already supports System Admin with no company
old shared shell incorrectly exposed company-bound destinations without active company
companies/users views use $companies for global managed companies, so navigation context must not reuse that variable implicitly
```

Decision:

```text
H7A_DECISION=MERGE_GLOBAL_ADMIN_INTO_SHARED_SHELL
BACKEND_REDESIGN=NO
TENANCY_CHANGE=NO
PERMISSION_CHANGE=NO
ROUTE_CHANGE=NO
```

### H7A RED — confirmed

```text
H7A_RED=2793fe15a7d3ee45af3332b136859cb843d2be53
H7A_RED_CI=39004765237
PHPSTAN=PASS
PHP_8_3=822 tests / 6874 assertions / 2 failures
FAILURES=exactly the two H7A shell contracts
ROOT_CAUSE=global pages still rendered simple-page/simple-nav instead of app-shell-chrome
```

RED scenarios:

1. System Admin with no active company:
   - shared shell required;
   - `Sin empresa activa`;
   - role `Administrador del sistema`;
   - no actionable `/dashboard`, `/sales`, `/catalog`, `/billing`;
   - exact five mobile slots with Inicio/Operación/Productos disabled;
   - global Admin/MELI destinations remain actionable.
2. System Admin with active company:
   - shared shell required;
   - company-bound destinations remain actionable;
   - global Admin destinations remain actionable;
   - exact five mobile slots preserved.

### H7A GREEN — automated certified

Canonical GREEN history was compacted after verification so the durable branch contains one RED commit plus one GREEN commit for H7A.

```text
H7A_GREEN=f8fd6a717e526acc1c79863bd7fb948bfd351817
H7A_GREEN_CI=39006254808
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
PHPSTAN=PASS
PHPUNIT=822 tests / 6967 assertions / 0 failures
APP_ENV=test
REAL_MELI_HTTP=0
```

Canonical RED→GREEN diff:

```text
COMMITS=1
FILES=12
PRODUCTION_VIEWS=5
TEST_FILES=7
CONTROLLERS_CHANGED=NO
ROUTES_CHANGED=NO
SCHEMA_CHANGED=NO
CSS_CHANGED=NO
JS_CHANGED=NO
DEPENDENCIES_CHANGED=NO
WORK_CHANGED=NO
FINANCIAL_CHANGED=NO
429_ARCHITECTURE_CHANGED=NO
```

Production views changed:

```text
app/Core/Http/views/navigation.php
app/Core/Http/views/companies.php
app/Core/Http/views/users.php
app/Modules/Settings/views/system.php
app/Modules/Settings/views/mercadolibre.php
```

Tests changed:

```text
tests/Integration/GlobalAdminShellHumanFirstUiTest.php
tests/Integration/CompanyDesignMasterUiTest.php
tests/Integration/UserDesignMasterUiTest.php
tests/Integration/SystemSettingsDesignMasterUiTest.php
tests/Integration/MeliAppSettingsDesignMasterUiTest.php
tests/Integration/MeliAppSettingsTest.php
tests/Integration/OperatorFoundationTest.php
```

### H7A implemented behavior

No-active-company System Admin:

```text
SHARED_APP_SHELL=YES
PRIMARY_CONTEXT=Sin empresa activa
ROLE_LABEL=Administrador del sistema
DESKTOP_COMPANY_BOUND_LINKS=ABSENT
MOBILE_NAV_SLOTS=5
MOBILE_INICIO=DISABLED NON-LINK
MOBILE_OPERACION=DISABLED NON-LINK
MOBILE_APPS=ACTIONABLE
MOBILE_PRODUCTOS=DISABLED NON-LINK
MOBILE_MELI=/settings/mercadolibre
GLOBAL_ADMIN_LINKS=ACTIONABLE
GLOBAL_MELI_DESKTOP_DUPLICATE=REMOVED
```

System Admin with active company:

```text
SHARED_APP_SHELL=YES
COMPANY_BOUND_LINKS=ACTIONABLE
GLOBAL_ADMIN_LINKS=ACTIONABLE
MOBILE_NAV_SLOTS=5
COMPANY_MELI_OWNER=/mercadolibre/accounts when company role is admin
GLOBAL_MELI_OWNER=/settings/mercadolibre for System Admin
```

Shared-shell ownership:

```text
SKIP_LINK=PRESERVED
LOGOUT_OWNER=SHARED_SHELL
DUPLICATE_VIEW_LOGOUT=REMOVED
APPS_LAUNCHER=PRESERVED
OLD_SIMPLE_NAV=REMOVED_FROM_H7A_GLOBAL_PAGES
```

### H7A non-goals / frozen scope

```text
H7B_USERS_PROGRESSIVE_DISCLOSURE=NOT_STARTED
H7C_DESTRUCTIVE_ACTIONS=NOT_STARTED
NO_GENERIC_MODAL_SYSTEM
NO_PERMISSION_MODEL_CHANGE
NO_COMPANY_SELECTOR_REDESIGN
NO_MELI_OWNER_MERGE
NO_BACKEND_QUERY_ARCHITECTURE_CHANGE
```

## Current disposition

```text
H7A_RED=CONFIRMED
H7A_AUTOMATED_GREEN=YES
H7A_BROWSER=PENDING
H7A_FULLY_CLOSED=NO
H7B_STARTED=NO
H7C_STARTED=NO
PR_MERGED=NO
STOP_SAFE=YES
```

## Exact resume point

```text
NEXT=H7A REAL-BROWSER CERTIFICATION ONLY
CANONICAL_GREEN=f8fd6a717e526acc1c79863bd7fb948bfd351817
CANONICAL_GREEN_CI=39006254808
```

Browser certification must cover two factual scenarios:

### A. System Admin / no active company

Routes:

```text
/system/companies
/system/users
/settings/system
/settings/mercadolibre
```

Required desktop 1440x900 + mobile 390x844 gates:

```text
HTTP_200=all routes
SHARED_APP_SHELL=YES
OLD_SIMPLE_NAV=ABSENT
CONTEXT=Sin empresa activa
ROLE=Administrador del sistema
DESKTOP_COMPANY_BOUND_LINKS=ABSENT
GLOBAL_ADMIN_LINKS=ACTIONABLE
GLOBAL_MELI_DESKTOP_DUPLICATE=NO
MOBILE_NAV=exactly 5 slots
INICIO/OPERACION/PRODUCTOS=disabled non-links
APPS=usable
MELI_5TH_SLOT=/settings/mercadolibre
APPS_LAUNCHER=no company-bound sales/catalog/billing entries
SKIP_LINK=#main-content works
DUPLICATE_LOGOUT=NO
PAGE_HORIZONTAL_OVERFLOW=NO
FIXED_NAV_OBSTRUCTION=NO
```

### B. System Admin / active company

Same routes and viewports. Required:

```text
HTTP_200=all routes
SHARED_APP_SHELL=YES
OLD_SIMPLE_NAV=ABSENT
Sin empresa activa=ABSENT
/dashboard=/sales=/catalog=/billing actionable
GLOBAL_ADMIN_LINKS=ACTIONABLE
MOBILE_NAV=exactly 5 slots active
APPS_LAUNCHER=usable
ESC_CLOSE=PASS
FOCUS_RETURN=PASS
SKIP_LINK=PASS
GLOBAL_MELI_DESKTOP_DUPLICATE=NO
PAGE_HORIZONTAL_OVERFLOW=NO
FIXED_NAV_OBSTRUCTION=NO
```

All browser certification:

```text
DO_NOT_SUBMIT_FORMS
DB_BEFORE_AFTER=IDENTICAL
BUSINESS_SIDE_EFFECTS=0
REAL_MELI_NETWORK_CALLS=0
APPLICATION_5XX=0
CONSOLE_APP_ERRORS=0
UNCAUGHT_JS=0
WORKING_TREE_BEFORE_AFTER=CLEAN
CERTIFIER_CODE_CHANGES=NO
CERTIFIER_TEST_CHANGES=NO
CERTIFIER_DOC_CHANGES=NO
CERTIFIER_COMMITS=NO
```

First real product gate failure => evidence + root cause + STOP. Certifier does not repair product code.

If browser PASS is independently audited, mark H7A FULLY CLOSED and only then start H7B.

## Frozen / prohibited

No broad redesign, new frontend framework, duplicate dataset/route/owner, Hostinger, cron, real MELI mutation, PR merge, Financial/Work/429 redesign, retry storms, child Work explosion, patch-chain CSS, UNKNOWN→0, or speculative ERP1 429 architecture.
