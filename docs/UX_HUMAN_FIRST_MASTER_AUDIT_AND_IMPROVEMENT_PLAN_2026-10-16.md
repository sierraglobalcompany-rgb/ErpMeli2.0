# ERP MELI 2.0 — HUMAN-FIRST MASTER AUDIT + IMPROVEMENT PLAN

**Date:** 2026-10-16  
**Branch:** `ui/design-master-v1-20261015`  
**PR:** #19 — DRAFT / NOT MERGED  
**Master law:** `docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md`  
**Project design authority:** `design-system/erp-meli-2-0/MASTER.md`  
**Product candidate audited:** `d48b4464200b321dc309fbebcd56021f08709ba4`  
**Audit mode:** READ/AUDIT FIRST — no broad production changes during this audit

---

# 1. EXECUTIVE VERDICT

The current ERP is **functionally strong, fact-safe, responsive and structurally KISS**, but it is **not yet Human-First complete**.

The dominant remaining problem is not backend correctness. It is presentation:

```text
TECHNICALLY CORRECT
BUT
SOME SCREENS STILL SPEAK TOO MUCH LIKE THE SYSTEM
```

The product must finish the transition from:

```text
DATA MODEL → SCREEN
```

to:

```text
TRUTH → HUMAN MEANING → DECISION → ACTION → TECHNICAL DETAIL
```

No evidence supports a broad backend redesign.

No evidence supports a frontend-framework migration.

The correct plan is a sequence of focused presentation/UX microblocks on top of the existing owners.

---

# 2. WHAT REMAINS STRONG — KEEP

## Architecture / truth

```text
KEEP current backend owners
KEEP company/account scoping
KEEP Financial UNKNOWN/CONFLICT semantics
KEEP Billing Work/cursor semantics
KEEP durable 429 handling
KEEP no-inline-retry rules
KEEP REMOTE_WRITES=OFF
KEEP local read-only dashboard/detail behavior
KEEP exact money handling
```

## UI foundation

```text
KEEP single shared app.css design family
KEEP vanilla app.js
KEEP desktop sidebar + topbar
KEEP five-slot mobile navigation
KEEP Apps launcher
KEEP safe-area handling
KEEP table-to-card responsive transformation
KEEP role-aware destinations
KEEP no fake routes/capabilities
```

## Browser evidence already certified

```text
B1 shell desktop/mobile GREEN
B2A sales/catalog/billing desktop GREEN
B2B known-fee Sale Detail GREEN
B2C1 financial CONFLICT GREEN
```

The Human-First audit does not invalidate that evidence. It adds a comprehension/presentation quality gate.

---

# 3. PROVISIONAL SCORECARD

This score is based on current source + existing real-browser evidence. It is not the final release score because HF1 browser QA and later Human-First remediations remain open.

| Area | Score / 5 | Weight | Weighted |
|---|---:|---:|---:|
| Truth | 5.0 | 20 | 20.0 |
| Human comprehension | 3.2 | 20 | 12.8 |
| Accessibility | 3.8 | 15 | 11.4 |
| Information architecture | 3.5 | 10 | 7.0 |
| Responsive | 4.5 | 10 | 9.0 |
| Interaction | 4.0 | 10 | 8.0 |
| Visual consistency | 4.2 | 5 | 4.2 |
| Forms / feedback | 3.8 | 5 | 3.8 |
| Data presentation | 3.5 | 3 | 2.1 |
| Polish | 3.7 | 2 | 1.5 |

```text
PROVISIONAL_TOTAL≈80/100
RULING=GOOD FOUNDATION / HUMAN-FIRST IMPROVEMENT REQUIRED BEFORE ERP COMPLETE
```

There is no proven backend blocker.

There are multiple MAJOR UX findings.

---

# 4. PASS 1 — TRUTH / PERMISSIONS

```text
PASS_STATUS=GREEN
```

## Evidence

Current completeness audit already proves one human owner per major capability and deliberately keeps Work/runtime/webhooks/API/financial internals technical-only or merged into existing surfaces.

Current navigation is role-aware:

```text
company admin -> /mercadolibre/accounts
system admin -> /settings/mercadolibre
limited roles -> no fake MELI route
system admin links only when system admin
```

Financial presentation preserves:

```text
UNKNOWN
CONFLICT
currency separation
missing != zero
```

## Findings

No truth-owner duplication requiring redesign was found.

## Decision

```text
KEEP backend architecture
KEEP capability boundaries
DO NOT create new modules merely for presentation
```

---

# 5. PASS 2 — HUMAN COMPREHENSION

```text
PASS_STATUS=RED
```

This is the largest remaining gap.

## UX-001 — Sale Detail Human-First implementation is not browser-certified yet

```text
SEVERITY=MAJOR GATE
DISPOSITION=VERIFY
```

Current source already translates:

```text
paid -> Pagada
CONFLICT -> Requiere revisión
UNKNOWN -> Sin información suficiente
```

and moves raw reconciliation/readiness/post-sale state behind `<details>`.

This is directionally correct.

But final browser comprehension has not been certified after HF1 implementation.

Required next:

```text
normal known-fee sale + conflict sale
1440x900 + 390x844
5–10 second comprehension gate
```

## UX-002 — Dashboard still speaks architecture language

```text
SEVERITY=MAJOR
PAGE=/dashboard
```

Current primary copy includes:

```text
Vista factual de la operación local
Rentabilidad = UNKNOWN
owner agregado autorizado
```

Expected:

```text
Rentabilidad
No disponible
Aún no hay información suficiente para calcularla con seguridad.
```

Raw technical explanation may exist in help/detail.

## UX-003 — Dashboard recent-sales states are raw

Current table outputs order status directly.

Expected Human-First status mapping consistent with Sale Detail.

## UX-004 — Sales List mixes normal work and technical audit language

```text
SEVERITY=MAJOR
```

The page introduction makes local storage/audits part of the first mental model.

Audit section exposes:

```text
CAPTURING
REPAIRING
CONFIRMING
VALID
ATTENTION
UNAVAILABLE
```

as primary copy.

Expected:

```text
Primary job = Buscar/revisar ventas
Historical audit = secondary/advanced
Human state first
Raw audit state optional technical detail
```

## UX-005 — Catalog has ID-centric primary workflow

```text
SEVERITY=MAJOR
```

`Seleccionar producto por ID` is too prominent for normal human use.

Expected:

```text
click/select product from list as primary
ID lookup secondary/advanced
```

Do not invent a new search backend unless justified.

## UX-006 — Billing exposes implementation vocabulary

```text
SEVERITY=MAJOR
```

Primary UI currently exposes:

```text
AAAA-MM-01
BILL
CREDIT_NOTE
Progreso persistido
Cursor confirmado
```

Expected primary language:

```text
Mes / período
Factura
Nota crédito
En proceso / Al día / Pendiente
```

Cursor/raw document code belongs under technical detail.

---

# 6. PASS 3 — INFORMATION ARCHITECTURE

```text
PASS_STATUS=YELLOW
```

## Strengths

- desktop IA is clear;
- mobile nav exact five slots;
- Catalog/Inventory remains one owner;
- MELI account/global app ownership is separate correctly;
- Financial is not duplicated.

## UX-007 — Global Administration uses a second shell/navigation

```text
SEVERITY=MAJOR
```

Global pages:

```text
/system/companies
/system/users
/settings/system
/settings/mercadolibre
```

use `simple-page/simple-nav` instead of the main app shell.

This does not break backend truth, but it creates a second navigation mental model inside one product.

### Recommendation

Audit whether the existing `navigation.php` can safely support global-admin context with nullable/no active company.

If yes:

```text
MERGE global admin into shared shell
```

If not, document a page-level exception and align the global shell visually/behaviorally.

Do not force this without route/session evidence.

## UX-008 — Historical Sales audit competes with ordinary Sales

Audit is legitimate, but should use progressive disclosure / secondary placement.

## UX-009 — Users page tries to do too much at once

Each user card exposes simultaneously:

```text
identity edit
status change
system-admin change
password reset
existing memberships
membership role edit
membership removal
new membership
```

This is cognitively heavy.

Expected:

```text
compact user summary
primary status/access facts
progressive disclosure for identity/security/memberships
```

No permission model change.

---

# 7. PASS 4 — DENSITY / INFINITE EXCEL

```text
PASS_STATUS=YELLOW/RED
```

## KEEP

Sales desktop list uses a table appropriately.

MELI accounts table is appropriate for multiple accounts.

Billing period history table is appropriate.

Responsive tables convert to cards on small screens.

## Findings

### UX-010 — Users administration is form-dense

Not an Excel table, but still violates the Human-First density law because all operations are expanded simultaneously.

### UX-011 — Sales Audit adds a second heavy table/workflow to primary Sales page

Should be secondary/disclosed.

### UX-012 — Catalog exposes ID as first table column and separate ID selector

The system identifier dominates normal selection more than needed.

### Sale Detail

HF1 source now replaces ordinary product mini-table with compact product records and removes empty Billing table chrome.

```text
STATUS=IMPROVED / BROWSER PENDING
```

---

# 8. PASS 5 — VISUAL DESIGN SYSTEM

```text
PASS_STATUS=GREEN/YELLOW
```

## Strengths

Current `app.css` already has a coherent token system:

```text
background/surface/text/muted/border
primary/success/warning/danger/info/finance
shared shadows
shared radii
sidebar/topbar
safe-bottom
```

Current visual direction is consistent:

```text
light
executive
calm
restrained
```

## UX-013 — Project Design System was implicit, now persisted

```text
STATUS=CLOSED BY GOVERNANCE
```

`design-system/erp-meli-2-0/MASTER.md` now preserves current decisions.

## UX-014 — Avoid adding HF-specific CSS families without browser evidence

HF1 correctly reused existing panel/settings/status/notice families.

Continue this pattern.

---

# 9. PASS 6 — ACCESSIBILITY

```text
PASS_STATUS=YELLOW
```

## Strengths

- visible `:focus-visible` ring;
- launcher focus trap;
- ESC closes launcher;
- focus returns to opener;
- semantic nav/aside/main/dialog patterns;
- decorative nav icons use `aria-hidden`;
- forms use visible labels;
- mobile safe-area exists.

## UX-015 — No skip-to-main mechanism found

```text
SEVERITY=MAJOR A11Y DEBT
```

The persistent desktop navigation creates repeated content before `<main>`.

Audit/implement a skip link or equivalent bypass mechanism as a focused cross-cut microblock.

## UX-016 — Base font is 15px

```text
SEVERITY=MINOR/MAJOR MOBILE USABILITY RISK
```

`body` uses 15px and form controls inherit font size.

The Human-First/UI UX Pro Max baseline prefers 16px body/mobile readability and helps avoid mobile form zoom behavior.

Do not blindly change globally. Browser-check visual density and then implement the smallest token/base adjustment.

## UX-017 — Interactive controls use 40–42px baseline

Current buttons are 40px and inputs 42px minimum.

They exceed WCAG minimum target rules in many contexts but are below the project-preferred 44px touch baseline.

Classify as optimization, not a proven blocker.

## UX-018 — Destructive actions need stronger confirmation UX

Relevant examples:

```text
MELI disconnect/delete
Company archive/delete
System clear debug
System-admin privilege changes
membership removal
```

Backend guards may be correct, but Human-First T3 UI should explain scope/impact and require an appropriate confirmation pattern for destructive operations.

Audit one owner at a time; do not add generic modal architecture without evidence.

---

# 10. PASS 7 — INTERACTION / FORMS / FEEDBACK

```text
PASS_STATUS=YELLOW
```

## Strengths

- visible form labels;
- server-side validation contracts;
- 422 invalid system settings now translated correctly;
- CSRF preserved;
- async Billing is explicit about not calling MELI directly from page;
- launcher interactions certified.

## Findings

### UX-019 — Billing form asks for storage-shaped period

`AAAA-MM-01` exposes backend representation.

Human UI should prefer a month concept.

Potential implementation options must be audited against controller contract before change.

### UX-020 — Some action feedback is architecture-facing

Examples:

```text
progreso persistido
cursor confirmado
solicitud registra trabajo pendiente
```

Human copy should explain what happens to the operator.

### UX-021 — Users administration needs progressive disclosure

Also an interaction finding, not just density.

---

# 11. PASS 8 — RESPONSIVE / MOBILE / TABLET

```text
PASS_STATUS=GREEN/YELLOW
```

## Certified strengths

- 390x844 B1 browser smoke passed;
- five mobile slots exact;
- Apps launcher works;
- no primary horizontal overflow in certified screens;
- safe-area handling exists;
- desktop B2A/B2B/B2C1 passed at 1440x900;
- tables have mobile-card transformation;
- desktop sidebar hides at <=1023px.

## Open

Human-First HF1 layout has not yet been browser-certified after source change.

Global Administration pages using `simple-page` need cross-page responsive QA before final Green.

Mobile font/touch baseline should be evaluated in browser.

---

# 12. PASS 9 — DATA-HEAVY PATTERNS / PERFORMANCE

```text
PASS_STATUS=GREEN/YELLOW
```

## Strengths

```text
Sales bounded to 100 recent rows
Catalog bounded to 100 products
Audit bounded to 25 runs
Billing bounded to 100 periods
no infinite server-rendered lists found in audited core surfaces
```

No evidence supports adding virtualization/framework dependencies now.

## Improvements

Use:

```text
search/filter/drill-down/progressive disclosure
```

before adding more visible columns or more panels.

Users page is the largest current density risk even though it is card/form-based rather than a table.

---

# 13. PASS 10 — REAL BROWSER / CROSS-PAGE CONSISTENCY

```text
PASS_STATUS=YELLOW
```

## Already real-browser certified

```text
shell desktop/mobile
Sales empty desktop
Catalog empty desktop
Billing empty desktop
Sale Detail known fee
Sale Detail financial conflict
```

## Open

```text
HF1 updated Sale Detail normal/conflict desktop + mobile
Dashboard Human-First after remediation
Sales List Human-First after remediation
Catalog Human-First after remediation
Billing Human-First after remediation
Global Admin shared-shell/consistency decision
Users progressive disclosure
MELI/Admin destructive-action UX
full 7-viewport final matrix
limited-role browser pass
```

---

# 14. CROSS-PAGE FINDINGS SUMMARY

| ID | Finding | Severity | Disposition |
|---|---|---|---|
| UX-001 | HF1 browser certification missing | MAJOR GATE | VERIFY |
| UX-002 | Dashboard raw UNKNOWN/architecture copy | MAJOR | OPTIMIZE |
| UX-003 | Dashboard raw recent-sale statuses | MAJOR | OPTIMIZE |
| UX-004 | Sales audit competes with normal workflow/raw states | MAJOR | SIMPLIFY |
| UX-005 | Catalog ID-centric primary flow | MAJOR | SIMPLIFY |
| UX-006 | Billing raw/implementation language | MAJOR | OPTIMIZE |
| UX-007 | Global Admin second navigation shell | MAJOR | AUDIT→MERGE/EXCEPTION |
| UX-009 | Users all operations expanded | MAJOR | SIMPLIFY |
| UX-015 | No skip-to-main mechanism | MAJOR A11Y | ADD MINIMAL |
| UX-016 | 15px base/mobile font risk | MINOR/MAJOR | VERIFY→OPTIMIZE |
| UX-017 | 40–42px touch baseline | MINOR | OPTIMIZE |
| UX-018 | Destructive confirmation UX inconsistent | MAJOR T3 | AUDIT→IMPROVE |
| UX-019 | Billing storage-shaped period input | MINOR/MAJOR | AUDIT CONTRACT |
| UX-020 | Operator feedback uses technical vocabulary | MAJOR | HUMANIZE |

---

# 15. NEW IMPROVEMENT PLAN

Do not resume the old sequence blindly.

The new sequence is Human-First + evidence driven.

## PHASE H0 — Governance

```text
STATUS=CLOSED
```

Installed:

```text
Human-First Master law
AGENTS mandatory activation
repository skill adapter
ERP Meli project Design Master
PR gate
checkpoint
```

## PHASE H1 — Sale Detail browser certification

```text
PRIORITY=P0 RELEASE GATE
```

Scope:

```text
normal known-fee sale
financial-conflict sale
1440x900
390x844
```

Verify:

```text
human comprehension in 5–10s
no raw primary codes
technical disclosures closed/usable
no empty Billing table chrome
no ordinary product horizontal scroll
no page overflow
mobile nav clearance
console/5xx clean
read-only side effects
```

If PASS: checkpoint.

If defect: one focused RED→GREEN only.

## PHASE H2 — Cross-cut accessibility foundation

### H2A Skip-to-main

Add smallest semantic bypass mechanism across shared shell.

### H2B Mobile typography / target baseline

Browser-audit 15px/40–42px behavior first.

Change only shared tokens/rules if evidence supports it.

No page-by-page patches.

## PHASE H3 — Dashboard Human-First

```text
UNKNOWN -> No disponible / Sin información suficiente
remove owner/backend wording from primary copy
humanize recent sale status
keep currencies separate
keep no fake profitability
```

Browser desktop/mobile + checkpoint.

## PHASE H4 — Sales List Human-First

Primary:

```text
search/review sales
```

Secondary:

```text
historical audit
```

Humanize audit states.

Keep raw state available only when useful.

Do not alter audit engine.

Browser desktop/mobile + checkpoint.

## PHASE H5 — Catalog / Inventory Human-First

Make normal list selection primary.

Move exact-ID selector to secondary/advanced presentation.

Preserve:

```text
missing=Sin definir
zero=0
one backend owner
```

Then run factual inventory missing-vs-zero browser gate (old B2C2) here, not before.

## PHASE H6 — Billing Human-First

### H6A Copy/disclosure

```text
BILL -> Factura
CREDIT_NOTE -> Nota crédito
cursor/progress internals -> technical detail
human async explanation
```

### H6B Period input UX

Audit controller contract first.

If safe, present month-level input without changing factual period key semantics.

No Work/429 architecture change.

## PHASE H7 — Global Admin consistency

### H7A Shell decision

Audit whether shared `navigation.php` can serve system-admin pages with no active company.

If safe:

```text
MERGE into shared shell
```

If unsafe/unnecessarily complex:

```text
KEEP separate shell
PERSIST justified page/design-system exception
ALIGN visual/navigation semantics
```

### H7B Users progressive disclosure

Compact user summary first.

Move password/system-admin/membership maintenance into clear secondary sections/disclosures.

No permission-model changes.

### H7C Destructive actions

Audit and improve:

```text
companies
users/memberships
MELI disconnect/delete
system debug clear
MELI credential clear
```

Use clear scope/impact.

Avoid building a generic modal system unless multiple proven cases justify it.

## PHASE H8 — MELI / Settings Human-First polish

MELI accounts already have good human status mapping.

Focus only on:

```text
destructive action clarity
technical vocabulary hierarchy
global settings consistency
```

Do not invent health/sync screens.

## PHASE H9 — Final cross-page audit

Run full Master-law 10-pass audit again.

Required:

```text
7 approved viewport sizes
system-admin role
limited role
navigation consistency
keyboard/focus
zoom/text scaling smoke
truth fixtures known/unknown/conflict/zero/missing
console/network
no primary horizontal overflow
```

Generate final scorecard.

## PHASE H10 — Return to ERP completion sequence

Only after Human-First final gate:

```text
remaining ERP factual gates
local demo
manual E2E
user review/approval
Hostinger
runtime certification
cron/Work Runner
real MELI READ-only
later authorized writes
```

---

# 16. PRIORITY MAP

## P0

```text
H1 HF1 browser certification
H2A skip-to-main accessibility
```

## P1

```text
H3 Dashboard
H4 Sales List
H5 Catalog/Inventory
H6 Billing
H7A Admin shell consistency
H7B Users progressive disclosure
H7C destructive actions
```

## P2

```text
mobile 16px/44px preferred baseline after evidence
locale/date/currency formatting polish
system debug human byte display
MELI global settings wording
```

## P3

```text
micro-polish
spacing refinements
copy refinement
optional saved views only with evidence
```

## P4

```text
future enhancements with proven user need
```

---

# 17. NO-GO

During Human-First plan:

```text
NO backend owner redesign
NO frontend framework
NO fake features
NO fake KPIs
NO Financial parallel module
NO Inventory parallel route
NO Hostinger
NO cron
NO real MELI mutation
NO REMOTE_WRITES
NO F15/F16
NO merge PR #19
```

---

# 18. NEXT MICROBLOCK

```text
NEXT=H1 UX-HF1 REAL BROWSER HUMAN-COMPREHENSION CERTIFICATION
```

Do not begin H2/H3 until H1 checkpoint is persisted.
