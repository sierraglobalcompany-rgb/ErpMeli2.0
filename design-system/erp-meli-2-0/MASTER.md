# ERP MELI 2.0 — DESIGN SYSTEM MASTER

**Status:** PROJECT UI/UX SOURCE OF TRUTH  
**Parent law:** `docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md`  
**Project:** ERP MELI 2.0  
**Scope:** visual language, information hierarchy, navigation, responsive behavior, components and Human-First presentation  
**Backend authority:** unchanged; this file never invents capabilities or facts

---

# 1. PRODUCT PROFILE

```text
PRODUCT_NAME=ERP MELI 2.0
PRODUCT_TYPE=B2B ERP / operational SaaS
PRIMARY_USERS=operations + administration
SECONDARY_USERS=viewer/limited operational roles
PRIMARY_CONTEXT=desktop office / responsive mobile operational access
DATA_DENSITY=medium-high, controlled by progressive disclosure
STYLE=light / executive / calm / data-first / Human-First
MOTION=low
VARIANCE=low-medium
ACCESSIBILITY_TARGET=WCAG 2.2 AA baseline
```

This product is not a marketing site. It must optimize for:

```text
clarity
truth
decision speed
operational confidence
role safety
repeatability
```

---

# 2. HUMAN-FIRST PRODUCT LAW

Primary interaction model:

```text
VERDAD
→ COMPRENSIÓN
→ DECISIÓN
→ ACCIÓN
→ DETALLE
```

Never expose the internal data model as the default mental model.

Rules:

```text
UNKNOWN != 0
CONFLICT != 0
MISSING INVENTORY != 0
RAW CODE != PRIMARY LABEL
TECHNICAL DETAIL = SECONDARY/DISCLOSED
EMPTY DATA != EMPTY TABLE CHROME
```

---

# 3. VISUAL DIRECTION

The approved direction is:

```text
light
premium but restrained
executive
airy enough to scan
data-first without looking like raw spreadsheet
calm semantic color
low visual noise
```

Do not introduce:

```text
dark dashboard aesthetic
glass-heavy visual language
random gradients
neumorphism
marketing-style motion
new frontend framework
page-specific visual systems
```

---

# 4. CURRENT TOKEN AUTHORITY

Current implementation authority is `public/assets/app.css`.

Core tokens:

```css
--bg: #f5f8fd;
--surface: #ffffff;
--surface-soft: #f8fbff;
--text: #13213d;
--muted: #6b7890;
--border: #e2e8f2;
--primary: #246bfd;
--primary-strong: #1554d1;
--primary-soft: #edf4ff;
--success: #168a49;
--success-soft: #eaf8ef;
--warning: #b86708;
--warning-soft: #fff5e7;
--danger: #c83c4b;
--danger-soft: #fff0f2;
--info: #1486c9;
--finance: #7556d8;
--radius-sm: 10px;
--radius: 16px;
--radius-lg: 22px;
--sidebar: 252px;
--topbar: 72px desktop / 64px responsive;
```

Do not replace these with a second token family without an explicit design-system migration block.

---

# 5. TYPOGRAPHY

Current family:

```text
Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif
```

Rules:

- headings short and clear;
- body copy human and direct;
- secondary copy must remain readable;
- numeric values use tabular figures when comparison matters;
- raw technical IDs may wrap;
- do not reduce body size to solve density.

---

# 6. LAYOUT SYSTEM

Desktop:

```text
persistent sidebar
fixed topbar
centered/max-width main content
panels/cards grouped by job
```

Responsive ≤1023px:

```text
desktop sidebar hidden
mobile/tablet topbar
five-slot bottom navigation
content bottom clearance including safe-area
Apps launcher for secondary destinations
```

Small viewport ≤760px:

```text
single-column settings/detail layouts
responsive tables transform to cards
launcher grid adapts
technical detail may stack vertically
```

No third tablet product.

Tablet is adaptive behavior of the same product.

---

# 7. NAVIGATION

## Desktop

Use sidebar groups with active state.

Primary IA:

```text
Inicio
Operación
Productos
Finanzas
Mercado Libre
Administración
```

Only render destinations supported by real routes/permissions.

## Mobile / tablet

Exact five primary slots:

```text
Inicio
Operación
Apps
Productos
Mercado Libre
```

Apps is the central launcher.

Mercado Libre may remain present but disabled when factual authorization/owner is unavailable; never create a fake route.

Secondary/admin/finance destinations live in Apps when appropriate.

---

# 8. PAGE COMPOSITION

Default order:

```text
1. Page/context title
2. Human status / attention
3. Primary facts
4. Primary action
5. Main operational content
6. Secondary facts
7. Technical evidence/disclosure
```

Do not place raw implementation detail before the business meaning.

---

# 9. COMPONENT FAMILY

Reuse existing shared families before adding new CSS:

```text
.page-header
.panel
.settings-grid
.settings-list
.toolbar
.section-heading
.empty-state
.status-chip
.status-connected
.status-attention
.status-disabled
.status-neutral
.notice
.table-wrap
.row-actions
.numeric-value
.fact-pill
.inline-fact-list
.evidence-list
.button
.button-secondary
```

New component CSS is allowed only when an existing family cannot express the correct semantic job cleanly.

---

# 10. STATUS LANGUAGE

Primary UI uses human labels.

Examples:

| System fact | Human primary presentation |
|---|---|
| `paid` | Pagada |
| `UNKNOWN` | Sin información suficiente |
| `CONFLICT` | Requiere revisión |
| `NONE` | Sin novedad / No aplica / Sin registro |
| `disabled` | Deshabilitada |
| disconnected account | Desconectada |
| background work pending | Pendiente / En proceso according to factual state |

Raw state may appear inside technical detail if useful for support/audit.

---

# 11. TABLE POLICY

Use desktop table when comparison across many rows is the primary task.

Good table candidates:

```text
sales list
users list
companies list
period list
large product list
```

Avoid table as primary presentation for:

```text
single sale
single company
single settings record
single reconciliation summary
1–5 product lines when cards/compact rows are clearer
```

Empty state:

```text
message + useful next step
NO empty table header below it
```

Mobile primary data must not require page-level horizontal scrolling.

---

# 12. DASHBOARD CONTRACT

Primary job:

```text
understand operation + priorities
```

First layer should remain limited to useful facts.

Current approved ideas:

```text
Ventas totales
Órdenes
Productos vendidos
Rentabilidad only when factually supportable
main sales/revenue trend when factual
priority/attention area when factual
account/reconciliation/system information only if owner exists
```

Never fabricate:

```text
profitability
health percentage
tasks/activity engine
stock alert
```

If unavailable:

```text
No disponible
Sin información suficiente
```

not raw `UNKNOWN` in primary UI.

---

# 13. SALES LIST CONTRACT

Primary job:

```text
find / filter / review sales
```

Normal workflow dominates.

Historical/audit tooling is secondary and should use progressive disclosure when it competes with normal sales work.

Desktop table is appropriate.

Mobile uses card/list transformation.

---

# 14. SALE DETAIL CONTRACT

Primary job:

```text
understand one sale quickly
```

First layer:

```text
sale identity
human status
amount/currency when factual
account/context
attention callout when needed
products
financial summary
billing/post-sale summary
```

Advanced:

```text
Reconciliación técnica
raw scope
pack/order IDs
coverage states
Billing evidence
support diagnostics
```

For conflict:

```text
Primary: Requiere revisión
Explain reason in human language
Raw CONFLICT remains technical detail
```

Do not invent net values from incomplete evidence.

---

# 15. CATALOG / INVENTORY CONTRACT

One human area and one backend owner.

Primary workflow:

```text
browse/select product
see identity
see inventory state
act if authorized
```

Rules:

```text
missing quantity -> Sin definir
factual zero -> 0
```

Direct database-ID lookup is advanced/secondary, not the ideal primary mental model.

Do not invent search backend merely for UX; reuse factual capabilities first.

---

# 16. BILLING CONTRACT

Primary job:

```text
understand periods/status and request supported work safely
```

Human primary copy:

```text
Factura
Nota crédito
Pendiente
En proceso
Completado
```

Technical cursor/persistence/request internals are secondary.

Do not create parallel Financial UI owners or fake reconciliation/profitability.

---

# 17. MERCADO LIBRE CONTRACT

Two separate owners remain conceptually distinct:

```text
company seller accounts
system/global Developer app settings
```

Do not merge them merely for visual simplicity.

Connection state should be human-first while preserving raw facts in technical/support detail where needed.

No fake health/webhook/sync status.

---

# 18. ADMIN CONTRACT

Admin pages should explain impact and scope.

Keep:

```text
Companies
Users / memberships / roles
System settings / diagnostics
```

Destructive actions require explicit scope and consequence.

Diagnostics may be more technical than ordinary operational screens, but still grouped and understandable.

---

# 19. FORMS

- visible labels;
- helper text for complex inputs;
- error near field;
- no placeholder-only semantics;
- clear required state;
- loading/success/error feedback;
- destructive confirmation;
- no silent data loss.

---

# 20. ACCESSIBILITY

Baseline:

```text
WCAG 2.2 AA
```

Required:

```text
visible focus
logical keyboard order
Escape for transient UI
focus return
accessible names
color not sole indicator
focus not obscured by fixed UI
safe touch targets
zoom/text growth usability
reduced motion
```

Prefer semantic native elements.

---

# 21. RESPONSIVE QA MATRIX

Required product matrix unless a narrower microblock is explicitly authorized:

```text
390x844
430x932
768x1024
1024x768
1366x768
1440x900
1920x1080
```

Critical checks:

```text
no primary horizontal page scroll
no sidebar/bottom-nav collision
no fixed-nav content obstruction
safe-area clearance
cards/forms readable
responsive table-to-card behavior
```

---

# 22. MOTION

Low-motion ERP.

Use motion only for:

```text
feedback
modal/sheet
state continuity
loading
```

Do not make correctness depend on animation-end.

---

# 23. DENSITY / VARIANCE

Recommended:

```text
Dashboard density <=6/10
Forms <=6/10
Admin <=7/10
Desktop data tables <=8/10
Mobile <=5/10
Motion 2–4/10
Variance 2–5/10
```

These are audit heuristics, not hardcoded CSS.

---

# 24. BROWSER HUMAN-COMPREHENSION GATE

For every major page ask, without explanation:

```text
1. ¿Qué página es?
2. ¿Qué está pasando?
3. ¿Qué requiere atención?
4. ¿Qué harías ahora?
5. ¿Dónde verías el detalle?
```

If two or more answers are unclear:

```text
NOT HUMAN-FIRST
```

---

# 25. ANTI-PATTERNS

Do not introduce:

```text
infinite Excel
raw status codes as primary labels
empty table chrome
multiple equal CTAs
random component families
page-specific token systems
horizontal mobile page scroll
fake metrics
fake health
UNKNOWN->0
CONFLICT->0
fixed bars over content
tech jargon before business meaning
```

---

# 26. PAGE OVERRIDES

Use:

```text
design-system/erp-meli-2-0/pages/<page>.md
```

Only for justified page-specific deviations.

Every exception documents:

```text
EXCEPTION_ID
LAW_OVERRIDDEN
REASON
USER_BENEFIT
RISK
EVIDENCE
REVIEW_DATE
```

No silent deviation.

---

# 27. DELIVERY GATE

A page/phase is GREEN only when applicable gates are proven:

```text
TRUTH
AUTHORIZATION
HUMAN COMPREHENSION
ACCESSIBILITY
RESPONSIVE
AUTOMATED QA
REAL BROWSER QA
NO INFINITE EXCEL
CHECKPOINT
```

Repository authority and the Human-First Master law outrank stale chat context.
