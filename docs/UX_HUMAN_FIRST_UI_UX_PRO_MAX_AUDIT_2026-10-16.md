# ERP MELI 2.0 — UX HUMAN-FIRST REALIGNMENT

**Date:** 2026-10-16  
**Status:** APPROVED AUDIT / DESIGN REALIGNMENT AUTHORITY  
**Scope:** presentation, information architecture, copy, hierarchy, progressive disclosure, responsive behavior  
**Backend semantics:** unchanged  

## 1. WHY THIS AUDIT EXISTS

The Design Master work is technically strong and fact-safe, but real-browser evidence shows a UX drift: some screens expose the internal system model more directly than the user's mental model.

The ERP must not become an infinite spreadsheet, a debug console, or a list of machine states. It must remain factual while being understandable in seconds by a human operator.

This audit therefore adds a third UX law beside KISS and anti-noise:

```text
HUMAN_FIRST=the primary screen explains what happened, what matters, and what the user can do next; technical codes/details are secondary and available on demand.
```

## 2. EXTERNAL DESIGN AUTHORITY

Reference:

```text
Official repository: https://github.com/nextlevelbuilder/ui-ux-pro-max-skill
Website supplied by user: https://ui-ux-pro-max-skill.com/
```

The supplied website identifies itself as an unofficial multilingual translation and links to the official GitHub repository. The official repository is the design/UX reference authority.

Relevant UI UX Pro Max principles adopted here:

- accessibility and keyboard visibility are critical;
- responsive layout must avoid unnecessary horizontal scrolling;
- navigation must be predictable and not overloaded;
- forms should use visible labels, helpful feedback and progressive disclosure;
- information density must be controlled to avoid cognitive overload;
- design-system decisions should be persistent and page-specific deviations explicit;
- raw technical complexity should not overwhelm the default experience;
- empty states should be meaningful rather than blank or structurally noisy;
- interfaces should use consistent visual language, spacing and semantic states.

UI UX Pro Max is a DESIGN REFERENCE, not a runtime dependency. Do not add a new framework/package merely to comply with it.

## 3. WHAT IS ALREADY GOOD — KEEP

### Shell / navigation

- Desktop sidebar + topbar: KEEP.
- Mobile five-slot bottom navigation: KEEP.
- Mobile Apps launcher: KEEP.
- Current light executive visual direction: KEEP.
- Single Design Master CSS family: KEEP.
- Vanilla JS launcher/accessibility fixes: KEEP.
- No duplicate desktop/mobile backend datasets: KEEP.

### Truth / backend semantics

- `UNKNOWN != 0`: KEEP.
- `CONFLICT` is never silently resolved: KEEP.
- Currency separation: KEEP.
- Missing inventory is not zero: KEEP.
- Sale Detail is read-only: KEEP.
- No remote MELI call on human page render: KEEP.
- No new Work/Billing/Financial architecture for presentation: KEEP.

### Browser evidence

B1/B2A/B2B/B2C1 prove that the current UI is functional, responsive and fact-safe. This audit does NOT invalidate that evidence. It changes the PRESENTATION QUALITY GATE before ERP COMPLETE.

## 4. PRIMARY UX FINDING — REAL DRIFT

### Severity: MAJOR UX / not a backend defect

The current UI sometimes renders internal domain codes as primary user-facing language:

```text
UNKNOWN
CONFLICT
PACK_MEMBERSHIP_UNVERIFIED
NONE
paid
BILL
CREDIT_NOTE
Readiness
Cobertura Billing
Progreso persistido
Cursor confirmado
```

These values may be correct system facts, but they are not good primary copy for a human operator.

### Rule

```text
RAW_CODE != PRIMARY_LABEL
```

The UI may preserve raw codes for support/audit/diagnostics, but normal operators should first see human language.

Examples:

```text
UNKNOWN                     -> Sin información suficiente
CONFLICT                    -> Datos incompatibles · requiere revisión
PACK_MEMBERSHIP_UNVERIFIED  -> Membresía del paquete sin verificar
NONE                        -> Sin postventa registrada
paid                        -> Pagada
BILL                        -> Factura
CREDIT_NOTE                 -> Nota crédito
```

Raw code may remain in a secondary `Detalle técnico` disclosure where useful.

## 5. HUMAN-FIRST DESIGN LAWS

These laws apply to all remaining UI work and to remediation of existing screens.

### HF-01 — Decision first

At first glance the operator should understand:

1. What happened?
2. Is something wrong or incomplete?
3. What should I do next?

Do not begin with implementation vocabulary.

### HF-02 — Progressive disclosure

Show the essential business summary first.
Move technical detail behind one of:

```text
Ver detalle
Ver conciliación técnica
Más información
Datos técnicos
```

Do not hide critical warnings.

### HF-03 — Raw codes are secondary

Never make raw machine states the main label when a truthful human label exists.

### HF-04 — One screen, one main job

A primary screen should not make Sales, audit machinery, internal reconciliation, Billing internals and diagnostics compete equally for attention.

### HF-05 — Tables only for comparison

Use a table when the user must compare multiple records/columns.

For a single record or a handful of facts, prefer:

```text
summary cards
key-value groups
compact record rows
status summaries
```

### HF-06 — No empty table chrome

If a dataset is empty, render a useful empty state and DO NOT render an empty table header beneath it.

### HF-07 — Human status + semantic emphasis

Important conditions use a human-readable status chip/callout with text, not color alone.

Examples:

```text
Correcto
Sin información
Requiere revisión
Desconectada
En proceso
Pendiente
```

### HF-08 — Technical identifiers are supporting information

IDs, pack IDs, cursor IDs, canonical/internal wording and persistence details are secondary unless the user's immediate task depends on them.

### HF-09 — Helpful empty states

An empty state answers:

```text
What is missing?
Is that normal?
What can I do next?
```

### HF-10 — Locale-aware presentation

Human display should prefer readable date/time/currency formatting without changing stored exact values.

### HF-11 — Truth is preserved during translation

Humanization must never weaken semantics:

```text
Sin información suficiente != 0
Datos incompatibles != 0
Sin datos != No
No disponible != error
```

### HF-12 — Diagnostics are separate

Support/debug/raw system facts belong in an explicit technical detail or diagnostics surface, not in the primary business path.

## 6. SCREEN AUDIT

## 6.1 Sale Detail — PRIORITY P0

Current strengths:

- factual source is correct;
- summary/products/reconciliation/post-sale domains are present;
- UNKNOWN/CONFLICT semantics are preserved;
- read has no side effects.

Current UX problems:

1. `CONFLICT` / `UNKNOWN` are exposed raw as primary business copy.
2. The screen is too vertically long and gives technical reconciliation equal weight with the sale summary.
3. `Reconciliación` contains many implementation-facing labels at once.
4. When Billing details are empty, an empty-state message is followed by an empty table header.
5. `Factura / Postventa` exposes `Readiness`, `PACK_MEMBERSHIP_UNVERIFIED` and `NONE` directly.
6. Product data uses a table inside a narrow column and requires internal horizontal scrolling at 1440px browser evidence.
7. The page does not provide an immediate human summary of what requires attention.

### New target IA

```text
Venta 1001
[Pagada] [COP 10.0000] [Cuenta ...]

IF attention exists:
[Requiere revisión]
La venta incluye datos incompatibles entre monedas. No se calculó una comisión consolidada.

A. Resumen de la venta
B. Productos
C. Resumen financiero
D. Facturación y postventa

Advanced disclosure:
[Ver conciliación técnica]
  - scope
  - pack/order IDs
  - raw coverage codes
  - Billing facts table when rows actually exist
  - evidence details
```

### Human mapping for B2C1 conflict example

Primary:

```text
Comisión: Requiere revisión
Motivo: Hay monedas diferentes dentro del mismo paquete.
```

Secondary technical disclosure:

```text
Comisión operativa raw state: CONFLICT
Cobertura raw state: CONFLICT
Moneda raw state: CONFLICT
Pack: PACK-A
Pedidos: 1001, 1002
```

No aggregate or zero may be invented.

## 6.2 Dashboard — PRIORITY P1

Current strengths:

- cards are readable;
- currencies are separated;
- factual local aggregation only;
- no fake profitability.

Current UX problems:

- `Rentabilidad = UNKNOWN` is machine language;
- copy such as `owner agregado autorizado` is architecture language, not operator language;
- `Vista factual de la operación local` is accurate but overly technical.

Target presentation:

```text
Rentabilidad
No disponible
Aún no hay información suficiente para calcularla con seguridad.
```

Optional technical explanation belongs in help/detail, not the card body.

## 6.3 Sales List — PRIORITY P1

Current strengths:

- search and recent sales are clear;
- sale detail link is direct;
- empty states exist.

Current UX problems:

- historical audit competes visually with the normal Sales workflow;
- audit state copy exposes raw states such as CAPTURING / REPAIRING / CONFIRMING / VALID / ATTENTION / UNAVAILABLE;
- explanation text reads like internal documentation.

Target:

```text
Primary: Buscar / revisar ventas
Secondary: Auditoría histórica [advanced disclosure]
```

Human audit statuses should be shown first, with raw state secondary if needed.

## 6.4 Catalog + Inventory — PRIORITY P1

Current strengths:

- Catalog and Inventory correctly remain one owner;
- missing inventory stays `Sin definir`;
- product rows are selectable.

Current UX problems:

- `Seleccionar producto por ID` is machine-oriented as a primary card;
- users should normally click/search a product rather than remember its database ID;
- ID-based lookup should be secondary/advanced.

Target:

```text
Primary: product list/search -> select product
Secondary: Ir a producto por ID
```

Do not invent a search backend if none exists. Reuse clickable rows first; add richer search only in an authorized future microblock if backend support is justified.

## 6.5 Billing — PRIORITY P1

Current strengths:

- one Billing owner;
- no remote call directly from the page;
- durable work model remains intact.

Current UX problems:

- raw document codes `BILL` and `CREDIT_NOTE` are primary labels;
- `AAAA-MM-01`, `progreso persistido`, `cursor confirmado` expose implementation details;
- the notice explains internal processing rather than user expectation.

Target copy examples:

```text
Tipo de documento:
Factura
Nota crédito

After request:
La sincronización se procesará en segundo plano. Puedes salir de esta página y volver más tarde.
```

Technical cursor/progress IDs go in advanced detail.

## 6.6 Administration / MELI Settings — PRIORITY P2

Keep current ownership and safety boundaries.
Audit later for the same Human-First rule:

- server-managed / runtime / credential implementation wording should be secondary;
- destructive actions must remain explicit and safe;
- diagnostics may remain technical because the user intent is technical, but should still be grouped and explain consequences.

## 7. PRODUCT TABLE RULE

A table is not automatically wrong. The problem is using table geometry for a task that does not require comparison.

### Sales list / 100 rows

Table = appropriate on desktop.
Cards = appropriate on mobile.

### One sale's 1–5 product lines

Prefer compact product rows/cards that can display:

```text
Product name
SKU
Quantity × unit price
currency
```

without an internal horizontal scrollbar on desktop.

If there are many items, the list can expand or switch to a table.

## 8. EMPTY DATA RULE

Bad:

```text
No hay detalles Billing...
[empty table header below]
```

Good:

```text
Sin detalles de facturación
Todavía no hay información de Billing asociada a esta venta.
```

Table markup should render only when rows exist.

## 9. UX GATE BEFORE ERP COMPLETE

ERP COMPLETE now also requires:

```text
HUMAN_FIRST_COPY=GREEN
PROGRESSIVE_DISCLOSURE=GREEN
RAW_MACHINE_CODES_PRIMARY_VIEW=NO
EMPTY_TABLE_CHROME=NO
SINGLE_RECORD_EXCEL_LAYOUT=NO
SALE_DETAIL_DESKTOP_INTERNAL_SCROLL=NO for ordinary 1–5 item case
MOBILE_PRIMARY_HORIZONTAL_SCROLL=NO
PRIMARY_ACTION_HIERARCHY=GREEN
HUMAN_STATUS_MAPPING=GREEN
```

This is presentation-only unless evidence proves a missing product capability.

## 10. REMEDIATION SEQUENCE

Do NOT continue directly to Inventory B2C2 yet.

```text
UX-HF1  Sale Detail human-first realignment
        -> factual semantics unchanged
        -> raw technical detail progressively disclosed
        -> no empty Billing table chrome
        -> human conflict/unknown labels
        -> remove ordinary desktop product-table internal horizontal scroll
        -> browser QA + checkpoint

UX-HF2  Dashboard human-first copy/hierarchy
        -> browser QA + checkpoint

UX-HF3  Sales List audit progressive disclosure + human statuses
        -> browser QA + checkpoint

UX-HF4  Catalog/Inventory primary workflow humanization
        -> browser QA + checkpoint

UX-HF5  Billing human copy/progressive disclosure
        -> browser QA + checkpoint

UX-HF6  MELI/Admin/settings audit and focused remediations only if evidence requires them
        -> browser QA + checkpoint

UX-HF7  cross-page responsive/human comprehension audit
        -> resume remaining factual browser gates (including Inventory missing-vs-zero)
```

## 11. KISS / ANTI-NOISE RULING

Do NOT:

- add a frontend framework;
- create a second UI architecture;
- install UI UX Pro Max into production runtime;
- copy its whole style database into the ERP;
- redesign backend owners;
- invent charts/data merely to make pages prettier;
- hide factual warnings.

Do:

- reuse current tokens/components;
- improve information hierarchy;
- translate raw states truthfully;
- progressively disclose technical evidence;
- keep each microblock small and testable.

## 12. STOP-SAFE RULING

```text
UI-DM10-B2C1 FINANCIAL CONFLICT=GREEN
UI-DM10-B2C2 INVENTORY MISSING-VS-ZERO=FROZEN UNTIL HUMAN-FIRST REALIGNMENT
NEXT=UX-HF1 SALE DETAIL HUMAN-FIRST REALIGNMENT
PR_19=DRAFT / NOT MERGED
REMOTE_WRITES=OFF
REAL_MELI_HTTP=0
NO HOSTINGER
NO CRON
NO REAL MELI MUTATION
```
