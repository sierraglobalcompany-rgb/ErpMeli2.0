# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=DESIGN MASTER APPROVED / UI-DM1..10-A GREEN / NEXT UI-DM10-B REAL BROWSER QA / STOP-SAFE**

> Autoridad operativa para recuperar el proyecto si el chat se interrumpe. Retomar exactamente desde `RESUME_EXACTLY_FROM`; no reconstruir por intuición.

## 1. REPOSITORY AUTHORITY

```text
DATE=2026-10-16
REPO=sierraglobalcompany-rgb/ErpMeli2.0
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
LATEST_CERTIFIED_BRANCH_HEAD=a4d74349e5e28e4013a952bd47ea70ef12e1dd9a
LATEST_CERTIFIED_BRANCH_RUN=38836597229
QA_PHP_8_3=PASS
QA_PHP_8_4=PASS
QA_PHP_8_5=PASS
REMOTE_WRITES=OFF
REAL_MELI_HTTP=0 for this development work
HOSTINGER_UI_DEPLOYED=NO
```

## 2. EXECUTION DISCIPLINE

```text
KISS=A→B
ANTI_NOISE=KEEP/MERGE/OPTIMIZE/SIMPLIFY/REMOVE only with evidence
ANTI_PATCH=root owner fix only
MICROBLOCK=RED → GREEN → full CI → audit → checkpoint → next
CHECKPOINT_CADENCE=small blocks; persist frequently so the project is recoverable if chat ends
```

## 3. CLOSED BLOCKS

```text
UI-DM1 FOUNDATION=GREEN
UI-DM2 FACTUAL DASHBOARD=GREEN
R3 DIRECT HOME=GREEN
UI-DM3 SALES LIST+DETAIL=GREEN
UI-DM4 PRODUCTS/CATALOG+INVENTORY=GREEN
UI-DM5 BILLING/FINANCIAL=GREEN
UI-DM6A MELI ACCOUNTS=GREEN
UI-DM6B MELI APP SETTINGS=GREEN
UI-DM7 ADMINISTRATION=GREEN
UI-DM8 COMPLETENESS AUDIT=GREEN
UI-DM9 FULL AUTOMATED QA=GREEN
UI-DM10-A1 LAUNCHER KEYBOARD ACCESSIBILITY=GREEN
UI-DM10-A2.1 INITIAL SHEET FOCUS TRAP=GREEN
UI-DM10-A2.2 MOBILE SAFE-AREA=GREEN
UI-DM10-A STATIC VISUAL/RESPONSIVE AUDIT=GREEN
```

## 4. UI-DM10-A CERTIFICATION SUMMARY

### A1 — launcher keyboard accessibility

```text
GREEN_HEAD=7f4312e0d828bb7007a384166e4ddf490003b646
GREEN_RUN=38834828976
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

### A2.1 — initial sheet focus trap

```text
GREEN_HEAD=503181a181c40dd2d6f1be2685bb61dff0b23081
GREEN_RUN=38835728947
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

### A2.2 — mobile safe-area

```text
GREEN_HEAD=a4d74349e5e28e4013a952bd47ea70ef12e1dd9a
GREEN_RUN=38836597229
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

## 5. FINAL STATIC AUDIT VERDICT

```text
RESULT=GREEN
EVIDENCE_BACKED_SOURCE_DEFECTS_REMAINING=0
REAL_BROWSER_CERTIFICATION=NOT YET DONE
```

Verified statically:
- desktop/mobile/tablet remain one product and one design system;
- mobile bottom navigation exposes exactly five approved slots: Inicio, Operación, Apps, Productos, Mercado Libre;
- Mercado Libre becomes disabled instead of fabricating a route when the current role has no valid owner;
- Apps launcher exposes only real authorized destinations;
- no deferred fake destinations for Logística, Postventa, Vinculación, Conciliación, Rentabilidad or API Health;
- ESC closes the launcher and focus returns to the opening control;
- focus trap handles backdrop exclusion, initially focused sheet, first/last wrap and visible keyboard focus;
- responsive tables use one shared `table[data-responsive-table] → [data-mobile-card]` system;
- one responsive CSS family is used; no second mobile/tablet architecture exists;
- `safe-area-inset-bottom` is handled centrally for content clearance, fixed bottom nav and launcher;
- CSS contains only one `!important`, restricted to `.apps-launcher[hidden]` so the hidden overlay cannot be forced visible;
- no frontend framework was added;
- no backend/Work/Billing/Financial/MELI core changes were introduced by DM10-A.

No real-browser visual claim is made from this static audit.

## 6. NEXT — UI-DM10-B REAL BROWSER QA

```text
NEXT=UI-DM10-B REAL BROWSER QA
```

This is the capability boundary where actual rendered evidence is required.

Approved viewport matrix:

```text
390x844
430x932
768x1024
1024x768
1366x768
1440x900
1920x1080
```

Required browser evidence:
1. login and authenticated shell render;
2. Dashboard;
3. Sales list and detail;
4. Products/Catalog + Inventory;
5. Billing/Financial presentation;
6. Mercado Libre accounts and global settings according to role;
7. Companies, Users, System Settings;
8. mobile exact bottom nav and Apps launcher interaction;
9. keyboard launcher: open, Tab, Shift+Tab, ESC and focus return;
10. responsive tables/cards with no primary horizontal-scroll requirement on mobile;
11. tablet is adaptive layout, not a third interface;
12. no clipped controls or bottom-nav/home-indicator overlap;
13. screenshots/evidence for approved viewport matrix;
14. fix only evidence-backed browser defects, one microblock at a time.

DM10-B does not authorize demo data creation, Hostinger or real MELI.

## 7. QA AUTHORITY

```text
composer qa = PHP lint + PHPStan + full PHPUnit
phpunit.xml = entire tests/ directory
workflow = PHP 8.3 / 8.4 / 8.5 + MariaDB 11.4
REAL_MELI_HTTP=0
```

## 8. BACKEND GUARDRAILS STILL CLOSED

```text
429 durable defer/cooldown
Work durable execution
no inline retry storm
no FIFO/HOL resurrection
no child/page work explosion
OAuth lifecycle
company/account scoping
Financial UNKNOWN/CONFLICT semantics
Billing cursor/period behavior
REMOTE_WRITES=OFF
```

## 9. PRODUCT SEQUENCE

```text
UI-DM10-B real browser QA
→ ERP COMPLETE candidate
→ local demo
→ manual E2E
→ user approval
→ Hostinger
→ runtime certification
→ cron / Work Runner
→ real MELI READ-only gates
→ later explicitly authorized phases
```

## 10. ABSOLUTE NO-GO

```text
NO demo seed
NO Hostinger
NO cron
NO real MELI mutation
NO REMOTE_WRITES
NO F15
NO F16
NO merge PR #19
```

## 11. STOP-SAFE

```text
STOP_NOW=NO
RESUME_EXACTLY_FROM=UI-DM10-B REAL BROWSER QA
BROWSER_EVIDENCE_REQUIRED=YES
DO_NOT_CLAIM_ERP_COMPLETE_BEFORE_DM10_B=YES
DO_NOT_MERGE_PR_19=YES
```
