# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=H4 SALES LIST HUMAN-FIRST — AUTOMATED GREEN / BROWSER QA NEXT / STOP-SAFE**

Autoridad operativa de recuperación. No depender del chat.

## Repository authority

```text
DATE=2026-10-16
REPO=sierraglobalcompany-rgb/ErpMeli2.0
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
BASE_BRANCH=ui/operator-shell-v1-20261014
BASE_SHA=c6f8af7f446cb327a905ae2f9bd9ee424c2d421f
H4_RED_CONTRACT_COMMIT=cc09222734b80a08e6aaab9639e4961984043dd1
H4_PRESENTATION_COMMIT=0e82d340adbf9deee663938629ceeb09812a8d56
H4_FIX1_OPERATOR_TEST_COMMIT=a051046ef0b3311d32a9e71e595a856ab2764b4f
H4_FIX1_HEAD=9f888c2f3f81c03c7f4e45c0ccb803d03a68f912
H4_FIX1_CI_RUN=38979090777
H4_FIX1_CI=completed / success
QA_PHP_8_3=PASS
QA_PHP_8_4=PASS
QA_PHP_8_5=PASS
LAST_FULL_BROWSER_CERTIFIED_PRODUCT=78e1ceb3073ffcc847c34cada7dafd550d3d86ce
REMOTE_WRITES=OFF
REAL_MELI_HTTP=0
HOSTINGER_DEPLOYED=NO
REAL_MELI_WRITES=NO
MERGE_AUTHORIZED=NO
STOP_SAFE=YES
```

## Required laws

Read before continuing:

```text
AGENTS.md
docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md
design-system/erp-meli-2-0/MASTER.md
docs/UX_HUMAN_FIRST_MASTER_AUDIT_AND_IMPROVEMENT_PLAN_2026-10-16.md
```

KISS / anti-noise / anti-patch remain mandatory. UNKNOWN never becomes 0. No Hostinger, cron, MELI real, merge, F15/F16 or retry-storm architecture.

## Certified history

H1-FIX1, H2A, H2B1/H2B2 and H3 are closed GREEN. H3 remains the last product with complete automated + real-browser certification.

```text
H3_PRODUCT_HEAD=78e1ceb3073ffcc847c34cada7dafd550d3d86ce
H3_CI=38968197740
H3_BROWSER=PASS desktop 1440x900 / mobile 390x844
```

## H4 — Sales List Human-First

### Contract and presentation

H4 keeps the ordinary sales workflow primary and historical audit secondary.

`0e82d340adbf9deee663938629ceeb09812a8d56` changes only `app/Modules/Sales/ViewSales/views/list.php` presentation:

- intro: `Consulta y revisa las ventas de esta empresa.`;
- recent/search sales before historical audit;
- historical audit inside native `<details class="panel sales-audit-disclosure">`;
- sale states use human labels and preserve raw tokens in `data-sale-status`;
- audit states use human labels and preserve raw tokens in `data-audit-status`;
- missing values remain missing/indeterminate, never zero;
- no backend, route, Work, Billing, Financial, migration or authorization owner change.

Human audit labels currently include:

```text
capturing -> Recopilando datos
repairing -> Completando datos
confirming -> Verificando resultados
valid -> Sin diferencias
attention -> Requiere revisión
unavailable -> No disponible
```

### H4 initial full QA RED — resolved

Run `38977876101` exposed exactly three legacy UI assertions that still required raw `<td>CAPTURING</td>` / `<td>VALID</td>` output.

H4-FIX1 corrected only tests:

```text
tests/Integration/OperatorFoundationTest.php
tests/Integration/WorkCliEntrypointTest.php
```

The assertions now validate the canonical Human-First DOM contract:

- raw states through `data-audit-status`;
- human labels as visible copy;
- 25-row audit bound using `data-audit-status="valid"` rather than raw visible cell text;
- existing company scoping, Work counts and simulator-call assertions remain intact.

Diff from the prior checkpoint to H4-FIX1 head is tests-only:

```text
OperatorFoundationTest.php: +16 / -5
WorkCliEntrypointTest.php: +3 / -1
```

### H4 automated certification

Exact-head GitHub Actions:

```text
HEAD=9f888c2f3f81c03c7f4e45c0ccb803d03a68f912
RUN=38979090777
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
WORKFLOW=completed / success
```

Automated H4 status:

```text
H4_AUTOMATED_GREEN=YES
PRODUCT_PRESENTATION_REVERTED=NO
RAW_STATES_PRIMARY=NO
HUMAN_LABELS_PRIMARY=YES
BACKEND_CHANGED=NO
ROUTES_CHANGED=NO
WORK_CHANGED=NO
FINANCIAL_CHANGED=NO
BILLING_CHANGED=NO
MIGRATIONS_CHANGED=NO
```

## Next exact microblock

```text
NEXT=H4-BROWSER REAL-BROWSER HUMAN-FIRST CERTIFICATION
SCOPE=/sales only; desktop + mobile; normal sales + historical audit disclosure
PRODUCT_CODE_CHANGES=NO
TEST_CHANGES=NO
EXPECTED_TOOL=Codex/local browser only because ChatGPT cannot control the local runtime/browser from this chat
REQUIRED_VIEWPORTS=1440x900 / 390x844
REQUIRED_CHECKS=primary sales hierarchy / human sale status / audit collapsed by default / audit opens by keyboard / human audit statuses / raw data-* preserved / no page overflow / five-slot mobile nav / skip-link smoke / no console errors / no app 5xx / no read side effects
FIRST_DEFECT_RULE=STOP; diagnose only; no same-run patch
H5_PLUS=FROZEN
STOP_SAFE=YES
RESUME_EXACTLY_FROM=H4-BROWSER
```

H4 must NOT be marked fully closed until real-browser certification passes on the exact automated-GREEN H4 head or an explicitly documented equivalent checkout.

## Frozen / prohibited

No broad redesign, framework, duplicate dataset, fake routes/data, Hostinger, cron, real MELI mutation, PR merge, weakening of catalog disposable-DB guard, retry storms, patch-chain CSS or UNKNOWN→0.
