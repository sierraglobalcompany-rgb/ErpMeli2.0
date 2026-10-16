# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=UI-DM10-B2C1 GREEN / HUMAN-FIRST UX ACTIVE / UX-HF1 AUTOMATED GREEN / UX-HF1 BROWSER QA PENDING / STOP-SAFE**

> Autoridad operativa para recuperar el proyecto si el chat se interrumpe. Retomar exactamente desde `RESUME_EXACTLY_FROM`; no reconstruir por intuición.

## 1. REPOSITORY AUTHORITY

```text
DATE=2026-10-16
REPO=sierraglobalcompany-rgb/ErpMeli2.0
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
CURRENT_PRODUCT_CANDIDATE_HEAD=d48b4464200b321dc309fbebcd56021f08709ba4
UX_HF1_AUTOMATED_RUN=38924219467
QA_PHP_8_3=PASS
QA_PHP_8_4=PASS
QA_PHP_8_5=PASS
REMOTE_WRITES=OFF
REAL_MELI_HTTP=0
HOSTINGER_UI_DEPLOYED=NO
```

## 2. CLOSED TECHNICAL / BROWSER WORK

```text
UI-DM1..UI-DM10-A=GREEN
UI-DM10-B1=GREEN
UI-DM10-B2A=GREEN
UI-DM10-B2B=GREEN
UI-DM10-B2C1 FINANCIAL CONFLICT=GREEN
```

B2C1 factual authority remains:

```text
SOURCE_HEAD=261812f112f29b7ae026f1f5605e94cc03fd4eb2
CHECKPOINT_CI_RUN=38917160256
CHECKPOINT_CI_RESULT=PASS
CONFLICT=preserved
UNKNOWN=preserved
ZERO_SUBSTITUTION=NO
READ_ONLY_SIDE_EFFECTS=0
```

## 3. HUMAN-FIRST UX AUTHORITY

User explicitly requires the ERP to be human, easy to understand, useful, well composed and not an infinite spreadsheet/debug console.

External UX reference:

```text
https://github.com/nextlevelbuilder/ui-ux-pro-max-skill
```

Persisted project authority:

```text
docs/UX_HUMAN_FIRST_UI_UX_PRO_MAX_AUDIT_2026-10-16.md
AUDIT_COMMIT=04da1926a13818be41403268b01d089359ae2e23
```

Human-First laws:

```text
RAW_CODE != PRIMARY_LABEL
DECISION_FIRST=YES
PROGRESSIVE_DISCLOSURE=REQUIRED
EMPTY_TABLE_CHROME=FORBIDDEN
SINGLE_RECORD_EXCEL_LAYOUT=AVOID
TECHNICAL_DETAILS=SECONDARY/ON_DEMAND
TRUTH_SEMANTICS=MUST_NOT_CHANGE
```

## 4. UX-HF1 — SALE DETAIL

### RED — CERTIFIED

```text
RED_COMMIT=be47c72af209757ba51f8c7c0aa9250ab7ae42eb
RED_RUN=38921803037
PHP_8_3=FAIL expected
PHP_8_4=FAIL expected
PHP_8_5=FAIL expected
SYNTAX=PASS
PHPSTAN=PASS
ONLY_FAILURE=SalesDetailHumanFirstUiTest
RED_VALID=YES
```

### GREEN — AUTOMATED CERTIFIED

Human-First production commits:

```text
b2d5c8c5021bd88450f9758f2984bf39c43b6578 = initial Human-First Sale Detail composition
d12423174613ff99e6835c28f3e8134bf2a8fb9f = reuse existing Design Master components; no new CSS family
2ab34d80cd196dd5dedfdad07b4c8c2a2eef4814 = preserve human "Factura / Postventa" contract
d48b4464200b321dc309fbebcd56021f08709ba4 = preserve historical Reconciliación contract inside technical disclosure
```

Test alignment:

```text
9aca934849cdb9bbce25aaaefb2bcc242fc1f183 = align obsolete Sale Detail table assertions with Human-First layout
```

Final automated certification:

```text
RUN=38924219467
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
UX_HF1_AUTOMATED_GREEN=YES
```

The intermediate run `38923697594` had exactly 3 compatibility failures because old tests required literal `Reconciliación`. Root fix was not to restore machine-first UI; the technical disclosure now contains the explicit heading `Reconciliación técnica`.

### CURRENT PRESENTATION CONTRACT

```text
paid -> Pagada in primary UI
CONFLICT -> Requiere revisión in primary UI
UNKNOWN -> Sin información suficiente in primary UI
raw CONFLICT/UNKNOWN remain in technical disclosure
financial conflict gets a human attention summary
products render as compact records instead of the normal-case mini-table
empty Billing does not render empty table chrome
Factura/Postventa primary states are humanized
raw readiness/post-sale states remain under Ver estados técnicos
technical reconciliation remains under Ver conciliación técnica
backend owners/queries/Financial semantics unchanged
no CSS framework/new dependency
no JS change
no route change
no migration
```

## 5. NEXT — UX-HF1 REAL BROWSER HUMAN-COMPREHENSION QA

```text
RESUME_EXACTLY_FROM=UX-HF1 REAL BROWSER HUMAN-COMPREHENSION QA
```

Required real-browser checks before HF1 may close:

```text
VIEWPORTS=1440x900 + 390x844
SCENARIO_A=normal known-fee sale
SCENARIO_B=financial conflict sale
PRIMARY_5_TO_10_SECOND_COMPREHENSION=PASS required
PRIMARY_RAW_MACHINE_CODES=NO
PROGRESSIVE_DISCLOSURE=works
EMPTY_BILLING_TABLE_CHROME=NO when zero rows
PRODUCT_INTERNAL_HORIZONTAL_SCROLL=NO for ordinary 1–5 items
PAGE_HORIZONTAL_OVERFLOW=NO
MOBILE_BOTTOM_NAV_OVERLAP=NO
CONSOLE_APP_ERRORS=NONE
CAPTURED_HTTP_5XX=NONE
READ_ONLY_SIDE_EFFECTS=0
```

Browser QA must judge composition, hierarchy and human comprehension — not just whether HTML technically renders.

If a visual/comprehension defect is found:

```text
CAPTURE EVIDENCE -> STOP -> open one focused RED/GREEN microblock
```

Do not continue automatically to HF2.

## 6. AFTER UX-HF1

Only after browser PASS + persisted checkpoint:

```text
UX-HF2 Dashboard
UX-HF3 Sales List / historical audit
UX-HF4 Catalog + Inventory workflow
UX-HF5 Billing
UX-HF6 MELI/Admin/settings focused audit
UX-HF7 cross-page responsive/human-comprehension audit
THEN resume factual gate UI-DM10-B2C2 Inventory missing-vs-zero
```

## 7. FROZEN

```text
UI-DM10-B2C2 INVENTORY MISSING-VS-ZERO=FROZEN
UX-HF2 DASHBOARD=NOT_STARTED
UX-HF3 SALES LIST=NOT_STARTED
UX-HF4 CATALOG/INVENTORY=NOT_STARTED
UX-HF5 BILLING=NOT_STARTED
UX-HF6 MELI/ADMIN=NOT_STARTED
UX-HF7 CROSS-PAGE AUDIT=NOT_STARTED
```

## 8. BACKEND GUARDRAILS STILL CLOSED

```text
429 durable defer/cooldown
Work durable execution
no inline retry storm
no FIFO/HOL resurrection
no child/page Work explosion
OAuth lifecycle
company/account scoping
Financial UNKNOWN/CONFLICT semantics
Billing cursor/period behavior
REMOTE_WRITES=OFF
```

## 9. ABSOLUTE NO-GO

```text
NO B2C2 before Human-First sequence authorizes it
NO UX-HF2 before UX-HF1 browser checkpoint
NO Hostinger
NO cron
NO real MELI mutation
NO REMOTE_WRITES
NO F15
NO F16
NO merge PR #19
NO frontend framework migration
NO UI UX Pro Max runtime dependency
```

## 10. STOP-SAFE

```text
STOP_NOW=NO
PR_19=DRAFT / NOT MERGED
HUMAN_FIRST_AUDIT=ACTIVE_AUTHORITY
UX_HF1_RED=CERTIFIED
UX_HF1_AUTOMATED_GREEN=YES
UX_HF1_BROWSER_GREEN=NO
B2C2=FROZEN
DO_NOT_CLAIM_HF1_COMPLETE_BEFORE_BROWSER_PASS=YES
```
