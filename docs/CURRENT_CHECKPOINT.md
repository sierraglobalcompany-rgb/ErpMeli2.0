# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=H4 SALES LIST HUMAN-FIRST — IMPLEMENTATION PRESENT / FULL QA RED / STOP-SAFE**

Este archivo es la autoridad operativa para recuperar el trabajo sin depender del chat.

## Repository authority

```text
DATE=2026-10-16
REPO=sierraglobalcompany-rgb/ErpMeli2.0
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
BASE_BRANCH=ui/operator-shell-v1-20261014
BASE_SHA=c6f8af7f446cb327a905ae2f9bd9ee424c2d421f
LAST_CERTIFIED_GREEN_PRODUCT_HEAD=78e1ceb3073ffcc847c34cada7dafd550d3d86ce
LAST_CERTIFIED_GREEN_CI=38968197740
H4_RED_CONTRACT_COMMIT=cc09222734b80a08e6aaab9639e4961984043dd1
H4_IMPLEMENTATION_COMMIT=0e82d340adbf9deee663938629ceeb09812a8d56
H4_IMPLEMENTATION_CI_RUN=38977876101
H4_IMPLEMENTATION_CI=FAIL
QA_PHP_8_3=FAIL
QA_PHP_8_4=FAIL
QA_PHP_8_5=FAIL
REMOTE_WRITES=OFF
REAL_MELI_HTTP=0
HOSTINGER_DEPLOYED=NO
REAL_MELI_WRITES=NO
MERGE_AUTHORIZED=NO
STOP_SAFE=YES
```

## Laws / guardrails

- KISS: simple, eficiente, optimizado y eficaz; A→B, sin arquitectura innecesaria.
- Anti-ruido: KEEP / MERGE / OPTIMIZE / REMOVE / DEFER con evidencia.
- Anti-parches: corregir dueño/causa raíz; no apilar workarounds.
- UNKNOWN nunca se convierte en 0.
- No Hostinger, cron, MELI real, merge ni F15/F16 sin autorización explícita.
- Mantener durable defer/cooldown para 429; sin inline retry storms ni explosión de Work.

## Certified history retained

H1-FIX1, H2A, H2B1/H2B2 y H3 quedaron cerrados antes de iniciar H4. El último producto completamente certificado sigue siendo:

```text
H3_PRODUCT_HEAD=78e1ceb3073ffcc847c34cada7dafd550d3d86ce
H3_CI=38968197740
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
```

H3 cerró Dashboard Human-First sin cambiar owners de negocio, preservando UNKNOWN, separación de monedas y estados humanos.

## H4 — Sales List Human-First

### Intento RED

Commit `cc09222734b80a08e6aaab9639e4961984043dd1` agregó el contrato integrado de presentación para `/sales`:

- ventas normales siguen siendo el contenido principal;
- auditoría histórica queda secundaria/colapsable;
- estados de ventas deben mostrarse en lenguaje humano;
- estados de auditoría deben mostrarse en lenguaje humano;
- los tokens crudos se preservan como metadata `data-*`, no como copy principal;
- no se cambia backend, rutas, Work, Financial ni owner de datos.

La primera CI confirmó RED porque la vista todavía mostraba copy técnico y estados crudos.

### Implementación actual

Commit `0e82d340adbf9deee663938629ceeb09812a8d56` cambió únicamente la presentación de `app/Modules/Sales/ViewSales/views/list.php` para:

- introducción humana: `Consulta y revisa las ventas de esta empresa.`;
- lista de ventas antes de auditoría;
- auditoría dentro de `<details class="panel sales-audit-disclosure">`;
- estados de venta humanos, conservando `data-sale-status`;
- estados de auditoría humanos, conservando `data-audit-status`;
- sin convertir datos faltantes en cero;
- sin crear rutas, owners, colas ni métricas nuevas.

### Full QA actual — RED conocido

GitHub Actions run `38977876101` terminó en FAIL en PHP 8.3, 8.4 y 8.5.

En PHP 8.3 el suite completó `812 tests` y expuso exactamente 3 fallos de contrato histórico de UI:

1. `OperatorFoundationTest::testAuditStartAndStoredResultsAreVisibleWithoutProcessingWork`
   - todavía espera `<td>CAPTURING</td>` y otros tokens crudos.
2. `OperatorFoundationTest::testAuditResultsAreBoundedAndScoped`
   - cuenta `substr_count($html, '<td>VALID</td>')`, incompatible con la nueva etiqueta humana + metadata.
3. `WorkCliEntrypointTest::testOperatorAuditThroughRealCliPersistsAndDisplaysResult`
   - todavía espera `<td>VALID</td>`.

No se ha demostrado un defecto de backend ni de persistencia por estos tres fallos. Son assertions antiguas que contradicen el contrato H4 Human-First ya definido. No deben restaurarse los tokens crudos como copy principal para hacer pasar esos tests.

## Next exact microblock

```text
NEXT=H4-FIX1 ALIGN LEGACY UI ASSERTIONS WITH HUMAN-FIRST CONTRACT
SCOPE=TESTS ONLY unless new evidence proves a product defect
EXPECTED_FILES=tests/Integration/OperatorFoundationTest.php; tests/Integration/WorkCliEntrypointTest.php
DO_NOT_TOUCH=backend owners / routes / Work / Financial / Billing / migrations / auth guards
METHOD=RED evidence already present -> minimal test alignment -> focused GREEN -> full composer QA -> exact-head GitHub Actions 8.3/8.4/8.5 -> audit -> checkpoint
BROWSER_QA=NO until full automated QA is GREEN
H5_PLUS=FROZEN
STOP_SAFE=YES
RESUME_EXACTLY_FROM=H4-FIX1
```

### Required acceptance for H4-FIX1

- Keep human labels as the primary visible copy.
- Assert raw states through `data-sale-status` / `data-audit-status` only where technically required.
- Preserve the bounded 25-audit behavior using the new DOM contract, not raw-token cell markup.
- Preserve company scoping, read-only GET behavior and Work counts.
- Focused tests GREEN.
- Full `composer qa` GREEN.
- GitHub Actions exact-head matrix PHP 8.3 / 8.4 / 8.5 GREEN.
- Only after that may H4 be marked closed and browser QA continue.

## Frozen / prohibited

No broad redesign, no framework, no duplicate dataset, no fake data/routes, no Hostinger, no cron, no real MELI mutation, no PR merge, no weakening of catalog disposable-DB guard, no retry storms, no patch-chain CSS, no UNKNOWN→0.
