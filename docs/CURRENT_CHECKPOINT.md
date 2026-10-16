# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=H2B2 SHARED TYPOGRAPHY/TOUCH GREEN / NEXT=H3 DASHBOARD HUMAN-FIRST / STOP-SAFE**

Autoridad operativa de recuperación. Retomar desde `RESUME_EXACTLY_FROM`.

## Repository authority

```text
DATE=2026-10-16
REPO=sierraglobalcompany-rgb/ErpMeli2.0
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
H1_FIX1_STARTING_HEAD=ce11219158470c7bc122ba56680af7ff144cfbba
CURRENT_PRODUCT_CANDIDATE_HEAD=cbc32e7b8f2ad534a051946cb8a644d62cf84f75
EXACT_PRODUCT_CI_RUN=38962684020
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

## H2B1 — typography and touch audit closed

```text
H2B1_STARTING_HEAD=891bbddf77cc3a8062b04a50e101827dceaa8fb6
H2B1_PRODUCT_HEAD_UNCHANGED=3b173b9d72a4383b1db879b59f8132f4fa735b4a
H2B1_STARTING_CHECKPOINT_CI=38942644768 completed / success
H2B1_RESULT=DIAGNOSED_FIX_REQUIRED
H2B1_MEASURED_VIEWPORTS=390x844 / 430x932 / 768x1024
H2B1_DESKTOP_REFERENCE=1440x900
H2B1_FINDINGS=H2B1-01 / H2B1-02 / H2B1-03
H2B2_STARTED=NO
```

Audit-only: five rendered routes at four sizes, plus Apps at 390/430.
The optional company selector redirected to Dashboard with the single-company fixture;
its multi-company controls are not certified. Product, tests and CSS unchanged; no full QA rerun.
Starting checkpoint CI success was verified at its exact SHA.

- H2B1-01 MINOR: shared body/form text computes to 15px / 23.25px; header copy already 16.32px.
- H2B1-02 MINOR: common controls compute to 42–42.85px high; Apps close 38x38.
  Native date fields already 44.85px; use rendered geometry, not source assumptions.
- H2B1-03 MAJOR usability: critical five-slot navigation labels are 8.5px at 390/430 and 9px at 768.
  Slots remain >=44x44, exact, unclipped and without page horizontal overflow.

205 visible target exposures across 22 page/Apps states: 103 preferred passes,
102 below44 but >=24 in both dimensions, zero below24. Mobile/tablet subset:
140 exposures / 101 passes / 39 preferred misses. Desktop reference: 65 / 2 / 63.
These are repeated exposures, not unique controls. 15px text and targets below44 do not
by themselves establish WCAG AA violations; no complete WCAG certification is claimed.
Form text below16 is a practical readability/zoom risk; iOS/Safari was not tested.
Apps titles12/subtitles10 and eyebrow10 remain contextual exception candidates.
Apps open -> dialog initial focus -> Escape -> trigger focus return passed at both mobile sizes.
Safe-area computes to zero in browser emulation; real nonzero device insets remain untested.

Future correction belongs in existing shared CSS owners: typography/form targets,
explicit Apps close dimensions and existing five-slot nav rules. No per-page patches,
new breakpoints, !important or JS sizing. Two shared responsibilities explain most misses,
but nav and close have explicit component values; do not claim two declarations fix everything.
Increasing nav text without checking wrapping can exceed the current slot width.

Evidence outside Git: `C:\codex\evidence\erp-meli2\human-first\h2b1\`
contains source inventory, DOM measurements/summary, detailed report, runtime proof,
mobile/tablet/desktop screenshots and SHA-256 manifest. Server and disposable DB removed,
secrets/runtime files cleared, temporary tab closed and viewport restored.
No unrelated dangerous defect, captured application 5xx or browser console error found.

## H2B2 — shared readability and touch closed

```text
H2B2_STARTING_HEAD=c5f180ee902fc8bf822f1018825b5083d4f2281c
H2B2_PRODUCT_HEAD=cbc32e7b8f2ad534a051946cb8a644d62cf84f75
H2B2_PRODUCT_CI=38962684020 completed / success; PHP 8.3 / 8.4 / 8.5 PASS
H2B2_LOCAL_QA=PASS; lint / PHPStan / 810 tests / 6787 assertions / 1 skipped / 0 errors / 0 failures
H2B1_01=CLOSED
H2B1_02=CLOSED for audited shared owners; row-actions remains DEFER
H2B1_03=CLOSED
BODY_FONT=16px
SHARED_TOUCH_FLOOR=44px
APPS_CLOSE=44x44
MOBILE_NAV_FONT=12px
MOBILE_NAV_SLOTS=5 EXACT
390=PASS
430=PASS
768=PASS
1440_REFERENCE=PASS
H2B3_STARTED=NO
H3_STARTED=NO
STOP_SAFE=YES
```

Three focused RED tests failed on existing body15, input floor42 and competing nav font rules.
Minimal GREEN replaces existing body/form/button/close values, removes the narrow8.5px
nav override and raises the existing nav height68->80 for readable12px labels.
Only app.css and DesignMasterResponsiveContractTest.php changed in the product commit.
Focused GREEN: 3 tests/18 assertions; full responsive class14 tests/93 assertions.
No views, JS, backend, route, dependency, breakpoint or row-action changes.

Real-browser certification followed exact product CI success: five routes at each of
390x844,430x932,768x1024 and1440x900; Apps at390/430.
Body/form/labels16px; body line-height24.8px (1.55); shared controls>=44px,
typical44.40px, native dates46.40px, Apps close44x44.
Five nav targets remain exact and>=44x44; labels12px. Mercado Libre wraps to two
unclipped lines at390/430, one at768. No label/icon collision or page horizontal overflow.
Apps cards remain unclipped, central orb48x48 dominant, Escape/focus return PASS.
H2A smoke390/1440: first Tab exposes skip link, Enter focuses MAIN#main-content,
next Tab reaches from inside main. Desktop shell/sidebar intact.

Mobile/tablet:140 visible target exposures, all>=44x44. Desktop reference retains45
exposures of existing sidebar links40px high; those links are outside audited form owners.
No blanket WCAG certification is claimed; no iOS/Safari or nonzero safe-area device test.
Micro labels were preserved. Row-actions/textarea not rendered by the minimal fixture;
textarea shares the tested CSS owner but is not independently browser-certified.

No business form submitted; Work/API/Billing rows remained zero before/after browser QA.
No captured application5xx or browser console errors. Disposable server/container removed,
runtime files and secrets cleared, temporary tab closed and viewport restored.
Evidence: `C:\codex\evidence\erp-meli2\human-first\h2b2\` contains RED/GREEN/full QA,
exact-head CI, actual patch, browser-after JSON/summary, screenshots, DB before/after,
review/dispositions, report and SHA-256 manifest. H3 is next per the existing plan, not started.

## Next / frozen

```text
NEXT=H3 DASHBOARD HUMAN-FIRST
H3_STARTED=NO
H3..H9=FROZEN
UI-DM10-B2C2 INVENTORY MISSING-VS-ZERO=MOVED INTO H5
RESUME_EXACTLY_FROM=H3 DASHBOARD HUMAN-FIRST WHEN AUTHORIZED
STOP_SAFE=YES
```

Follow the existing improvement plan one microblock at a time. H1 does not certify the remaining ERP pages or the final product.
No broad redesign, backend-owner redesign, frontend framework, fake data/routes, Hostinger, cron, real MELI writes, F15/F16 or merge of PR #19.
Keep durable 429 defer/cooldown, no inline retries, company/account scoping, Financial UNKNOWN/CONFLICT and Billing cursor semantics.
