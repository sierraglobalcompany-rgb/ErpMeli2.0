# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=H2A SKIP-TO-MAIN GREEN / H2B NEXT BUT NOT STARTED / STOP-SAFE**

Autoridad operativa de recuperación. Retomar desde `RESUME_EXACTLY_FROM`.

## Repository authority

```text
DATE=2026-10-16
REPO=sierraglobalcompany-rgb/ErpMeli2.0
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
H1_FIX1_STARTING_HEAD=ce11219158470c7bc122ba56680af7ff144cfbba
CURRENT_PRODUCT_CANDIDATE_HEAD=3b173b9d72a4383b1db879b59f8132f4fa735b4a
EXACT_PRODUCT_CI_RUN=38941700937
EXACT_PRODUCT_CI=completed / success
QA_PHP_8_3=PASS
QA_PHP_8_4=PASS
QA_PHP_8_5=PASS
REMOTE_WRITES=OFF
REAL_MELI_HTTP=0
HOSTINGER_UI_DEPLOYED=NO
```

## Required governance

Read `AGENTS.md`, `docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md`,
`design-system/erp-meli-2-0/MASTER.md`, any relevant page override, and
`docs/UX_HUMAN_FIRST_MASTER_AUDIT_AND_IMPROVEMENT_PLAN_2026-10-16.md`.
Sale Detail has no page override. Truth and authorization outrank presentation.

## H1-FIX1 — closed

Certified H1 product: `1c1abcadec033231bc662507e2eac3b9c6869caf`; CI `38933239893` passed PHP 8.3/8.4/8.5.

Root cause: mobile repeated the hero facts in `.sales-summary` and placed products before financial meaning.
The correction adds only two rules inside the existing `max-width: 760px` breakpoint:

```css
.sales-summary { display: none; }
.financial-human-summary { order: -1; }
```

`detail.php`, backend owners, routes, JS, money semantics and desktop CSS remain unchanged.
One focused test obtains `/assets/app.css` through the application and checks both rules within that breakpoint.
RED failed only because the responsive hierarchy was absent; minimal GREEN passed.

```text
LOCAL_LINT=PASS
LOCAL_PHPSTAN=PASS
LOCAL_PHPUNIT=805 tests / 6744 assertions / 0 errors / 0 failures / 1 skipped
COMPOSER_QA=PASS; runtime-only COMPOSER_PROCESS_TIMEOUT=0
H1_BROWSER_GREEN=YES
H1_001=CLOSED
KNOWN_FEE_DESKTOP_1440x900=PASS
KNOWN_FEE_MOBILE_390x844=PASS
CONFLICT_DESKTOP_1440x900=PASS
CONFLICT_MOBILE_390x844=PASS
```

Known fee remains exactly `9.12500000`; absent facts use human copy and remain `UNKNOWN` in technical disclosure.
Conflict prominently explains incompatible currencies; raw `CONFLICT` is secondary and never substituted by zero.
Both scenarios use separate clean disposable DB states. Native disclosure opens/closes by Enter/Space.
No empty Billing table chrome, primary page horizontal overflow, application console errors or captured application 5xx.
Products remain available below Finance on mobile. Normal vertical scrolling to technical detail is accepted.
Work/API/Billing and selected order/item facts match their pre-read baselines.
Browser scores are audit heuristics, not a timed human-participant study.

Evidence outside Git:

```text
C:\codex\evidence\erp-meli2\human-first\h1-fix1\
red.txt
green-focused.txt
full-local-qa.txt
implementation.txt
browser-summary.txt
known\fixture-proof.txt / side-effects.txt / browser-evidence.txt / screenshots
conflict\fixture-proof.txt / side-effects.txt / browser-evidence.txt / screenshots
```

Temporary QA containers/DBs, local server and fixture helper were removed; temporary runtime files and secrets cleared.

## H2A — skip-to-main closed

```text
H2A_STARTING_HEAD=59474304379d2ef6f9434ccd5410ce18bead3e64
H2A_PRODUCT_HEAD=3b173b9d72a4383b1db879b59f8132f4fa735b4a
H2A_PRODUCT_CI=38941700937
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
LOCAL_QA=lint PASS / PHPStan PASS / 807 tests / 6769 assertions / 1 skipped / 0 errors / 0 failures
DESKTOP_KEYBOARD=PASS
MOBILE_KEYBOARD=PASS
H2A_GREEN=YES
```

One native skip link is centralized before persistent chrome in `navigation.php`.
Seven existing shared-shell views now expose one `<main id="main-content" tabindex="-1">`:
Dashboard, Sales list/detail, Catalog, Billing, MELI accounts and the conditional shared-shell company selector.
Global administration, login and install shells were not changed. No JavaScript or backend changes.
Two focused RED failures proved the missing bypass contract; all 11 responsive contracts passed after minimal GREEN.

Real browser on `/dashboard` and `/sales`, each at 1440x900 and 390x844:
first Tab exposes the link; Enter focuses the main landmark; next Tab reaches `from` or `order_id` inside main.
One link/main/target per page, clear focus above chrome, no layout gap or page horizontal overflow.
Mobile five-slot navigation and Apps open/ESC/focus return remained correct. No app console errors or captured 5xx.
H1 visual-vs-DOM order audit: NON_BLOCKING / DEFER; product records add no interactive controls before the technical disclosure; no blocking focus defect demonstrated. H1 was not redesigned or fully recertified in H2A.

Evidence: `C:\codex\evidence\erp-meli2\human-first\h2a\` contains source inventory, RED/GREEN/full QA, browser focus states, screenshots and SHA-256 manifest.
Disposable QA/browser DB containers and local PHP were removed; runtime files/secrets cleared, temporary tabs closed and viewport restored.

## Next / frozen

```text
NEXT=H2B MOBILE TYPOGRAPHY / TOUCH TARGET BROWSER AUDIT
H2B_STARTED=NO
H3..H9=FROZEN
UI-DM10-B2C2 INVENTORY MISSING-VS-ZERO=MOVED INTO H5
RESUME_EXACTLY_FROM=H2B AUDIT WHEN AUTHORIZED
STOP_SAFE=YES
```

Follow the existing improvement plan one microblock at a time. H1 does not certify the remaining ERP pages or the final product.
No broad redesign, backend-owner redesign, frontend framework, fake data/routes, Hostinger, cron, real MELI writes, F15/F16 or merge of PR #19.
Keep durable 429 defer/cooldown, no inline retries, company/account scoping, Financial UNKNOWN/CONFLICT and Billing cursor semantics.
