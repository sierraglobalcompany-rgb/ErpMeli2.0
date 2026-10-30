## CHECKPOINT ACTIVO 2026-10-30 — SNAPSHOT ÍNTEGRO DE FUENTES PENDIENTE / WARNINGS FOCALES

**Esta sección tiene precedencia sobre el resto del historial.** PR #21 `OPEN/DRAFT/NO MERGE` @ `83e5dda0a47d553bf79099ceec14290fcf564e8d`; G1-P1/G2-A/G2-B siguen **sin commits en candidato Windows aislado**. G2-B QA local: 973 tests / 8787 assertions / **2 warnings** / 1 skip / 0 fail/error; G2-A local 970/8639/1 skip; WIP histórico 8/8 verificados en checkout **separado**. G1 `uncertain_review=HOLD` sin política de reconciliación autorizada. Hostinger/F14–F16, HTTP real, financiero facturable y release `NOT_CERTIFIED`.

**Prioridad de riesgo y próxima acción diferenciada:** La evidencia portable anterior `ERP2_CHECKPOINT_PORTATIL_G1P1_G2A_POST_WIP_20261029.zip` era parcial, porque faltaba el fuente íntegro `tests/Integration/MeliListingsHttpRouteTest.php`. ChatGPT preparó ahora una HERRAMIENTA para exportar **las fuentes seleccionadas completas del candidato Windows** en un nuevo ZIP externo, sin modificar el repo: `ERP2_HERRAMIENTA_CHECKPOINT_G1P1_G2A_G2B_20261030.zip`, SHA256 `4a988ea8f8813baf422086c6c18177bb7d7343e67848203ed7424434f3c27a4c`, 11079 bytes, 3/3 manifiesto y ZIP CRC PASS. Incluye `erp2_export_candidate_readonly.py` (SHA256 `f0cc333a62f93f5b98ad354d8044f03c1cf134ec52535f1df6e659314988b35c`) y **ÚNICO prompt** `ERP2_PROMPT_PARA_CODEX_LUNA_SNAPSHOT_INTEGRO_DIAGNOSTICO_WARNINGS_20261030.md` (SHA256 `eaf91ae5bb1587b5822ac76c6994ccac5a402f4b6de610ec3eeddef317dad7b7`). Archivos disponibles **en conversación, no en GitHub**. Herramienta probada con Git sintético (fuentes tracked/untracked, manifest, no sobrescritura, exclusión .env), **NO EJECUTADA EN WINDOWS AÚN**. No es un backup de DB ni garantiza ausencia total de secretos; revisar antes de subir el ZIP de fuente.

**Diagnóstico PHPUnit:** el log original G2-B tiene marca `.W......... 819/973`: orientación aproximada al test ordinal 758; el log **no identifica ambos warnings**. La orden permite mapear sin ejecutar la suite completa y, si hay evidencia, máximo una clase/grupo focal en MariaDB desechable. Si no se reproduce, `WARNINGS_UNCLASSIFIED`; no borrar tests, no suprimir warnings, no cambiar producto.

**Prohibiciones actuales:** no reejecutar G2-B 973 tests ni browser, no reaplicar paquetes G1/G2-A/B, no escribir al WIP ni a GitHub funcional, no mover checkout ni commit/push/merge/deploy, no HTTP MELI real. El trabajo operativo local siguiente es **una sola exportación + diagnóstico focal**, bajo el prompt anterior. `FULL_SOURCE_SNAPSHOT_WINDOWS=NOT_RUN`; `G2B_LOCAL_SCOPE=PASS_WITH_WARNINGS`; `G2B_RELEASE_CERTIFIED=NO`; `NEXT=CODEX_LUNA_ONLY_FOR_LOCAL_EXPORT`. ChatGPT audita archivos devueltos y dirige después la siguiente fase.

## CHECKPOINT ACTIVO 2026-10-30 — G2-B PASS LOCAL CON WARNINGS / NO RELEASE

**PRECEDENCIA:** esta sección es la lectura activa. Todos los apartados de G2-B «preparado / no ejecutado» situados más abajo son HISTÓRICOS y quedaron superados por la entrega Codex `ERP2-G2B-CATALOGO-PAGINACION-READONLY-20261029`. Auditoría externa integral y límites: [ERP2_G2B_AUDITORIA_INDEPENDIENTE_Y_CHECKPOINT_20261030.md](ERP2_G2B_AUDITORIA_INDEPENDIENTE_Y_CHECKPOINT_20261030.md).

**G2B local:** mismo candidato Windows sin commit `C:\codex\ERP2-reports\ERP2-G1-HTTP-MANUAL-GLOBAL-PACE-20261028\candidate`, base PR21 `83e5dda0a47d553bf79099ceec14290fcf564e8d`. Delta propio 3 PHP y 3 tests de catálogo, más checkpoint; keyset id DESC, 100 filas / LIMIT 101, company_id aislado, cursor validado y navegación «Siguiente página» sin COUNT ni nuevas tablas. ZIP evidencia `ERP2-G2B-EVIDENCE.zip` SHA256 `b4d130d7b8e2902ab451b2fcd3edb8a4763e7e61fe34eb07a711b6e75a73b075`; informe SHA256 `37ce99dd02e85dd8febbab84a660faa7c037cab4de2b919687d02434c39fb07b`; ZIP CRC PASS y **26/26 SHA manifest PASS**, patch SHA256 `9be06af7ad33b8ba907f6fd25bfc02fa8497c80c2d9364da86192105e17d349b`. QA FINAL original **973 tests / 8787 assertions / 1 skipped / 2 warnings / 0 fails/errors**; PHPStan/Composer/lint PASS. Browser final 390×844 y 1440×900, 100+1 y back PASS focal. WIP histórico ocho hashes 8/8 coincidentes reportados en root original separado; G2-A unchanged reportado. **ChatGPT verificó manifest, diff y capturas pero no accede a checkout Windows en tiempo real.**

**Reserva L5:** 2 warnings PHPUnit respecto de 0 warnings en baseline G2-A; log bruto G2-B no identifica origen ni categoría. No afirmar «warning-free» ni declarar release: `G2B_LOCAL_SCOPE=PASS_WITH_WARNINGS`. RED inicial descrito pero log bruto ausente. Identificar warnings con diagnóstico focal antes de gate integración; **no repetir 973 tests sin hipótesis ni cambio de código**. Ver informe GitHub mencionado.

**Continuidad crítica:** PR21 `OPEN/DRAFT/NOT_MERGED` y HEAD arriba; GitHub funcional NO contiene G1-P1/G2-A/G2-B, que siguen sin commit en candidato Windows. ZIP portátil previo era parcial (faltaba fuente completa test G2-A) y ZIP G2-B solo contiene delta; antes de cualquier operación de integración/reconstrucción generar export íntegro saneado y verificable del candidato Windows sin tocar WIP. No enviar de nuevo prompts G2-A/B ejecutados.

**Bloqueos:** `G1_UNCERTAIN_REVIEW=HOLD/UNAPPROVED`, `G1_RELEASE_CERTIFIED=NO`, `G2A_RELEASE_CERTIFIED=NO`, `G2B_RELEASE_CERTIFIED=NO`, `F14_F15_F16=NOT_RUN`, `REAL_MELI_HTTP=0`, `REMOTE_WRITES=OFF`, `MONTHLY_REPORT_FACTURABLE=NO`. `NEXT_ACTOR=CHATGPT` para priorizar gates de integración y producto, sin autorizar commit/push/merge/deploy de oficio.

## PUNTO DE ENTRADA ACTUAL — G2-A COMPLETADO LOCAL / G2-B PREPARADO — 29-OCT-2026

**PRECEDENCIA:** esta sección es la entrada activa; todo lo que sigue es histórico y puede contener estados G2-A anteriores. PR #21 OPEN/DRAFT, HEAD `83e5dda0a47d553bf79099ceec14290fcf564e8d`, **sin el código local G1-P1 ni G2-A**. El candidato Windows `C:\codex\ERP2-reports\ERP2-G1-HTTP-MANUAL-GLOBAL-PACE-20261028\candidate` contiene cambios locales no versionados y G2-A `PASS_SCOPE_ONLY` (970 tests/8639 assertions/1 omitido/0 fallos; navegador 390×844 y 1440×900; NO live). Ocho WIP verificados 8/8 en root histórico **separado**. G1 `uncertain_review=HOLD`, resolución sin aprobar; release, F14–16 y facturación real NO certificados.

**Checkpoint externo de recuperación:** `ERP2_CHECKPOINT_PORTATIL_G1P1_G2A_POST_WIP_20261029.zip`, SHA-256 `a8c4c704d684a1f5e0bcddd4ce3be336a541a5a2b94f488388955b65cdaade24`, 791898 bytes, ZIP CRC PASS, manifiesto 27/27 SHA PASS, preparado por ChatGPT a partir de evidencias adjuntas. **NO está subido a GitHub**: conservar el ZIP descargado desde la conversación. Incluye patches G1-P1 y G2-A, untracked conocidos, logs, capturas, informes WIP, ZIPs primarios. **No es copia 100% del checkout**: falta el contenido fuente completo de `tests/Integration/MeliListingsHttpRouteTest.php` y no se hizo restore de integración en este turno; README declara los límites. No aplicar sobre candidato existente.

**Próximo cambio útil G2-B (propuesto; NO ejecutado):** navegación estable por cursor en catálogo local, hoy `ProductRepository::listRecentForCompany` y `searchForCompany` usan `LIMIT 100` sin «siguiente». ChatGPT contrastó repo y preparó `ERP2_PROMPT_CODEX_LUNA_G2B_CATALOGO_PAGINACION_UNICO_20261029.md`, SHA256 `701dcaa51d072025528a29a47c97588fbcffae8c4f2064cd964f546c735edb66` (solo archivo de conversación, **NO enviado/ejecutado**). Preflight en el candidato Windows y diseño `catalog.php` del WIP histórico (solo lectura); **no escribir sobre WIP**, no agregar tablas, no tocar G1/G2-A. Un único lote de implementación y QA si se entrega el prompt a Codex. No empezar otra auditoría G2-A ni prometer que G2-B ya está implementado.

## Checkpoint local — ERP2-M02-UX01-MOBILE-VIEWPORT-20261024

Corrige únicamente el scroll móvil de la preview sintética M02. En <=1023px el `<main>` marcado ocupa el espacio entre topbar y navegación fija; la rueda, teclado y `scrollIntoView` desplazan ese mismo scrollport. Escritorio conserva el flujo normal.

```text
TASK=ERP2-M02-UX01-MOBILE-VIEWPORT-20261024
BASE_SHA=3f1bd2973ad97f30945811b1e25a5c1e36c6375c
BASE_PARENT=a20f4c593b1cdd85eb237370bde31810b0d15570
FILES=app/Modules/Settings/views/meli-listings-preview.php; public/assets/app.css; tests/Integration/MeliListingPreviewHttpRouteTest.php; docs/CURRENT_CHECKPOINT.md
RED=PASS_expected_missing_preview_marker
GREEN=PASS_scoped_main_scrollport_and_test_contract
BROWSER=PASS_390x844_430x932_768x900_1440x900
SCROLL=PASS_single_main_scrollport_root_fixed_no_overlap_no_horizontal_overflow
KEYBOARD=PASS_skip_link_visible_offscreen_pagination_focus_visible_no_trap
DETAILS=PASS_native_keyboard_disclosure
APPS=PASS_open_close_escape_focus_return
NOTICE_PAGINATION=PASS_scrollIntoView_complete_visible_inside_main
TOUCH=NOT_AVAILABLE_playwright_context_maxTouchPoints_0
HTTP=loopback_only; CONSOLE_ERRORS=0; REAL_MELI_HTTP=0
QA=PASS_22_TESTS_223_ASSERTIONS; PHPSTAN_PASS; PHP_LINT_CHANGED_PHP_PASS; DIFF_CHECK_PASS
DATABASE=DISPOSABLE_MARIADB_11.4_LOOPBACK_TMPFS
UX01_LOCAL_PASS=YES
M02_UI_PRODUCTION_READY=NO; M02_FULLY_CLOSED=NO; PRODUCT_READY=NO
COMMIT=LOCAL_ONLY (RESULT_SHA_IN_FINAL_REPORT)
PUSH_MERGE_DEPLOY=NONE
NEXT=CHATGPT_VERIFICA_EVIDENCIA_UX01
```

## Checkpoint local — ERP2-M02-UI-FIXTURE-TENANT-HTTP-BROWSER-20261024

Integra únicamente una vista de publicaciones sintéticas para QA local. La ruta existe solo con `APP_ENV=test`, exige administrador activo y cuenta conectada de la empresa seleccionada, y no consulta al proveedor ni escribe en ERP.

```text
TASK=ERP2-M02-UI-FIXTURE-TENANT-HTTP-BROWSER-20261024
BASE_SHA=a20f4c593b1cdd85eb237370bde31810b0d15570
ROUTE=/__test/m02/listings-preview (APP_ENV=test only; no shell/mobile/apps link)
OWNER=CompanyContext admin + MeliListingReadScope.connectedSeller
FIXTURES=static per-account; no MeliClient.readPage/token/OAuth/network
RED=PASS_expected_404_before_route
QA=PASS_50_TESTS_725_ASSERTIONS; PHPSTAN_PASS; PHP_LINT_CHANGED_PHP_PASS; DIFF_CHECK_PASS
BROWSER=PASS_LOCAL_CHROMIUM_1440x900_430x932_390x844; mobile_cards; zero_horizontal_overflow; primary_action_above_mobile_nav
AUTH_FILTER_PAGING_XSS=PASS; account_switch_resets_offset_filter; keyboard_focus_visible
MOBILE_NAV_OVERLAP=RED_REPRODUCED_THEN_GREEN_390x844_430x932
HTTP_EXTERNAL_REQUESTS=0; console_errors=0; REAL_MELI_HTTP=0
DB_GET_ONLY=PASS_hash_equal_for_meli_accounts,meli_tokens,work_items,api_usage_daily,products,product_inventory
L1_KISS=PASS; L2_ANTI_NOISE=PASS; L3_CAUSE_ROOT=PASS; L4_HUMAN_FIRST=PASS; L5_CHATGPT_INDEPENDENT_AUDIT_PENDING; L6_SINGLE_BATCH=PASS
M02_UI_PRODUCTION_READY=NO; M02_FULLY_CLOSED=NO; PRODUCT_READY=NO
F14=CLOSED; F16=CLOSED; REMOTE_WRITES=OFF
COMMIT=LOCAL_ONLY (SHA_IN_FINAL_REPORT)
PUSH_MERGE_DEPLOY=NONE
CHECKPOINT=PERSISTED
NEXT=CHATGPT_AUDITA_UI_FIXTURE_INTEGRADA_M02
```

---
## Checkpoint local — ERP2-M02-SECURE-LISTING-PAGE-20261024

Integra un consumidor offline, de página única y limitado a 1000 resultados. Reutiliza CompanyContext, PDO, MeliClient y MeliClientResponse; sigue sin ruta, UI, token persistido ni llamada real a Mercado Libre.

```text
TASK=ERP2-M02-SECURE-LISTING-PAGE-20261024
REPO=sierraglobalcompany-rgb/ErpMeli2.0
BRANCH=codex/l4-m03-m04-qa-gate-repair
BASE_SHA=da57f42222859c1c98aeb58deaadafb8a0e58e43
PATCH_SHA256=7c292d3a01bc609162864c8b8d80d90bf11068b90cb59289cdcf62fee5af8950
FILES=app/Modules/Settings/MeliListingReadScope.php; tests/Integration/MeliListingReadScopeTest.php; docs/CURRENT_CHECKPOINT.md
RED=PASS_expected_missing_class_before_GREEN
GREEN_AND_REGRESSION=PASS_34_TESTS_250_ASSERTIONS_ZERO_FAILURES
PHP_LINT=PASS; PHPSTAN_FULL=PASS; DIFF_CHECK=PASS
DB=DISPOSABLE_MARIADB_11.4_LOOPBACK_TMPFS_NO_HOST_MOUNTS_REMOVED
REAL_MELI_HTTP=0; OAUTH=NONE; REMOTE_WRITES=OFF
ROUTE_UI_TOKEN_PERSISTENCE_MIGRATION_WORK=NONE
L1_KISS=PASS; L2_ANTI_NOISE=PASS; L3_ANTI_PATCH=PASS; L4_UI=NO_APLICA
L5=INDEPENDENT_CHATGPT_AUDIT_PENDING; L6=PASS
M02_COMPONENT=LOCAL_ONLY; M02_FULLY_CLOSED=NO; M02_UI_READY=NO; PRODUCT_READY=NO
COMMIT=LOCAL_ONLY (SHA_IN_FINAL_REPORT)
PUSH_MERGE_DEPLOY=NONE
CHECKPOINT=PERSISTED
NEXT=CHATGPT_AUDITA_M02_SECURE_LISTING
```

---
## Checkpoint local — ERP2-M02-OFFLINE-READ-CONTRACT-INTEGRATION-20261024

Integra únicamente el contrato offline de búsqueda de publicaciones y lectura bulk en el clon E. Reutiliza `MeliClient`, `MeliClientResponse`, el decoder y MariaDB; no activa consumidor, ruta, sincronización, persistencia ni tráfico externo. El audit independiente de M01 sigue pendiente y no se repitió.

```text
TASK=ERP2-M02-OFFLINE-READ-CONTRACT-INTEGRATION-20261024
REPO=sierraglobalcompany-rgb/ErpMeli2.0
BRANCH=codex/l4-m03-m04-qa-gate-repair
BASE_SHA=70fa51d4a2424802aee9098345d9eb9edd06f843
PATCH_SHA256=6151984fbc1f2d0e2c0b86f8805e56dc4a2278c63c92351499836ebf1913782c
CONTRACT=users.items.search+items.bulk_READ_OFFLINE
IMPLEMENTATION=existing_MeliClient+MeliClientResponse+LosslessJsonDecoder
NEW_ROUTES_CONSUMERS_TABLES_MIGRATIONS_WORK=NONE
RED=PASS_registry_contract_absent_before_GREEN
GREEN_AND_REGRESSION=PASS_91_TESTS_971_ASSERTIONS_ZERO_FAILURES
PHP_LINT=PASS; PHPSTAN_FULL=PASS; DIFF_CHECK=PASS
REAL_MELI_HTTP=0; REAL_OAUTH=NONE; REMOTE_WRITES=OFF
DATABASE=DISPOSABLE_MARIADB_LOOPBACK_TMPFS_NO_MOUNTS_REMOVED
L1_KISS=PASS; L2_ANTI_NOISE=PASS; L3_ANTI_PATCH=PASS; L4_UI=NO_APLICA
L5=CHATGPT_INDEPENDENT_AUDIT_PENDING; L6=PASS
M01_AUDIT=CHATGPT_PENDING_NOT_REEXECUTED
M02_FULLY_CLOSED=NO; M02_UI_READY=NO; PRODUCT_READY=NO
COMMIT=LOCAL_ONLY (SHA_IN_FINAL_REPORT)
PUSH_MERGE_DEPLOY=NONE
CHECKPOINT=PERSISTED
NEXT=CHATGPT_AUDITA_M02_OFFLINE_CONTRACT
```

---

## Checkpoint local — ERP2-L6-M01-CONSOLIDADO-20261024

Cierra este lote local de búsqueda interna en Catálogo y persistencia Ley 6 en el clon. No declara readiness general; queda pendiente auditoría independiente.

```text
TASK=ERP2-L6-M01-CONSOLIDADO-20261024
REPO=sierraglobalcompany-rgb/ErpMeli2.0
BRANCH=codex/l4-m03-m04-qa-gate-repair
BASE_B=d3ff1d2988544aa7dc545ab90298094a9ec807a6
COMMIT_C=3e969f4f27f331d989096ae642f15e9aaedea955
COMMIT_D=THIS_CHECKPOINT_COMMIT
L6=PASS; ROOT_AGENTS_ONLY; NEW_READ_ONLY_SESSIONS_ROOT_AND_CATALOG=PASS
M01=PASS; COMPANY_SCOPED_READ_ONLY_SEARCH_SKU_NAME_MAX_100
RED=PASS; 3_EXPECTED_FUNCTIONAL_FAILURES; GREEN_FOCAL=38_TESTS_398_ASSERTIONS
FULL_QA=866_TESTS_7631_ASSERTIONS_1_SKIP_ZERO_FAILURES
PHP_LINT=PASS; PHPSTAN=PASS; DIFF_CHECK=PASS
BROWSER=PASS_LOCAL; 1440x900_430x932_390x844; ZERO_HORIZONTAL_OVERFLOW
DATABASE=DISPOSABLE_MARIADB_LOOPBACK_TMPFS_NO_MOUNTS_REMOVED
REAL_MELI_HTTP=0; REMOTE_WRITES=OFF
PUSH_MERGE_DEPLOY=NONE
PRODUCT_READY=NO
CHECKPOINT=PERSISTED
NEXT=CHATGPT_INDEPENDENT_AUDIT
```

---
## Checkpoint local — ERP2-L4-M03-M04-INTEGRATION-GATE-20261023

Este candidato local integra únicamente Ley 4 documental, Dashboard M03, NAV-FOCUS M03 y Ventas multicuenta M04. Conserva íntegro el checkpoint histórico del clon M03/M04 debajo de esta nota.

```text
TASK=ERP2-L4-M03-M04-INTEGRATION-GATE-20261023
BASE_SHA=36ad68b3a2f4b0f62a27d6863afb47972cf00eda
REMOTE_REF=ui/design-master-v1-20261015; REMOTE_HEAD=36ad68b3a2f4b0f62a27d6863afb47972cf00eda
SOURCE_M03=codex/erp2-l4-m03-v2-dashboard; SOURCE_L4=codex/erp2-l4-v3-install-and-7-screen-audit
FILES=13 exact allowlisted paths; .playwright-cli and ignored runtime/cache state excluded
QA=results and exact candidate fingerprint in companion final report
REAL_MELI_HTTP=0; REMOTE_WRITES=OFF
COMMIT_PUSH_MERGE_DEPLOY=NONE
PRODUCT_READY=NO; H10_CLOSED=NO; INDEPENDENT_L5=CHATGPT_PENDING
NEXT_AFTER_LOCAL_GATES=CHATGPT_AUDITA_CANDIDATO_INTEGRADO
```

---
## Checkpoint local — ERP2-M04-SALES-CANONICAL-20261023

Este bloque cierra únicamente Ventas multicuenta en el clon aislado indicado. El historial M03 que sigue debajo se conserva íntegro; el CSS NAV-FOCUS no se modificó.

```text
TASK=ERP2-M04-SALES-CANONICAL-20261023
BASE_HEAD=36ad68b3a2f4b0f62a27d6863afb47972cf00eda
BRANCH=codex/erp2-l4-m03-v2-dashboard
WIP_M03=PRESERVED; Dashboard paid filter/table cards and existing CSS NAV-FOCUS retained
ORIGIN_NOTE=local clone path; expected GitHub remote ref not fetched or verified
SOURCE_ZIP_SHA256=3b137385763f42f4d95b2ef40051fadec958b294d49cb946386dfc3b7cb5913b; embedded=3ac82846306744ffbc4b9a4188bfc357385d98b4f8a6ea145e34386492020334; used order and patch files match manifest
PATCH=02_M04_SOBRE_M03_UX_AJUSTADO.diff
PATCH_SHA256=e2e898fb635f05cc21254dda9cfcdec4d6b05df47e98d81d2f14fa665cba62a7
ROOT_CAUSE=external order ID alone is ambiguous across seller accounts in one company
IMPLEMENTATION=existing SalesListController + existing CompanyContext + account_id-scoped query
NEW_ROUTES_CLASSES_SERVICES_TABLES_MIGRATIONS=NONE
RED=2 focused new tests failed against pre-GREEN behavior
GREEN=PASS 11 tests / 127 assertions on PHP 8.5.8
M03_DASHBOARD_REGRESSION=PASS 4 tests / 47 assertions; raw orders 3, paid 1, cancelled 2
PHP_LINT=PASS (8 changed PHP files)
PHPSTAN=PASS
DIFF_CHECK=PASS (line-ending warning remains on pre-existing DashboardHttpRouteTest.php)
BROWSER=PASS local Chromium; Sales and Dashboard canonical links resolve both same-ID accounts
VIEWPORTS=1440x900, 390x844, 430x932; no horizontal page overflow
ACCOUNT_IDENTITY=PASS nickname + seller ID shown in details; cross-account/company facts isolated
NETWORK=0 external requests; 0 non-login POSTs; 0 console errors
NAV_FOCUS=PASS first Tab reaches skip-to-main; mobile scroll-padding-bottom=106px
BROWSER_BACK_FOCUS=existing M03 BODY-focus limitation remains separate; no JS change
DATABASE=unique disposable MariaDB, loopback-only, tmpfs, no bind/volume; removed after QA
REAL_MELI_HTTP=0
COMMIT_PUSH_MERGE_DEPLOY=NONE
L1_KISS=PASS; L2_ANTI_NOISE=PASS; L3_ANTI_PATCH=PASS; L4_HUMAN_FIRST=PASS
L5=CHATGPT_INDEPENDENT_AUDIT_PENDING; not self-certified
PHASE_RESULT=IMPLEMENTADO_QA_LOCAL_PASS
EVIDENCE=LOCAL_ONLY_UNPUBLISHED
NEXT=CHATGPT_AUDITA_M04
```

# CURRENT CHECKPOINT — ERP MELI 2.0

## Checkpoint local — ERP2-M03-NAV-FOCUS (2026-10-23)

Aplica sólo al clon aislado `codex/erp2-l4-m03-v2-dashboard`, base `36ad68b3a2f4b0f62a27d6863afb47972cf00eda`. Se preserva el WIP M03 V2/UX que sigue debajo; el único cambio de producto de este bloque es una regla CSS en `public/assets/app.css`.

```text
TASK=ERP2-M03-NAV-FOCUS-20261023
RED_390_844=14.75px_FOCUS_OCCLUDED_BY_FIXED_NAV
RED_430_932=20.45px_FOCUS_OCCLUDED_BY_FIXED_NAV
GREEN=0px_OVERLAP_AT_390_430_768_1023_HEADLESS_1024_1440
1023_HEADED=INNERWIDTH_1023_MATCHMEDIA_MAX1023_FALSE; HEADLESS=TRUE
CSS=html_scroll_padding_106px_plus_safe_bottom_at_existing_max_1023_media
DASHBOARD_HTTP_TEST=PASS_4_TESTS_47_ASSERTIONS_DISPOSABLE_DB
M03_CALCULO=PASS_RETEST; M03_TABLA=PASS_RETEST
SALES_SMOKE=PASS_390_768; APPS_KEYBOARD=PASS_ESCAPE_RESTORES_OPENER
DETAIL_ENTER=GET_200; BROWSER_BACK_FOCUS=BODY_NOT_RESTORED
BLOCKER_C=HISTORY_BACK_FOCUS_REQUIRES_OUT_OF_SCOPE_BEHAVIOR; NO_JS_CHANGE
LOCAL_RESULT=PARTIAL; LEY5=PENDIENTE_REVISION_INDEPENDIENTE
REAL_MELI_HTTP=0; COMMITS_PUSH_MERGE_DEPLOY=NONE
M04_M01=NOT_STARTED
EVIDENCE=LOCAL_ONLY_UNPUBLISHED
```

## Checkpoint local — ERP2-M03-UX-MOBILE (2026-10-23)

Este registro aplica **sólo** al clon aislado `codex/erp2-l4-m03-v2-dashboard` en `36ad68b3a2f4b0f62a27d6863afb47972cf00eda`; no reemplaza el estado global H9/H10 que sigue debajo. Se aplicó el parche UX sobre M03 V2 ya presente y se preservó su corrección financiera.

```text
TASK=ERP2-M03-UX-MOBILE
HUMAN_FIRST_AND_DESIGN_AUTHORITIES=LOADED
TRUTH_AND_COMPANY_AUTHORIZATION=PASS_SYNTHETIC_LOCAL_FIXTURE
PRODUCT_FILES_CHANGED=2 (M03 V2 DashboardReadModel + Dashboard view)
TEST_FILES_CHANGED=1 (existing DashboardHttpRouteTest)
RESPONSIVE=PASS_390_430_768_1440
NO_PAGE_HORIZONTAL_SCROLL=PASS
PAID_COP=100000.0000; RAW_ORDER_COUNT=3; CANCELLED_ORDERS=2
SINGULAR=PASS (1 orden)
RED=EXPECTED_FAILURE_ON_MOBILE_TABLE_CONTRACT
GREEN=PASS (focal 1 test / 25 assertions; Dashboard regression 4 / 47)
REAL_BROWSER=PASS_FOR_RESPONSIVE_SURFACE; PARTIAL_FOR_FIXED_NAV_KEYBOARD_FOCUS
NAV=FOCUS_OBSCURED_AT_390_AND_430; NOT_OBSERVED_AT_768_OR_1440
NAV_CSS_SIMULATION=PASS_WITH_SCROLL_PADDING; SOURCE_CHANGE=NOT_APPLIED_PER_TASK_ORDER
CHECKPOINT=PERSISTED
M03_UX=PARTIAL (nav focus finding remains for independent audit)
L5=PENDIENTE_AUDITORIA_CHATGPT
NEXT=CHATGPT_AUDITA_M03_UX
M04=NOT_STARTED; COMMIT_PUSH_MERGE_DEPLOY=NONE
REAL_MELI_HTTP=0
```

## H9 — CIERRE INDEPENDIENTE VERIFICADO (2026-10-22 UTC / 2026-10-22 America/Bogota)

**AUTORIDAD VIGENTE, supersede notas H9 parciales históricas: H9 FULLY CLOSED para el alcance final Human-First certificado, combinando 77 rutas/tamaños de Chrome de la primera pasada, Chrome focal real de geometría/rol/zoom/recuperación y prueba PHP HTTP de rol.** No se reproduce la batería visual de 77 para cerrar; H10 queda AÚN NO INICIADO y empieza sólo con preflight documental/lectura dirigida, sin deploy.

```text
PROJECT=ERP MELI 2.0 ONLY
REPOSITORY=sierraglobalcompany-rgb/ErpMeli2.0
BRANCH=ui/design-master-v1-20261015
PR_19=OPEN/DRAFT/NOT_MERGED
H7C_FULLY_CLOSED=YES
H8A_FULLY_CLOSED=YES
H8B_FULLY_CLOSED=YES
H9_INDEPENDENT_AUDIT=PASS
H9_FULLY_CLOSED=YES
H9_APPROVED_MOCKUP_FIDELITY=NOT_VERIFIABLE
H9_VISUAL_FIX=7e83c11e0a8e85e0c7ae44f858225a01db7b8334
H9_VISUAL_FIX_CI=39875310237_SUCCESS
H9_ROLE_TEST=738db21a8e573ba9c7facbd46e823bdc08aaad5f
H9_ROLE_TEST_CI=39881745635_SUCCESS
H9_ROLE_TEST_PHP_8_3_8_4_8_5=860_TESTS_7497_ASSERTIONS_EACH
H9_ROLE_TEST_PHPSTAN=PASS
H9_MOBILE_GEOMETRY=PASS_390x844_430x932
H9_LIMITED_ROLE=PASS_BROWSER_HTTP_AND_SHELL_PLUS_SERVER_HTML_TEST
H9_ZOOM_REAL_200=PASS
H9_ERROR_RECOVERY_SAFE_GET_404_TO_200=PASS
H9_ZERO_MUTATIONS=REPORTED_21_TABLES_3_PRIVATE_SYNTHETIC_FILES
H9_SERVER_LINK_DEAD_END=FALSE_POSITIVE_CORRECTED
H10_STARTED=NO
NEXT=H10_READ_ONLY_PREFLIGHT_FOLLOW_ERP_COMPLETION_GATES
```

**Paquetes y SHA:**
- H9 primera pasada: ZIP `29fcf3f4d92b037114b293a684e180d2f0f927507ae5cb3152b20e117b520e20`, report `3703a8ce40a05062da0d61e1e51a66bff300ebdcc55c42bcbb054c5f59fc7802`; 45/45 hashes internos y CRC PASS auditados, 77/77 recorridos System Admin, truth fixtures 5/5. Scorecard original 5 PASS/1 FAIL/4 NOT_COVERED es **histórico**, no resultado consolidado final.
- H9 focal Codex: ZIP `9c273bc69d7de1f9ad0cae591e4fc8b612575981a6e28746743e249c92a6358e` (672.194 bytes), report `d6ad445e7f7575fac554d5609bb0d187ce9d54482027bc66ca02e903a88d2ff8` (8.353 bytes); auditoría independiente de ChatGPT: 17 miembros ZIP, CRC PASS, 16/16 hashes internos PASS, informe exterior idéntico al interno, 14 PNG inspeccionados y ZIP sin miembros cifrados. Abarca 390/430 CTA sin solape (0px), interacción checkbox, operador sintético GET 200/403 esperados, Apps móvil, sidebar desktop, zoom Chrome real 200% y recuperación GET 404→back GET 200. Hashes/conteos de 21 tablas/3 archivos privados sintéticos iguales **según informe y matriz**, ChatGPT no consultó DB real. Docker tmpfs, `REAL_MELI_HTTP=0`, sin provider requests observadas; network bridge sin política de egress formal.
- **Rectificación forense importante:** informe Codex sospechó que un operador veía enlace `/mercadolibre/accounts` desde `/billing` pero NO capturó DOM Billing; lectura de `app/Modules/Billing/views/billing.php` confirma que panel y enlace están **dentro de `if ($company['role'] === 'admin')`**. El controller obtiene el rol efectivo de `CompanyContext::company()`; no es bypass ni enlace de operador demostrado. `tests/Integration/BillingDesignMasterUiTest.php::testLimitedOperatorBillingHidesUnreachableMeliManagementLink`, commit **test-only `738db21a…`**, verifica respuesta GET Billing 200, ausencia enlace restringido y formulario de sync, MELI móvil deshabilitado, mientras prueba admin preexistente valida enlace accesible. CI `39881745635` SUCCESS con **860 tests/7.497 assertions por PHP 8.3, 8.4, 8.5; PHPStan PASS**. **No se cambió código del producto para corregir un falso positivo.**
- **Apps escritorio:** CSS `.mobile-bottom-nav {display:none}` fuera de media <=1023; botón Apps solo móvil. Sidebar autorizada comprobada a 1440, no abrir Apps desktop es `NOT_APPLICABLE`, NO un nuevo bloqueo. Certificación H9 compuesta de browser y HTML servidor; no afirmar DOM Billing capturado por Chrome ni UX de producción.
- **Límites de artefactos:** seis capturas con identificadores sintéticos conservadas en directorio RAW D: fuera ZIP curado; perfil temporal de Chrome en C: no tuvo limpieza confirmada; solo evidencias sanitizadas compartibles. `APPROVED_MOCKUP_FIDELITY=NOT_VERIFIABLE` sigue sin referencia final aprobada. La integración de H9 no prueba producción, Hostinger o API MELI real.
- **Regla permanente de respaldo D:** solo código y documentación no sensible, sin claves, tokens, passwords, hashes de contraseñas reales, sesiones, dumps privados ni archivos/respaldos cifrados. No restaurar/copiar `.env`, `storage/private` o material protegido. Estado histórico de `D:\codex\ERP-MELI2` `RECOVERY_PARTIAL`, HEAD local histórico divergente y contenidos no certificados libres de secretos; **NO borrar/formatear C:**. No confundir staging de QA con respaldo operativo.
- PR #19 **Draft, sin merge ni deploy**, mantener guardrails KISS/anti-ruido/anti-parches/Human-First, MELI writes OFF hasta su fase autorizada; no reabrir H8 ni H9 sin nueva regresión demostrada.


## H9 — AUDITORÍA INDEPENDIENTE PARCIAL (2026-10-22)

```text
H7C_FULLY_CLOSED=YES
H8A_FULLY_CLOSED=YES
H8B_FULLY_CLOSED=YES
H9_BROWSER_INITIAL=77_OF_77_ADMIN_HTTP200_AT_7_VIEWPORTS
H9_SCORECARD=5_PASS_1_FAIL_4_NOT_COVERED
H9_INDEPENDENT_EVIDENCE_AUDIT=PASS_INTEGRITY_NOT_COMPLETE_COVERAGE
H9_V1_ZIP_SHA256=29fcf3f4d92b037114b293a684e180d2f0f927507ae5cb3152b20e117b520e20
H9_V1_REPORT_SHA256=3703a8ce40a05062da0d61e1e51a66bff300ebdcc55c42bcbb054c5f59fc7802
H9_CSS_FIX=7e83c11e0a8e85e0c7ae44f858225a01db7b8334
H9_CSS_FIX_CI=39875310237_SUCCESS
H9_CSS_CHROME_CERTIFIED=NO
H9_LIMITED_ROLE=NOT_COVERED
H9_REAL_ZOOM_200=NOT_COVERED
H9_ERROR_RECOVERY=NOT_COVERED
H9_FULLY_CLOSED=NO
H10_STARTED=NO
NEXT=H9_FOCAL_CHROME_390_430_LIMITED_ZOOM_RECOVERY_THEN_CHATGPT_AUDIT
```

**Auditoría H9:** ZIP CRC PASS, 45/45 hashes internos, informe externo idéntico, 77 pares en 11 rutas/7 tamaños con 0 overflow horizontal y 0 errores 5xx; 21 tablas/4 archivos sintéticos iguales antes/después según SHA del runner, no relectura de DB por ChatGPT. En `/settings/system` a 390×844, el CTA se cruzó con la nav fija unos 42px al iniciar la pantalla, pero **en scroll final no se superpuso**; observación es transitoria, no obstrucción permanente. Se identificaron checkboxes de 44px apilados que alargan el formulario. ChatGPT implementó una mejora raíz delimitada a la página en `public/assets/app.css` (`7e83c11e…`, CI GREEN) para volverlos horizontales y compactos; **el resultado en Chrome sigue NO_VERIFICADO**. Perfil limitado, zoom real y error/recuperación permanecen sin cobertura. **No declarar H9 cerrado ni H10 iniciado.** No repetir 77 recorridos ni H8-A/H8-B; Codex sólo navegador focal en staging desechable, y ChatGPT audita el paquete resultante.

**Respaldo D: orden del usuario:** código y documentos no sensibles únicamente; nada de claves, contraseñas, tokens, hashes reales de passwords, archivos cifrados ni respaldos cifrados. Contenido histórico del respaldo D: `NOT_VERIFIED_SECRET_FREE`; recuperación de `D:\codex\ERP-MELI2` sigue PARCIAL. No borrar C: ni importar archivos privados.


## ESTADO VIVO H8-B — CIERRE LOCAL RED→GREEN (2026-10-22, Bogotá)

**H8-B FULLY CLOSED en cobertura local automatizada:** corregida la sustitución silenciosa de valores inválidos de `debug_retention_days` y `debug_max_mb` en `SystemSettingsController::update()`. El cambio reutiliza `SystemSettingsRepository::updateOperationalToggles()` y está limitado a **2 archivos** (controlador y prueba HTTP de integración), sin esquema, rutas, UI, permisos, Work, Billing/Financial, Mercado Libre HTTP ni nueva arquitectura. Este bloque es **la autoridad vigente** frente a estados históricos H8-B pendiente más abajo.

```text
PROJECT=ERP MELI 2.0 ONLY
REPO=sierraglobalcompany-rgb/ErpMeli2.0
BRANCH=ui/design-master-v1-20261015
PR_19=OPEN/DRAFT/NOT_MERGED
H7C_FULLY_CLOSED=YES
H8A_FULLY_CLOSED=YES
H8B_RED=583ab2c946c64b272ef1bfd89596fc76f027b693
H8B_RED_CI=39854251327 FAILURE_EXPECTED_ONE
H8B_GREEN=6fc9f70a57808012c6badaa6127f6b02d9e6efcf
H8B_GREEN_CI=39854998573 SUCCESS
H8B_PHP_8_3_8_4_8_5=859_TESTS_7490_ASSERTIONS_EACH_ZERO_FAILURES
H8B_PHPSTAN=PASS
H8B_INDEPENDENT_AUDIT=PASS
H8B_FULLY_CLOSED=YES
H9_STARTED=NO
NEXT=H9_READ_ONLY_PREFLIGHT
```

**RED demostrado:** nueva prueba `SystemSettingsInvalidInputHttpRouteTest::testInvalidOperationalSettingsReturn422WithoutMutation@nonnumeric retention`; HTTP observado 303 vs esperado 422, **una sola falla** en QA PHP 8.3/8.4/8.5 (RUN 39854251327). **GREEN comprobado:** RUN 39854998573 SUCCESS, matriz PHP 8.3/8.4/8.5 con 859 tests, 7.490 assertions y PHPStan sin errores. ChatGPT auditó logs reales de cada job y diff del mismo SHA. La batería corrió con MariaDB 11.4 descartable de CI, `REAL_MELI_HTTP=0`; no se ejecutó contra un checkout del usuario ni base real.

**Contrato corregido:** si el campo no viene en el POST se usa el default explícito previo (7 días, 100 MB); si está presente se exige tipo string, un entero validable y dominio/rango existentes en repositorio, y toda entrada inválida responde **422 antes de cualquier UPDATE**. La prueba focal cubre `abc`, `0`, vacío, decimal, notación científica, `null`, array y boolean para ambos campos, además de valores fuera del dominio y un caso positivo de campo ausente. Persistencia y `meli_writes_enabled` sin modificaciones en 422; CSRF 419, rol admin 403 y redirección válida 303 conservados. No hubo cambio de UI; no exigir Chrome adicional.

### Restricción permanente sobre la copia local en D: — orden expresa del usuario

```text
BACKUP_D_SCOPE=ERP_MELI_2_0_ONLY
BACKUP_D_POLICY=CODE_AND_NON_SENSITIVE_DOCS_ONLY
BACKUP_D_NO_REAL_KEYS_TOKENS_PASSWORDS=MANDATORY
BACKUP_D_NO_PASSWORD_HASHES_OR_ENCRYPTED_CONTENT=MANDATORY
BACKUP_D_NO_ENCRYPTED_ARCHIVES_OR_ENCRYPTION_WORKFLOW=MANDATORY
BACKUP_D_EXISTING_BYTES_SECURITY_AUDIT=NOT_RUN
D_RECOVERY_STATUS=PARTIAL
```

**No copiar a D:** `.env` reales, `storage/private`, `auth.json`, tokens, cookies, sesiones, claves privadas/certificados con material secreto, credenciales de BD o Mercado Libre, hashes reales de contraseñas, dumps privados que los contengan, archivos cifrados ni ZIP protegidos con contraseña. Tampoco generar un respaldo cifrado: el usuario no quiere claves, contraseñas ni cifrados en la copia de D:. Copiar sólo fuente y documentación no sensible; configuraciones sensibles se **recrean y reautentican** tras una reinstalación, no se restauran desde D:. **No prometer que respaldos Git históricos, reflogs, bundles o `.rescate-local` ya existentes están limpios de secretos sin inspección local de su contenido.** Codex reportó `storage/private` excluido del traslado, pero la auditoría integral de D: no se ejecutó aquí. No borrar C: ni sus orígenes antes de verificar recuperación suficiente y obtener autorización separada.

**STOP:** No reabrir H7C/H8-A/H8-B sin regresión nueva. No merge/deploy, no API MELI real, no mover/restaurar credenciales a D:, no limpieza de C:, no comenzar H9 sin lectura dirigida del alcance.


## ESTADO VIVO H8-A — CIERRE INDEPENDIENTE FINAL (2026-10-22)

**H8-A queda FULLY CLOSED para el alcance definido (formulario destructivo de limpieza de credenciales MELI Developer, controles de accesibilidad, roles/solo lectura y ausencia de efectos colaterales) en staging sintético, sin ejecución en producción.** Esta sección prevalece sobre las líneas `H8A_FULLY_CLOSED=NO` o `NEXT=H8A_CHROME_FOCAL_V4` históricas de más abajo, incluidas las notas sobre el bloqueo V3/preflight V4.

```text
PROJECT=ERP MELI 2.0 ONLY
REPO=sierraglobalcompany-rgb/ErpMeli2.0
BRANCH=ui/design-master-v1-20261015
PR_19=OPEN/DRAFT/NOT_MERGED
CERTIFIED_BROWSER_SOURCE_HEAD=5c7475ec9dc7613812f9b976915fdfe4309bcda0
FUNCTIONAL_GREEN_HEAD=5ce08784a55779886703ac6f2125224c764bcd1d
SOURCE_HEAD_VS_FUNCTIONAL=DOCS_ONLY
FUNCTIONAL_CI=39701894315 SUCCESS
LAST_PRE_CLOSURE_CI=39723060231 SUCCESS
H7C_FULLY_CLOSED=YES
H8A_AUTOMATED_GREEN=YES
H8A_BROWSER_CERTIFIED=YES
H8A_INDEPENDENT_AUDIT=PASS
H8A_FULLY_CLOSED=YES
APPROVED_MOCKUP_FIDELITY=NOT_VERIFIABLE
H8B_STARTED=NO
H9_STARTED=NO
NEXT=H8B_INPUT_VALIDATION_READ_ONLY_PREFLIGHT -> RED_MINIMAL -> GREEN -> QA
```

**Evidencia H8-A V4** aportada por Codex: `H8A_BROWSER_REPORT.md`, `H8A_RUN_MANIFEST.json`, `H8A_BROWSER_EVIDENCE.zip`, `H8A_BROWSER_EVIDENCE.zip.sha256`, `SHA256SUMS.txt`. **Auditoría independiente de ChatGPT:** bytes del ZIP SHA-256 `B5D492FD3940AF7520D97D4D371B45EC3916B29C735E0FED6A2F9B80D25B5334` coinciden con sidecar; ZIP sin CRC inválido; 7 miembros (informe, JSON, sumas, 4 PNG), 6/6 hashes internos correctos; 3 archivos externos coinciden byte a byte con sus versiones dentro del ZIP; 4 PNG abiertos y examinados, dimensiones 390×844 (3) y 1440×900 (1). Informe SHA `71C80FAEC24CE3C9595CDA9BA09124E7C4BE15EA60A03172AC405611198A814F`; manifiesto SHA `4EF9CB50D8C8DF50F1D5AAB2969F313098E1619739858486995939BB7F9862FF`.

- **A=PASS:** Chrome Windows 155, pantalla 390×844, formulario `action=/settings/mercadolibre` y hidden `action=clear` obtenidos con `getAttribute` en runtime; CSRF presente sin extraer valor; required checkbox value `1`, `checkValidity() false→true→false`; Enter/Espacio/Tab y `:focus-visible` con halo CSS; CTA bottom=721.16 y nav top=757, gap=35.84px, overlap=0; ninguna presentación del secreto ni POST destructivo. Capturas foco y fin de scroll. **Nota UX no bloqueante:** checkbox visualmente grande y separado del texto por regla genérica `.settings-form input {width:100%}`; actualmente utilizable con foco visible y sin solape, no abrir parche visual no solicitado.
- **B=PASS:** modo `server-managed` en PHP `APP_ENV=test`, valores MELI sintéticos de proceso; 390×844 y 1440×900, cuatro capturas de V4 en total; en cada tamaño se verifican cero forms/inputs/controles de guardar-limpiar, sin secret en UI y aviso de sólo lectura. Capturas corroboran el estado; no hubo POST de settings.
- **C=PASS:** comparación de manifiesto de 4 tablas `users`, `companies`, `company_users`, `system_settings` y archivos `runtime.json`, `installed.lock`, `install.guard`: SHA/filas/tamaños y estado de existencia iguales antes/después; mismo staging y reloj monotónico. **Límite explícito:** ChatGPT contrastó hashes/manifest, no se conectó a la BD ni recibió datos crudos.
- **Aislamiento:** MariaDB 11.4.13 `mariadb:11.4`, tmpfs 768 MiB sin mount, puerto loopback temporal y contenedor retirado; Chrome 155.0.8059.85, PHP 8.5.8, `REAL_MELI_HTTP=0`, sin API MELI real ni escrituras remotas. V3 había validado 7 viewports y 200/403; no se repitieron. CI automatizada del commit funcional: 842 tests/7.371 aserciones por matriz PHP 8.3/8.4/8.5, PHPStan PASS. V4 no reejecutó suite completa (correcto).
- **Observaciones que no impiden H8-A:** favicon loopback 404 incidental; consulta npm metadata de tooling inicial (no de ERP); `APPROVED_MOCKUP_FIDELITY=NOT_VERIFIABLE` sin mockup aprobado; algunas carpetas temporales de staging/Playwright quedaron por bloqueo de eliminación segura, **no ejecutar limpieza recursiva** sin verificar identidad y contenido. Prohibido afirmar QA de producción, simulación de POST destructivo en navegador o acreditación de un diseño aprobado.
- **Recuperación D: es independiente y permanece PARTIAL:** `D:\codex\ERP-MELI2` con HEAD local histórico `931e91b3…`, divergencia respecto a remoto. El cierre Chrome se ejecutó en staging nuevo desde GitHub y NO certifica ni corrige la rama ni DB privada de D:. No borrar C: todavía.

**Guardrails:** cuatro leyes KISS/antirruido/antiparches/Human-First; no merge, deploy, MELI live ni modificación financiera/FIFO/Work. **No pedir más H8-A V4 ni reabrir H7C por defecto.** H8-B sólo procede en microbloque autónomo con TDD y prueba con DB exclusiva, no ejecutar sobre `storage/private` real o checkout compartido.


## RECTIFICACIÓN DE ALCANCE — respaldo local solo ERP MELI 2.0 (2026-10-22 UTC)

**El usuario solicitó respaldo local únicamente de `sierraglobalcompany-rgb/ErpMeli2.0`.** Se interpretó erróneamente como los 16 repositorios de la cuenta. **CANCELADOS** los prompts para ocho repositorios privados y cualquier `NEXT=LOCAL_AUTH_FOR_CORRECT_OWNER_AND_BACKUP_8_PRIVATE`. No pedir a Codex autenticación, clonación ni QA de otros repositorios por causa de este proyecto.

```text
BACKUP_SCOPE=ERP_MELI_2_0_ONLY
BACKUP_OTHER_REPOS=OUT_OF_SCOPE
BACKUP_MORE_CODEX=NO_NECESARIO
BACKUP_LOCAL_ROOT=D:\GitHub_OFFLINE_BACKUPS\sierraglobalcompany-rgb\20261022T040320Z
ERP2_GIT_BACKUP=REPORTED_PASS_IN_CODEX_EVIDENCE
ERP2_BUNDLE_SHA256=74478DE600E40EDB2470A3ED390487C7C4C363771D821F04B47E5657CDF2BE70
BACKUP_MANIFEST_SHA256=E1EB3D770C13BC9D035783962A44446D36D4B61683DA37031AF7609CD97E41A1
H8A_BROWSER_CERTIFIED=NO
H8A_FULLY_CLOSED=NO
H8B_STARTED=NO
NEXT=H8A_CHROME_FOCAL_V4 -> CHATGPT_INDEPENDENT_AUDIT
```

- Informe de Codex: mirror `ErpMeli2.0` con 46 refs, 5.565 objetos, `git fsck`, bundle, LFS y restauración Git offline `PASS`; se verificó presencia del commit `de383a6b9f1e45849a05f3999975d17932a3c5d4` y del funcional `5ce08784a55779886703ac6f2125224c764bcd1d`. Metadata pública issues/PR exportada según reporte. **ChatGPT auditó reporte/manifiesto, pero no recibió los bytes del bundle ni inspeccionó el disco D:** PASS es una prueba **reportada por Codex**, no una reejecución local de ChatGPT.
- Esta copia respalda historia Git; **no implica** backups de base de datos, secretos, archivos locales no versionados, dependencias instaladas ni certificación E2E de la aplicación. No es necesario ampliar ahora el respaldo a otros repositorios. Conservar snapshot anterior inmutable.
- Esta rectificación prevalece sobre instrucciones históricas de respaldo de toda la cuenta. No reabrir H7C, no merge/deploy; H8-A mantiene Chrome V4 como única siguiente puerta de calidad.


## CHECKPOINT VIVO — RECUPERACIÓN H8-A (2026-10-21, Bogotá)

**VERDAD ACTUAL: H7C COMPLETO; H8-A GREEN AUTOMATIZADO PERO NO CERRADO; H8-B/H9 NO INICIADOS.** Este bloque prevalece sobre campos históricos de fases anteriores presentes más abajo; siempre verificar HEAD/PR/CI directamente en GitHub.

```text
PROJECT=ERP MELI 2.0 ONLY (NEVER ERP-BDM)
REPO=sierraglobalcompany-rgb/ErpMeli2.0
BRANCH=ui/design-master-v1-20261015
PR=19 OPEN/DRAFT/NOT_MERGED
FUNCTIONAL_HEAD=5ce08784a55779886703ac6f2125224c764bcd1d
FUNCTIONAL_CI=39701894315 SUCCESS
QA=842 tests / 7371 assertions / PHP 8.3, 8.4, 8.5 / PHPStan PASS
DOCS_HEAD_BEFORE_THIS_CHECKPOINT=6b86c01927534dac94ef259ed67bbab3a15299f1
DOCS_HEAD_BEFORE_CI=39702560800 SUCCESS
H7C_PHASE_FULLY_CLOSED=YES (TEST; caveats documented)
H8A_AUTOMATED_GREEN=YES
H8A_BROWSER_CERTIFIED=NO
H8A_FULLY_CLOSED=NO
H8B_STARTED=NO
H9_STARTED=NO
NEXT=H8A_CHROME_FOCAL_V4 -> CHATGPT_INDEPENDENT_AUDIT
NO_MERGE=YES
NO_DEPLOY=YES
REAL_MELI_HTTP=0
REMOTE_WRITES=OFF
```

### H8-A implementación ya hecha por ChatGPT, NO delegar programación

- Riesgo corregido en `MeliAppSettingsController::mutate()`: `POST /settings/mercadolibre` con `action=clear` antes borraba credenciales locales sin segundo consentimiento.
- RED sólo test: `994744c819aa781b9c287e995bdac50617f5253a` / CI `39700960434` (un fallo esperado por versión, 303→422). Primer GREEN `042e52de4169a84ad96ee1a917123c12186c6b59` / CI `39701598048` SUCCESS. GREEN final `5ce08784a55779886703ac6f2125224c764bcd1d` / CI `39701894315` SUCCESS.
- Sólo 3 owners funcionales/test: `app/Modules/Settings/MeliAppSettingsController.php`, `app/Modules/Settings/views/mercadolibre.php`, `tests/Integration/MeliAppSettingsTest.php`. Exacto `confirm_clear_meli_credentials==='1'` antes de abrir `install.guard`; faltante/inválido 422 sin alterar runtime/installed/guard. HTML nativo `<details>` cerrado + checkbox requerido sin marcar, copy veraz, mismos CSRF/status/rutas. Sin PHP/CSS/JS extra, tablas, migraciones u OAuth.
- CI funcional y CI documental base exitosos. El commit de este checkpoint cambia **sólo los 2 Markdown** `docs/CURRENT_CHECKPOINT.md` y `docs/PROJECT_CONTINUITY_CURRENT.md`. No se ha hecho QA de navegador H8-A.

### UX/UI y mockups — comprobación de autoridad (10 pasadas)

- Fuentes efectivas versionadas: `docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md` y `design-system/erp-meli-2-0/MASTER.md`; vigente sistema claro, ejecutivo, consistente, WCAG 2.2 AA, nav móvil de 5 slots.
- Git tree en `6b86c01927534dac94ef259ed67bbab3a15299f1`: **349 rutas, sin imágenes de mockup/Figma ni `design-system/erp-meli-2-0/pages/*.md`**. El ZIP histórico de H7A contiene capturas de otras pantallas, **no es mockup aprobado de `/settings/mercadolibre`**. No inventar fidelidad. Si aparece referencia aprobada verificable en recursos del certificador, cotejar por pantalla, viewport, estado, DPR, SHA y procedencia; si no, `APPROVED_MOCKUP_FIDELITY=NOT_VERIFIABLE` y medir contra Design System.
- Navegador de Codex Luna sólo en staging TEST; 7 viewports oficiales: 390×844, 430×932, 768×1024, 1024×768, 1366×768, 1440×900, 1920×1080. Medir diseño, focus/Tab/Escape, checkbox/validity, 403 operador, 200 system-admin, modo servidor sólo lectura; no POST clear/save, no llamada MELI.
- Prompt único para el certificador: `ERP_MELI_2_0_PROMPT_CODEX_LUNA_H8A_BROWSER_V2_10_PASADAS_MOCKUPS_2026-10-21.md` (adjunto de conversación; no está versionado en el repo). Al recibir reporte + ZIP + SHA256, ChatGPT audita antes de cerrar H8-A.

### Reglas no negociables / stop-safe

KISS sin arquitectura nueva; antirruido (eliminar/merge/optimizar sólo con prueba); antiparches (corregir el dueño raíz, no tapar síntomas); UX/UI Pro + Human-First (verdad, seguridad y accesibilidad primero). `UNKNOWN!=0`, prohibidas métricas o pantallas ficticias. No tocar Billing/Financial/Work/FIFO/429 de ERP1 sin evidencia; causa universal 429 no probada. PR #19 no merge sin aprobación. Ni Hostinger/cron, ni mutaciones MELI, ni borrado de `runtime.json`, `installed.lock`, `install.guard`, exportaciones o temporales del usuario por conveniencia. El test de MeliAppSettings **escribe en el storage del checkout local**; sólo ejecutarlo en copia desechable: nunca sobre C:\\codex o recursos compartidos.


### H8-B: hallazgo focal preparado, implementación AÚN NO iniciada (preauditoría 2026-10-21)

- **Evidencia directa, sin BD:** En PHP 8.4, `filter_var('abc', FILTER_VALIDATE_INT)` devuelve `false` y `filter_var('0', FILTER_VALIDATE_INT)` devuelve `0`; el `?: 7`/`?: 100` de `SystemSettingsController::update()` los sustituye silenciosamente por valores predeterminados. `'3.5'`, `''` y `'1e3'` también producen fallback. **Aún no se probó HTTP mediante RED**, por lo que 303 es consecuencia esperada del código, no observación E2E certificada.
- Falso positivo cerrado: `SystemSettingsInvalidInputHttpRouteTest` ya cubre 422 para enteros `5` días y `9` MB; `CompanySelectorHomeContractTest` ya certifica selector único → `/dashboard`. **No rehacer DM8-A1 ni el antiguo 500 de DM8-A3**.
- Próximo microbloque después del cierre completo H8-A: RED de entrada **presente** inválida (`abc`, `0`, `3.5`, campo vacío/no numérico) con System Admin+CSRF válido y estado inicial 14 días/200 MB, esperando 422, **sin mutación** (incluye toggles y `updated_by`). Probar también 403/419; los **campos ausentes** conservan fallback histórico 7/100, y `meli_writes_enabled` continúa falso.
- GREEN previsto y aislado: distinguir campo ausente frente a campo presente inválido; rechazar `FILTER_VALIDATE_INT === false` y dejar que el repositorio rechace `0`; cambiar sólo `SystemSettingsController.php` y la prueba focal. NO nuevos componentes, clases, rutas, SQL ni copy visual por esta incidencia.
- Importante: `TestDatabase::reset()` destruye la DB configurada. PHPUnit sólo en entorno efímero/CI o test DB comprobada. H8-B aún **NO** tiene RED, GREEN ni cambios de producto; `H8B_STARTED=NO`.
- H8-A sigue `AUTOMATED_GREEN=YES`, `BROWSER_CERTIFIED=NO`, `FULLY_CLOSED=NO`. Prompt Codex Luna browser V2 (adjunto) permanece próximo paso. No capturas ni mockup H8-A nuevo disponible en este turno; `APPROVED_MOCKUP_FIDELITY=NOT_VERIFIABLE` hasta evidencia aprobada.
- Checkpoint anterior: `5af2ff8291a55f20da78efd99493da2b0b250702`, CI `39704436927` **SUCCESS**; PR #19 `OPEN/DRAFT/NOT MERGED`. Continuar con leyes KISS, antirruido verificable, antiparches y UX/UI Pro/Human-First.

### H8-A V3 — auditoría independiente del paquete Chrome (2026-10-21)

- Evidencia original recibida: `H8A_BROWSER_REPORT.md`, `H8A_BROWSER_EVIDENCE.zip`, `H8A_BROWSER_EVIDENCE.zip.sha256`. ZIP SHA256 `c2fc8ebe9e7f50c277191faca741d04514b64dcb5c31e1ea8aabe356a1efbc92` **comprobado**, CRC PASS, 19 miembros totales / 18 archivos en manifiesto = 18/18 hashes PASS, reporte externo e interno idénticos.
- Se inspeccionaron visualmente las nueve capturas existentes. Chrome 155 headless, DPR1; 7/7 viewports sin overflow, shell/nav móvil de cinco slots y comportamiento nativo de disclosure, Enter/Espacio, validación checkbox `false→true→false`, CSRF presente y Secret ausente del DOM. Capturas sólo prueban posición superior; no certifican CTA junto al nav en el final de scroll.
- Red: POST login sintético ×2 con 303, GET System Admin `/settings/mercadolibre` 200 y usuario sin privilegio global 403 observado en evento de respuesta (Playwright lanzó al navegar al 403); cero POST destructivo/de configuración, solicitudes externas=0, requests pendientes=0. Ese error de navegación es instrumental, no fallo de autorización.
- Invariantes: snapshot de `runtime.json`, `installed.lock`, `install.guard` y cuatro tablas `users`, `companies`, `company_users`, `system_settings` iguales antes/después (SHA/tamaño/conteo), mismo staging sintético; BEFORE completado antes del primer GET; AFTER empieza tras cierre del navegador.
- Estado V3 preservado **`BLOCKED`**: el runner leyó `form.action` sombreado por `input[name="action"]`, produciendo ruta ficticia `/settings/[object HTMLInputElement]`. En código de GitHub sí está `form action="/settings/mercadolibre"` + `input name="action" value="clear"`, pero aún faltan atributos leídos **en Chrome** con `getAttribute()`; tampoco se ejecutó modo credenciales gestionadas por servidor y faltan sus 2 capturas.
- Foco: `:focus-visible` se activa; `outlineStyle:none` **no prueba ausencia de indicador** porque `public/assets/app.css` aplica `input:focus {border-color:var(--primary);box-shadow:0 0 0 3px rgba(36,107,253,.12)}`. Verificar foco visible real en siguiente QA. Nada justifica modificar producto.
- Diseño/mockup: no hay mockup aprobado verificable de esta página en GitHub; `APPROVED_MOCKUP_FIDELITY=NOT_VERIFIABLE`. QA visual contra Design Master solamente. H8-A no está independientemente certificado y H8-B **NO** ha iniciado implementación.
- Sólo resta **una microcertificación** instrumental: (A) leer attributes del formulario y foco + CTA al scroll final móvil 390x844; (B) GET modo administrado por servidor 390x844 y 1440x900, 2 capturas; (C) misma BD/storage sintéticos intactos antes/después; red 0 MELI/0 POST clear/save. No repetir 7 viewports ni 842 tests. Contrato disponible al usuario: `ERP_MELI_2_0_CODEX_LUNA_H8A_MICROCERT_FOCAL_V4_2026-10-21.md` (archivo de conversación, no en GitHub).

```text
H8A_V3_INDEPENDENT_AUDIT=PASS_FOR_PROVEN_GATES_WITH_EXPLICIT_GAPS
H8A_V3_RUNNER_RESULT=BLOCKED
H8A_BROWSER_CERTIFIED=NO
H8A_FULLY_CLOSED=NO
H8B_STARTED=NO
NEXT=H8A_CHROME_FOCAL_V4 -> CHATGPT_INDEPENDENT_AUDIT
```

**CURRENT=H7C FULLY CLOSED / H8-A MELI CREDENTIAL CLEAR AUTOMATED GREEN / BROWSER CERT PENDING / H8-B NOT STARTED / PR #19 DRAFT UNMERGED / STOP-SAFE**

Esta es la autoridad operativa corta.

Para recuperación rápida, leer primero:

```text
docs/PROJECT_CONTINUITY_CURRENT.md
```

Para recuperación detallada, metodología, leyes y contexto forense, leer después:

```text
docs/ERP_MELI_2_0_RECOVERY_HANDOFF_H7C_A_AUTOMATED_GREEN_BROWSER_PENDING_2026-10-17.md
RECOVERY_HANDOFF_COMMIT=68a98e9728335053ade3e96ec2718255e4a08223
```

## Repository authority

```text
DATE=2026-10-21
REPO=sierraglobalcompany-rgb/ErpMeli2.0
IMPLEMENTATION_BRANCH=ui/design-master-v1-20261015
PR=19
PR_STATE=OPEN / DRAFT / NOT MERGED
BASE_BRANCH=ui/operator-shell-v1-20261014
BASE_SHA=c6f8af7f446cb327a905ae2f9bd9ee424c2d421f
FUNCTIONAL_HEAD=5ce08784a55779886703ac6f2125224c764bcd1d
FUNCTIONAL_HEAD_CI=39701894315 SUCCESS
REMOTE_WRITES=OFF
REAL_MELI_HTTP=0
HOSTINGER_DEPLOYED=NO
REAL_MELI_WRITES=NO
MERGE_AUTHORIZED=NO
STOP_SAFE=YES
```

`main` is not current Human-First product authority.

## Mandatory laws

```text
KISS=MANDATORY
ANTI_NOISE=MANDATORY
ANTI_PATCH=MANDATORY
HUMAN_FIRST=MANDATORY
TRUTH_BEFORE_BEAUTY=MANDATORY
UNKNOWN_NEVER_BECOMES_ZERO=MANDATORY
EVIDENCE_BEFORE_GREEN=MANDATORY
NO_MERGE_WITHOUT_EXPLICIT_USER_APPROVAL=MANDATORY
```

## Working method

```text
READ-ONLY PREFLIGHT
-> one bounded owner/root defect
-> approved design when required
-> real RED test first
-> commit RED
-> prove expected RED in CI
-> minimal root-owner GREEN
-> commit GREEN
-> fresh full CI
-> real-browser certification when UI/responsive/keyboard changed
-> independent evidence audit
-> durable checkpoint + PR sync
-> only then next microblock
```

ChatGPT is primary driver. Antigravity is certification-only when local real-browser control is unavailable; no product fixes, commits, push, merge commit or PR merge. A same-branch local fast-forward sync is allowed only when required by the certification prompt. Codex is for material local-machine barriers, not routine implementation.

## Certified Human-First history

```text
H1_FIX1_SALE_DETAIL=GREEN
H2A_SKIP_TO_MAIN=GREEN
H2B_TYPOGRAPHY_TOUCH=GREEN
H3_DASHBOARD=GREEN
H4_SALES_LIST=FULLY_CLOSED
H5_CATALOG_INVENTORY=FULLY_CLOSED
H6A_H6F_BILLING=FULLY_CLOSED
BILLING_HUMAN_FIRST_CLOSURE=YES
H7A_GLOBAL_ADMIN_SHARED_SHELL=FULLY_CLOSED
H7B_USERS_PROGRESSIVE_DISCLOSURE=FULLY_CLOSED
```

Do not reopen certified blocks without new evidence/regression/new requirement.

## H7B closure anchor

```text
H7B_RED=73a8a92f6d31f1f6619dc6d4f2e3b2214032c3cc
H7B_GREEN=e6711bd9ccbb5a6298c14885f4f2341040a94343
H7B_GREEN_CI=39043131947
H7B_PHP_8_3=823 tests / 6999 assertions / 0 failures
H7B_BROWSER_CERTIFIED_HEAD=16d50961cda86ef7f886affe8676d470422607cd
H7B_EVIDENCE_ZIP_SHA256=6aa6144e6f34432e1e5e86e2aeffbd9c99b387afbc39a0f5ed9e69576a692da7
H7B_MANIFEST=18/18 exact SHA-256
H7B_BROWSER_INDEPENDENT_AUDIT=PASS
H7B_FULLY_CLOSED=YES
```

## H7C authority

```text
H7C_A=Companies: archive + permanent delete
H7C_B=MELI accounts: disconnect + hard delete
H7C_C=System settings: clear debug
H7C_D=Users: remove System Admin privilege + remove company membership
```

H7C is destructive-action UX / T3 Security-Authorization, not generic progressive disclosure. One owner per microblock.

# H7C-A — Companies destructive UX

Truth owner remains:

```text
GET/POST /system/companies
POST /system/companies/{id}/archive
POST /system/companies/{id}/reactivate
POST /system/companies/{id}/delete
OWNER=CompanyManagementController
```

Backend authorization, CSRF, domain rules and permanent-delete acknowledgement are authoritative.

## Initial RED — proven

```text
H7C_A_RED=1c8bae3374c7a36af075f3b5bb59c64c95cef763
H7C_A_RED_CI=39049451668
RED_WORKFLOW=EXPECTED FAILURE
RED_PHPSTAN=PASS
RED_PHP_8_3=824 tests / 7002 assertions / exactly 1 failure
RED_ONLY_FAILURE=H7CCompanyDestructiveActionUxTest
PROVEN_DEFECT=active company archive was direct one-click POST
PRODUCTION_CHANGED_AT_RED=NO
```

## Initial GREEN — automated PASS

```text
H7C_A_INITIAL_GREEN=d8cfbe04a924304cb1234153fe873a34bef4bab4
H7C_A_INITIAL_GREEN_CI=39049794704
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
PHPSTAN=PASS
PHP_8_3_RESULT=824 tests / 7007 assertions / 0 failures
```

Initial RED->GREEN production diff:

```text
FILES=1
FILE=app/Core/Http/views/companies.php
ADDITIONS=27
DELETIONS=12
CONTROLLERS_CHANGED=NO
ROUTES_CHANGED=NO
SCHEMA_CHANGED=NO
CSS_CHANGED=NO
JS_CHANGED=NO
PERMISSIONS_CHANGED=NO
WORK_CHANGED=NO
FINANCIAL_CHANGED=NO
429_ARCHITECTURE_CHANGED=NO
```

## Acknowledgement hardening — RED proven

Forensic review before browser certification found that the HTML checkbox was `required` but the backend accepted a direct POST without `confirm_permanent_delete`. Browser-native validity alone was therefore insufficient.

```text
H7C_A_ACK_RED=ff0255d4826e31948852c1fee5dff40131b705e6
H7C_A_ACK_RED_CI=39054722005
ACK_RED_WORKFLOW=EXPECTED FAILURE
ACK_RED_PHP_8_3=825 tests / 7011 assertions / exactly 1 failure
ACK_RED_ONLY_FAILURE=H7CCompanyPermanentDeleteAcknowledgementTest
ACK_PROVEN_DEFECT=direct POST could bypass permanent-delete acknowledgement
PRODUCTION_CHANGED_AT_ACK_RED=NO
```

## Acknowledgement hardening — GREEN PASS

```text
H7C_A_GREEN=22384c2c616da89d34e37543cd42e8ae13c54e06
H7C_A_GREEN_CI=39055307246
GREEN_WORKFLOW=SUCCESS
PHP_8_3=PASS
PHP_8_4=PASS
PHP_8_5=PASS
PHPSTAN=PASS
PHP_8_3_RESULT=825 tests / 7020 assertions / 0 failures
REAL_MELI_HTTP=0
```

ACK RED->GREEN diff is bounded to:

```text
PRODUCTION_FILE=app/Core/Http/CompanyManagementController.php
TEST_COMPAT_FILE=tests/Integration/CompanyManagementTest.php
NEW_RED_TEST=tests/Integration/H7CCompanyPermanentDeleteAcknowledgementTest.php
ROUTES_CHANGED=NO
SCHEMA_CHANGED=NO
CSS_CHANGED=NO
JS_CHANGED=NO
PERMISSIONS_CHANGED=NO
WORK_CHANGED=NO
FINANCIAL_CHANGED=NO
429_ARCHITECTURE_CHANGED=NO
```

Implemented contract:

```text
ACTIVE COMPANY:
  archive behind native <details>
  summary=Archivar empresa
  factual impact copy
  /archive + CSRF preserved

ARCHIVED COMPANY:
  Reactivar remains direct/restorative

DELETABLE ARCHIVED COMPANY:
  delete still depends on backend deletable fact
  native <details>
  factual delete conditions preserved
  required checkbox confirm_permanent_delete
  backend also requires confirm_permanent_delete=1
  missing/invalid acknowledgement returns 422 without deletion
  /delete + backend authority preserved
  user remains preserved

NO generic modal
NO schema/permission/JS architecture change
```

## H7C-A FINAL — independent real-browser evidence PASS

Antigravity V3 certified actual local Chrome on the **disposable TEST runtime**; ChatGPT independently verified the uploaded ZIP digest, exact 29/29 manifest hashes, report equality, screenshots, JSON gates, browser network log, DB snapshots and live GitHub ancestry/CI/PR.

```text
H7C_A_BROWSER_RESULT=PASS
H7C_A_INDEPENDENT_AUDIT=PASS
H7C_A_FULLY_CLOSED=YES
CERTIFIED_HEAD=95e15d84afe11fba4752d7bbdb6b32b456b58858
FUNCTIONAL_GREEN=22384c2c616da89d34e37543cd42e8ae13c54e06
FUNCTIONAL_GREEN_CI=39055307246 SUCCESS
CERTIFIED_HEAD_CI=39055734137 SUCCESS
REAL_LOCAL_CHROME=YES
BROWSER=Google Chrome 155.0.8059.84
DESKTOP=1440x900 PASS
MOBILE=390x844 PASS
SYSTEM_ADMIN_HTTP=200
COMPANY_ADMIN_NON_SYSTEM_HTTP=403
ARCHIVE_DELETE_DISCLOSURES=PASS
KEYBOARD_ENTER_SPACE=PASS
DELETE_CHECKBOX_NATIVE_VALIDITY=PASS
H7A_SHARED_SHELL=PASS
MOBILE_NAV_SLOTS=5
HORIZONTAL_OVERFLOW=NO
BOTTOM_NAV_OBSTRUCTION=NO
APPLICATION_5XX=0
APPLICATION_UNCAUGHT_JS_ERRORS=0
REAL_MELI_BROWSER_NETWORK_CALLS=0
DESTRUCTIVE_ROUTE_POSTS=0
DB_BEFORE_AFTER=IDENTICAL
BUSINESS_SIDE_EFFECTS=0
CERTIFIER_GIT_BEFORE_AFTER=CLEAN
CERTIFIER_CODE_TEST_DOC_EDITS=0
CERTIFIER_PUSH=NO
CERTIFIER_MERGE=NO
PR_19=OPEN / DRAFT / NOT MERGED
EVIDENCE_ZIP_SHA256=177f76c87774f20962219743424341384349f0e30a0d086d2cb061eac87d1e88
EVIDENCE_MANIFEST=29/29 EXACT
ZIP=ERP_MELI_2_0_H7C_A_BROWSER_ANTIGRAVITY_EVIDENCE_2026-10-17.zip
REPORT=ERP_MELI_2_0_H7C_A_BROWSER_ANTIGRAVITY_REPORT_2026-10-17.md
SIDECAR=ERP_MELI_2_0_H7C_A_BROWSER_ANTIGRAVITY_EVIDENCE_2026-10-17.zip.sha256
```

Minor non-blocking observations: the Chrome favicon GET returned 404, and the Apps launcher focus-inside snapshot was taken before waiting for its animation-frame focus; Escape and focus-return behavior are evidenced. They do not justify reopening H7C-A without fresh proof.

## H7C-B1 — MELI account disconnect (FULLY CLOSED)

Owner: `app/Modules/Settings/views/meli-accounts.php`; the controller, routes, CSRF, company scoping, work guard and account token/status behavior are unchanged.

```text
H7C_B1_RED=d6b96bcbc5b7189b231ae84c8e03ed881858d912
H7C_B1_RED_CI=39070718318 EXPECTED FAILURE
RED_PHP_8_3=826 tests / 7022 assertions / exactly 1 failure
RED_ONLY_FAILURE=MeliAccountLifecycleUiTest::testDisconnectIsBehindNativeHumanDisclosureWithFactualImpact
H7C_B1_GREEN=ab640fe222bc33fc9083fb25596b41133f37bab8
H7C_B1_GREEN_CI=39070978051 SUCCESS
GREEN_PHP_8_3=826 tests / 7027 assertions / 0 failures
GREEN_PHP_8_4=PASS
GREEN_PHP_8_5=PASS
GREEN_PHPSTAN=PASS
RED_TO_GREEN_ONLY=app/Modules/Settings/views/meli-accounts.php (+8/-4)
CONTROLLER_CHANGED=NO
ROUTES_CHANGED=NO
CSS_CHANGED=NO
JS_CHANGED=NO
SCHEMA_CHANGED=NO
WORK_CHANGED=NO
FINANCIAL_CHANGED=NO
```

The direct `Desconectar` one-click POST is now in a native `<details>` (closed by default). Factual copy explains local credential deletion, ERP disablement, history preservation and possible later reauthorization. POST and CSRF retained within the disclosure.

### Independent real-browser audit — PASS

Antigravity Chrome 155 on isolated disposable TEST runtime certified **HEAD** `ce09d7b21bb6d619884307959b13c58cd9faf842`. ChatGPT independently validated all attached artifact hashes and ZIP entries; reviewed desktop/mobile screenshots, HTML/DOM, keyboard, authorization, shell, console/network, before/after database, Git trace and live GitHub CI.

```text
H7C_B1_BROWSER_RESULT=PASS
H7C_B1_INDEPENDENT_AUDIT=PASS
H7C_B1_FULLY_CLOSED=YES
CERTIFIED_HEAD=ce09d7b21bb6d619884307959b13c58cd9faf842
CERTIFIED_HEAD_CI=39071315439 SUCCESS
ZIP_SHA256=ce44f76272dd5743abc23f60ef6ea95a151e9a34e8944a32180172aebca71350
MANIFEST=26/26 EXACT
ZIP_ENTRIES=27
REPORT_MATCHES_ZIP=YES
REAL_LOCAL_CHROME=YES
DESKTOP_1440x900=PASS
MOBILE_390x844=PASS
COMPANY_ADMIN_HTTP=200
NON_ADMIN_HTTP=403
DETAILS_DEFAULT_CLOSED=YES
KEYBOARD_ENTER_SPACE=PASS
POST_AND_CSRF_INSIDE_DISCLOSURE=PASS
DIRECT_DISCONNECT_OUTSIDE_DISCLOSURE=NO
REAUTHORIZE_UNCHANGED=YES
H7A_SHELL=PASS
MOBILE_NAV_SLOTS=5
HORIZONTAL_OVERFLOW=NO
BOTTOM_NAV_OBSTRUCTION=NO
APP_JS_ERRORS=0
APP_5XX=0
DESTRUCTIVE_POSTS=0
REAL_MELI_BROWSER_CALLS=0
DB_BEFORE_AFTER=IDENTICAL
CERTIFIER_WORKTREE_AFTER=CLEAN
CERTIFIER_CODE_TEST_DOC_WRITES=0
CERTIFIER_COMMITS=0
CERTIFIER_PUSH=0
CERTIFIER_MERGE=0
PR_19=OPEN / DRAFT / NOT MERGED
```

Evidence originals (external to repository):
- `ERP_MELI_2_0_H7C_B1_ANTIGRAVITY_BROWSER_REPORT_2026-10-17.md`
- `ERP_MELI_2_0_H7C_B1_ANTIGRAVITY_BROWSER_EVIDENCE_2026-10-17.zip`
- `ERP_MELI_2_0_H7C_B1_ANTIGRAVITY_BROWSER_EVIDENCE_2026-10-17.zip.sha256`

Incidental favicon 404 and deliberate non-admin HTTP 403 are classified, not evidence of application malfunction. Browser-session zero network calls does not imply machine-wide capture.

## H7C-B2 — MELI hard delete, automated GREEN; real browser PENDING

Precise owner `app/Modules/Settings/MeliAccountController.php` and `app/Modules/Settings/views/meli-accounts.php`. Root human-confirmation defect was proven: a company-admin CSRF-valid POST deleting an eligible disabled pristine account without `confirm_permanent_delete` returned 303 instead of 422. Existing company/CSRF/status/history authorization gates remain authoritative and unchanged.

RED:
```text
H7C_B2_RED=3595880b6f4e6cdaeef6cc0ebff9f85c0e7cb723
H7C_B2_RED_CI=39072807611 EXPECTED FAILURE
RED_PHP_8_3=827 tests / 7028 assertions / 1 failure
RED_ONLY_FAILURE=MeliAccountLifecycleTest::testHardDeleteRequiresExplicitAcknowledgementEvenWithValidCsrf
RED_OBSERVED_HTTP=303
RED_EXPECTED_HTTP=422
RED_PRODUCTION_CHANGED=NO
```

GREEN:
```text
H7C_B2_GREEN=d00eb26f37488d8552d2b1598d5b27dcb76967dd
H7C_B2_GREEN_CI=39073100289 SUCCESS
GREEN_PHP_8_3=828 tests / 7037 assertions / 0 failures
GREEN_PHP_8_4=828 tests / 7037 assertions / 0 failures
GREEN_PHP_8_5=828 tests / 7037 assertions / 0 failures
PHPSTAN=PASS ALL 3
GREEN_FILES=app/Modules/Settings/MeliAccountController.php;app/Modules/Settings/views/meli-accounts.php;tests/Integration/MeliAccountLifecycleTest.php
ROUTES_CHANGED=NO
SCHEMA_CHANGED=NO
CSS_CHANGED=NO
JS_CHANGED=NO
WORK_CHANGED=NO
FINANCIAL_CHANGED=NO
OAUTH_CHANGED=NO
REAL_MELI_HTTP=0
```

Controller: on disabled pristine account after existing history checks, missing/invalid ack returns 422 without deletion, only exact `confirm_permanent_delete='1'` can proceed. UI: native `<details data-meli-destructive-action="delete">` closed by default, factual local-only copy, form CSRF, required checkbox, explicit destructive button. Positive delete and 404/409/419 regression tests retained; RED + HTML test now pass.

## H7C-B2 independent real-browser forensic closure — PASS

Antigravity certified real Chrome 155.0.8059.84 on an isolated TEST runtime. ChatGPT independently checked both attached ZIPs (same 27 internal file bytes but ZIP compression/order differing), every 26/26 manifest SHA-256, report byte equality, 5 screenshots, DOM/keyboard/HTML5 native validity, role 200/403, responsive mobile geometry, H7A shell, console/network, DB before/after, Git and live CI.

```text
H7C_B2_RED=3595880b6f4e6cdaeef6cc0ebff9f85c0e7cb723
H7C_B2_RED_CI=39072807611 EXPECTED FAILURE
H7C_B2_GREEN=d00eb26f37488d8552d2b1598d5b27dcb76967dd
H7C_B2_GREEN_CI=39073100289 SUCCESS
H7C_B2_GREEN_PHP_8_3=828 tests / 7037 assertions / 0 failures
H7C_B2_GREEN_PHP_8_4=PASS
H7C_B2_GREEN_PHP_8_5=PASS
H7C_B2_GREEN_PHPSTAN=PASS
BROWSER_CERTIFIED_HEAD=21968d5ba0770f59db3e88348bb90d4ff6c506f6
BROWSER_CERTIFIED_HEAD_CI=39073464183 SUCCESS
H7C_B2_BROWSER_CERTIFIED=YES
H7C_B2_INDEPENDENT_AUDIT=PASS
H7C_B2_FULLY_CLOSED=YES
BROWSER_CHROME=155.0.8059.84
DESKTOP=1440x900 PASS
MOBILE=390x844 PASS
COMPANY_ADMIN_GET=200
OPERATOR_GET=403
ELIGIBLE_DISABLED_PRISTINE_DELETE=VISIBLE_IN_CLOSED_DETAILS
CONNECTED_DELETE=HIDDEN
DISABLED_WITH_HISTORY_DELETE=HIDDEN
ATTENTION_DELETE=HIDDEN
DELETE_REQUIRED_ACK_DEFAULT_UNCHECKED=PASS
NATIVE_VALIDITY_UNCHECKED_FALSE_CHECKED_TRUE_RESTORED_FALSE=PASS
KEYBOARD_TAB_ENTER_SPACE=PASS
SHARED_SHELL_H7A=PASS
MOBILE_NAV_SLOTS=5
HORIZONTAL_OVERFLOW=NO
BOTTOM_NAV_OBSTRUCTION=NO
UNCAUGHT_JS=0
CONSOLE_ERROR_RAW_COUNT=1 EXPECTED_403_CHROME_ERROR_PAGE
FAVICON_404=2 INCIDENTAL
PRODUCT_JS_DEFECT_PROVEN=NO
BROWSER_REAL_MELI_CALLS=0
BROWSER_DESTRUCTIVE_POSTS=0
APPLICATION_5XX=0
DB_BEFORE_AFTER=IDENTICAL
CERTIFIER_REPO_CHANGES=0
CERTIFIER_COMMITS_PUSHES_MERGES=0
MANIFEST=26/26 EXACT
EVIDENCE_ZIP_SHA256=f9e95998b5cb0802bab5a7df271715552c9b55b039e3b10bac9583ee0abb8753
SUPPLEMENTARY_ZIP_SHA256=9342658d07222ef427f75b7dfc8324b1b60db8ab1931193bcce0b2f9619adccd
PR_19=OPEN / DRAFT / NOT MERGED
```

External evidence received:
- `ERP_MELI_2_0_H7C_B2_BROWSER_ANTIGRAVITY_REPORT_2026-10-17.md`
- `ERP_MELI_2_0_H7C_B2_BROWSER_ANTIGRAVITY_EVIDENCE_2026-10-17.zip`
- `20261017-172800.zip` (same internal contents, differing archive bytes)
- User supplied final ZIP hash in certifier output; separate `.zip.sha256` was NOT attached. ChatGPT independently calculated the final ZIP SHA and created the sidecar, so the verification is independent, not a claim of certifier delivery.

No product defect is proven by the known expected operator GET 403 Chrome console-network message; it remains transparently counted as one in raw evidence. No fixes/patches warranted.

## H7C-C — System clear debug — FULLY CLOSED (independent Chrome V5 audit)

Precise owner: `app/Modules/Settings/SystemSettingsController.php::clearDebug()` and `app/Modules/Settings/views/system.php`. Existing route `POST /settings/system/debug/clear`, existing `DebugMaintenance::clearDebug()` recognizes only local debug files; no schema/route/JS/CSS/remote MELI modifications.

Test-only RED:
```text
H7C_C_RED=b1fe1533db7809f689c398d111d4292474b5c31a
H7C_C_RED_CI=39079965535 EXPECTED FAILURE
RED_PHP_8_3=829 tests / 7040 assertions / exactly 1 failure
RED_ONLY_FAILURE=DebugSettingsControllerTest::testClearDebugRejectsMissingOrInvalidHumanConfirmationWithoutDeletingFiles
RED_OBSERVED=303 with valid CSRF and missing confirm_clear_debug
RED_EXPECTED=422 without deleting temp debug files
RED_PRODUCTION_CHANGED=NO
```

Minimal GREEN:
```text
H7C_C_GREEN=901b8652c825251f6189f6a11bea8c6ba7f198af
H7C_C_GREEN_CI=39080177081 SUCCESS
GREEN_PHP_8_3=829 tests / 7049 assertions / 0 failures
GREEN_PHP_8_4=829 tests / 7049 assertions / 0 failures
GREEN_PHP_8_5=829 tests / 7049 assertions / 0 failures
GREEN_PHPSTAN=PASS
RED_TO_GREEN_FILES=3
RED_TO_GREEN_CONTROLLER=app/Modules/Settings/SystemSettingsController.php (+3/-0)
RED_TO_GREEN_VIEW=app/Modules/Settings/views/system.php (+9/-4)
RED_TO_GREEN_TEST=tests/Integration/DebugSettingsControllerTest.php (+12/-1)
ROUTES_CHANGED=NO
SCHEMA_CHANGED=NO
CSS_CHANGED=NO
JS_CHANGED=NO
DEBUG_MAINTENANCE_ALGORITHM_CHANGED=NO
OAUTH_CHANGED=NO
WORK_CHANGED=NO
FINANCIAL_CHANGED=NO
REAL_MELI_HTTP=0
```

Existing System Admin DB authorization, CSRF 419, optional DebugMaintenance 503 and recognized-file deletion preserved. Added exact server acknowledgement `confirm_clear_debug='1'`, otherwise 422 without file deletion. UI now has native closed-by-default `<details data-system-destructive-action="clear-debug">`, factual scope copy (recognized local debug files only, preserving ZIP exports, unrelated files, business data), required checkbox, and original CSRF-protected POST inside.

### V5 — Real Chrome certification + independent audit (2026-10-21)

- Tester: Codex Luna in disposable loopback Chrome 155.0.8059.85, 1440×900 desktop and 390×844 mobile; product code and shared checkout unchanged.
- Exact certified browser HEAD: `970153033003547a16b2c968db6ba4d0264cdb03`; GREEN→HEAD changed only `docs/CURRENT_CHECKPOINT.md` and `docs/PROJECT_CONTINUITY_CURRENT.md` (two docs-only commits).
- Independent evidence: `ERP_MELI_2_0_H7C_C_CODEX_LUNA_BROWSER_REPORT_V5_2026-10-21.md`, `ERP_MELI_2_0_H7C_C_CODEX_LUNA_BROWSER_EVIDENCE_V5_2026-10-21.zip`, matching `.zip.sha256`; independent verdict `ERP_MELI_2_0_H7C_C_AUDITORIA_INDEPENDIENTE_CIERRE_V5_2026-10-21.md`.
- ZIP SHA-256 `c3db19754c9ea438032ddafe36827d32ab22d33e5300a04c209a42cfdf5c3f44`; 31 members, 30/30 manifest SHA, CRC PASS, external/internal report byte-identical.
- Server auth 200 System Admin / 403 non-System Admin; `<details>` closed by default; `confirm_clear_debug='1'` checked only when human intentionally toggles; native validity false→true→false, 26 real Tabs, Enter/Space/focus PASS.
- Mobile five navigation slots, no horizontal overflow, 35.734375px bottom-button clearance; H7A skip link and Apps opened by keyboard, focus inside and returned by Escape.
- Browser network 27 requests (25 GET, 2 synthetic login POST), 0 destructive/config/export/MELI POST, 0 JS uncaught and 0 HTTP 5xx. One raw console error attributable to intentional 403; not hidden.
- Before/after byte-identical deterministic DB snapshots including `system_settings`; debug and export filesystem inventories identical with file SHA-256.
- Independent V2 auditor: 108 PASS, two *non-substantive text-format false negatives*, adjudicated via original GitHub and artifacts; report's RED expected/observed order typo documented against official CI log (actual original 303 vs expected 422).
- Scope at the V5 capture: isolated TEST, no real destructive POST or production deployment. H7C-D1 status is recorded below. PR #19 remains OPEN/DRAFT/NOT MERGED.

## H7C-D1 — System Admin revocation confirmation (FULLY CLOSED; independent evidence audit PASS)

BASE_HEAD=27f6e222ae7077de3d4a33af1423c8bdc70d1aa2
RED_LOCAL=9d954ad17ced0d53ba3f62740f92403048b54dd3
RED=5950fd92cd0cc213a8a06c73f977c10f1e4c5b57
RED_CI=39661909819 EXPECTED_SINGLE_FAILURE
GREEN_LOCAL=6d7feafe52227187399a3d51e04d2581ad5a3982
GREEN=44e50342a5dcb606e90c07b4ad199102a434344d
GREEN_CI=39666739964 SUCCESS
PHP_8_3_8_4_8_5=834 tests / 7156 assertions / 0 failures per matrix
LOCAL_PHPUNIT=834 tests / 7153 assertions / 1 Windows symlink skip
PHPSTAN=PASS
TEST_DB_ISOLATED=YES
BROWSER_OBSERVED=PASS
BROWSER_INDEPENDENT_AUDIT=PASS_WITH_DOCUMENTED_LIMITATIONS
D1_FULLY_CLOSED=YES
D2_STARTED=NO
CODE_OWNER_FILES_CHANGED=3
REMOTE_WRITES=OFF
REAL_MELI_HTTP=0

- RED reproduced the intended defect: the valid, unconfirmed self-revocation returned 303 instead of 422. RED CI had that single failure in each PHP matrix.
- GREEN changes only UserManagementController.php, views/users.php and UserManagementTest.php. The exact string acknowledgement is checked after last-active-admin protection and before UPDATE. Existing route, transaction, authorization, CSRF, session and membership behavior remain covered.
- Chrome 155.0.8059.85 observed 200 for the System Admin and 403 for a restricted user. Desktop 1440×900 and mobile 390×844: collapsed disclosures, required checkbox, false→true→false native validity, Enter/Space/Tab, direct grant without confirmation, Apps Enter/Escape focus return, no horizontal overflow, and consent controls clear of the fixed mobile nav all PASS.
- Browser sent no revocation/configuration/export/MELI POST. Synthetic DB snapshots for users, company_users, companies and system_settings match before/after; relevant storage file hashes also match.
- Evidence: H7C_D1_BROWSER_REPORT_2026-10-21.md and H7C_D1_BROWSER_EVIDENCE_2026-10-21.zip. ZIP SHA-256: b56332f1e88194298dc1b1761eda8bbebe84349c599ab31829955e61bd1a4cef. Matching .zip.sha256 and per-member SHA256SUMS.txt are included. Codex observation is not independent certification.
- The local suite skipped the symlink test on Windows; CI Linux ran it, accounting for the three-assertion difference. PHPUnit local had zero failures/errors. The full suite output retains expected diagnostics from negative tests.

### Independent audit closure — 2026-10-21

- Inputs: `H7C_D1_BROWSER_REPORT_2026-10-21.md`, `H7C_D1_BROWSER_EVIDENCE_2026-10-21.zip`, matching `.zip.sha256`. Independent conclusion: `ERP_MELI_2_0_H7C_D1_AUDITORIA_INDEPENDIENTE_CIERRE_2026-10-21.md` (conversation artifact).
- ZIP SHA-256 `b56332f1e88194298dc1b1761eda8bbebe84349c599ab31829955e61bd1a4cef`, CRC and extraction PASS, 23 ZIP members/22 SHA manifest members verified, report outside = ZIP report byte-identical. Three screenshots inspected.
- Real GitHub confirms RED `5950fd92cd0cc213a8a06c73f977c10f1e4c5b57`, run `39661909819` one expected 303-vs-422 failure on each PHP 8.3/8.4/8.5; GREEN `44e50342a5dcb606e90c07b4ad199102a434344d`, run `39666739964` SUCCESS; browser HEAD `63ba16cc239339fa263afebe6e3093e4add97add`, run `39669622138` SUCCESS, 834 tests/7156 assertions per PHP version, PHPStan PASS. GREEN→HEAD docs-only.
- Owner server guard `confirm_remove_system_admin==='1'` for actual revocation after last-admin protection, before UPDATE; no-ack 422/DB unchanged; existing 403/419/404/409, valid 303, self-session cleanup, company membership preservation verified in code/tests.
- Isolated Chrome 155.0.8059.85 (1440×900 and 390×844), admin 200/non-System Admin 403; collapsed disclosure, required checkbox checkValidity false→true→false, keyboard, five-slot mobile nav, Apps focus return, no destructive browser POST/MELI call. DB JSON before/after byte-identical for users, company_users, companies, system_settings; six synthetic .gitkeep FS inventory entries byte-identical.
- Transparency: Chrome was executed by Codex, then its artifacts were independently checked, not replayed by ChatGPT. DB rows not contained in ZIP (table hashes and row counts only); storage inventory was empty placeholders, so it certifies absence of observed mutation, not a real revocation or deletion. Raw admin console has 4 VERBOSE autocomplete messages; two incidental login favicon404s; 403 console error expected. One Windows symlink test skip executed on green Linux CI.
- Product change frozen; this closure changes only continuity docs/PR, no production deployment or merge.

## Exact resume point

NEXT=H8-A APP CREDENTIAL CLEAR REAL-BROWSER CERTIFICATION ONLY
H7C_A_FULLY_CLOSED=YES
H7C_B1_FULLY_CLOSED=YES
H7C_B2_FULLY_CLOSED=YES
H7C_C_AUTOMATED_GREEN=YES
H7C_C_BROWSER_CERTIFIED=YES
H7C_C_INDEPENDENT_AUDIT=PASS_WITH_MINOR_DOCUMENTARY_NOTES
H7C_C_FULLY_CLOSED=YES
H7C_D_STARTED=YES
H7C_D1_AUTOMATED_GREEN=YES
H7C_D1_BROWSER_OBSERVED=PASS
H7C_D1_BROWSER_INDEPENDENTLY_CERTIFIED=YES
H7C_D1_INDEPENDENT_AUDIT=PASS_WITH_DOCUMENTED_LIMITATIONS
H7C_D1_FULLY_CLOSED=YES
H7C_D2_STARTED=YES
H7C_D2_AUTOMATED_GREEN=YES
H7C_D2_BROWSER_OBSERVED=PASS
H7C_D2_BROWSER_INDEPENDENTLY_CERTIFIED=YES_WITH_COMPOSITE_EVIDENCE_AND_LIMITATIONS
H7C_D2_FULLY_CLOSED=YES
H7C_PHASE_FULLY_CLOSED=YES
H8_STARTED=YES
H8A_AUTOMATED_GREEN=YES
H8A_BROWSER_CERTIFIED=NO
H8A_FULLY_CLOSED=NO
H8B_STARTED=NO
H9_STARTED=NO
PR_19=OPEN / DRAFT / NOT MERGED
MERGE_AUTHORIZED=NO
STOP_SAFE=YES

### H7C-D2 — confirmación humana para retirar membresías (2026-10-21)

START_HEAD=afeba7940aea51f0ceefa24ddba6c89c430599ac
RED_TEST_ONLY_SHA=56f99973ce9706657dde859aded519f685b2efec
RED_CI=39674454144 EXPECTED_SINGLE_FAILURE (PHP 8.3/8.4/8.5)
GREEN_SHA=715f4a9ae93452df75fc5d32eaa0514accf12ea7
GREEN_CI=39678811548 SUCCESS (PHP 8.3/8.4/8.5 + PHPStan)
HEAD_AT_BROWSER=715f4a9ae93452df75fc5d32eaa0514accf12ea7
LOCAL_PHPUNIT=840 tests / 7315 assertions / 1 Windows symlink skip / 0 failures
PHPSTAN=PASS
COMPOSER_VALIDATE=PASS
PHP_LINT=PASS (230 PHP files)
TEST_DB_ISOLATED=YES (dedicated disposable MariaDB; `erp_meli2_test`)
BROWSER=Chrome 155.0.8059.85; desktop 1440x900; mobile 390x844
SYSTEM_ADMIN=HTTP 200; ordinary operator same-origin GET `/system/users`=403
MOBILE_NAV=5 slots; no horizontal overflow; consent checkbox and button clear of fixed nav
CONSENT=closed by default; required unchecked; checkValidity false→true→false; Enter/Space pass
APPS_KEYBOARD=4 Tab to launcher; Enter opens and focuses dialog; Tab stays inside; Escape closes and restores focus
BROWSER_PROHIBITED_POSTS=0 (only two synthetic login POSTs); external HTTP=0; application 5xx=0; uncaught JS=0
DB_BEFORE_AFTER=identical hashes/counts for users, company_users, companies, system_settings
FILESYSTEM_BEFORE_AFTER=identical 929-file inventory; filenames pseudonymized in evidence
CODE_OWNER_FILES_CHANGED=UserManagementController.php, views/users.php, UserManagementTest.php
D1_UNCHANGED=YES
EVIDENCE=H7C_D2_BROWSER_REPORT_2026-10-21.md + H7C_D2_BROWSER_EVIDENCE_2026-10-21.zip + .zip.sha256

### H7C-D2 independent composite closure — 2026-10-21

- ChatGPT independently verified D2 browser ZIP `95e0076e410eb3c9256f59f9fb2ef017c9600a3f8eca487a3e2989614dbf84aa`: CRC, 41/41 members, 40/40 hashes, report identical, original real Chrome admin 200/non-admin 403, keyboard and responsive evidence. GitHub: RED `39674454144` expected single failure; GREEN `39678811548` and browser HEAD `39681959280` SUCCESS, PHP 8.3/8.4/8.5 840 tests / 7318 assertions.
- Supplemental FS microcert ZIP `a3d227b52aabc2287eaf6eb3287858432409b708e5d4c47ad20e16d72e7d2ffd`: CRC, 7/7 members, 6/6 SHA, same source HEAD `ec6afa00a8327a6b7e1aa1383dfa5869ee55ce0f`; monotonic BEFORE finished before first GET, browser closed before AFTER; 12 synthetic files unchanged byte-for-byte, 7 storage directories, 527 content bytes. No prohibited POST/MELI call.
- Limitations kept visible: original 929-file browser interval **not** proven chronologically; microcert tool marked `BLOCKED` because a manual redirect login `POST /login` yielded `opaqueredirect`/aborted without observable 303 and no SHA of snapshot JSON was persisted at instant of capture. Nonetheless the same microcert run returned HTTP 200 at `/system/users`; existing `CompanyContext::assertSystemAdmin` verifies active System Admin directly in DB for that route. Independent adjudication accepts separate full original browser authorization evidence plus equivalent new filesystem interval; it does **not** change `MICROCERT_RESULT=BLOCKED` or claim contemporaneous cryptographic attestation.
- PHPUnit tested destructive POST on disposable MariaDB, not real Chrome. No product files changed for closure, no merge/deploy. Pre-existing `runtime.json`, `installed.lock`, user exports/temporary files must not be deleted without provenance checks.
- Independent report (conversation artifact): `ERP_MELI_2_0_H7C_D2_AUDITORIA_INDEPENDIENTE_FINAL_COMPUESTA_2026-10-21.md`. Next H8 preflight only; H8 implementation NOT started.


## H8-A — MELI Developer app local credential clear (automated GREEN; Chrome pending)

- READ-only preflight checked `app/Modules/Settings/MeliAppSettingsController.php`, `views/mercadolibre.php`, `MeliAppSettingsTest.php`, design authority and H8 master plan. Defect proven: privileged CSRF-valid `POST /settings/mercadolibre` with `action=clear` had no second acknowledgement and returned 303 while clearing local `MELI_CLIENT_ID` and `MELI_CLIENT_SECRET`.
- Test-only RED `994744c819aa781b9c287e995bdac50617f5253a`; CI `39700960434` EXPECTED FAILURE. Exactly one failure in each PHP 8.3/8.4/8.5: `MeliAppSettingsTest::testClearRequiresExplicitHumanConfirmationAndPreservesPrivateRuntime`, observed 303 vs expected 422 (841 tests / 7321 assertions at failure).
- First GREEN `042e52de4169a84ad96ee1a917123c12186c6b59`, CI `39701598048` SUCCESS (842 tests / 7363 assertions). Final functional GREEN `5ce08784a55779886703ac6f2125224c764bcd1d`, CI `39701894315` SUCCESS in PHP 8.3/8.4/8.5: **842 tests, 7371 assertions each, PHPStan PASS**.
- Changed owner paths only: `app/Modules/Settings/MeliAppSettingsController.php`, `app/Modules/Settings/views/mercadolibre.php`, `tests/Integration/MeliAppSettingsTest.php`. No routes, schema, new classes/frameworks, CSS/JS, OAuth, MELI remote HTTP, Billing/Financial/Work/429 modifications.
- Server now requires exact `confirm_clear_meli_credentials='1'` **before acquiring `install.guard`**, so invalid/missing ack returns 422 without rewriting `runtime.json` or creating the lock; preserve 403/419/409/503, server-managed read-only and valid 303 clear behavior.
- UI has native closed-by-default `<details data-meli-app-destructive-action="clear-credentials">`, factual scope copy, original CSRF and `action=clear`, required unchecked checkbox, clear danger CTA. Saving app credentials and server-managed settings remain untouched. Regression tests check byte-identical `runtime.json`, `installed.lock`, `install.guard`, plus DOM structure, no nested forms and no leaked secret.
- **Safety:** `MeliAppSettingsTest` prepares `storage/private/runtime.json` and `installed.lock` in its checkout; NEVER run this test suite in a shared local user checkout or against user's pre-existing storage. GitHub Actions uses disposable CI. Browser certifier must use isolated TEST clone/DB/storage.
- `H8A_AUTOMATED_GREEN=YES`, `H8A_BROWSER_CERTIFIED=NO`, `H8A_FULLY_CLOSED=NO`. Browser certification only on functional HEAD above or proven docs-only descendant. **Codex Luna may certify browser only, not change code or Git**. Do not start H8-B/H9; do not reopen H7C. PR #19 OPEN/DRAFT/NOT MERGED, no deploy/remote write.

## ERP1 / 429 frozen truth

ERP1 had Billing/Financial/Work instability including 429/FIFO/timing symptoms and architecture drift. Universal root cause remains:

```text
UNIVERSAL_ERP1_429_ROOT_CAUSE=NOT_PROVEN
```

No Financial/Work/429 retry, pacing, queue, batching or transport redesign without fresh evidence.

## Frozen / prohibited

No PR merge or merge commit without explicit approval, Hostinger, cron, real MELI mutation, broad navigation redesign, generic modal framework, UNKNOWN->0, patch-chain CSS, speculative ERP1 architecture, or reopening closed Human-First blocks without evidence.

## Consolidación local Work/FIFO y Financial/Billing

- Work/FIFO A+B y Financial/Billing están integrados únicamente en este candidato local; siguen sin publicar.
- `billing_details` y `billing_periods` son la fuente de verdad; `013_billing_detail_references` es un índice auxiliar.
- En bases con históricos, `013_billing_detail_references_backfill` debe quedar ausente hasta completar y verificar el backfill. Financial falla cerrado antes de ese gate. En una instalación nueva, la migración puede registrar el marcador si no existen detalles previos.
- Antes de una futura actualización con datos existentes: pausar escritores Billing, validar backup/restauración, migrar en orden y verificar backfill, cobertura, marcador y equivalencia antes de reanudar.
- `RELEASE_READY=NO`; esta consolidación no aplicó migraciones fuera de DB descartable.

## CHECKPOINT EXTERNO ACTUAL — G1-P1 2026-10-29

**Aviso de precedencia para iniciar chats nuevos:** este archivo contiene historia amplia y puede mostrar hitos locales más antiguos. El último checkpoint post G1-P1, con diez pasadas forenses y enlaces a hashes/capturas/WIP, es [ERP2_HANDOFF_10_PASADAS_POST_G1_P1_20261029.md](ERP2_HANDOFF_10_PASADAS_POST_G1_P1_20261029.md). El plan de ruta es [ERP2_PLAN_MAESTRO_CIERRE_PRODUCTO_20261028.md](ERP2_PLAN_MAESTRO_CIERRE_PRODUCTO_20261028.md), sección **G1-E**. No repetir `docs/NEXT_CODEX_G1.md` ni prompts G1/P1 antiguos.

Estado actual: `G1_P1_LOCAL_QA=PASS` (959 tests, 8448 asserts, un skip; ZIP 40/40 SHA), `MANUAL_UNCERTAIN_REVIEW=BLOCKED_BUSINESS_DECISION`, `G1_LOCAL_COMPLETE=NO`, `G1_RELEASE_CERTIFIED=NO`, `PR21=OPEN_DRAFT_NO_MERGE`, `REAL_MELI_HTTP=0`, `REMOTE_WRITES=OFF`, `WIP_HISTORICAL_8_OF_8=HASH_IDENTICAL_REPORTED`. **G1 functional code sigue SIN COMMIT en Windows y NO vive en GitHub.** El presente cambio es solo documental.


## ACTUALIZACIÓN DE RUMBO 29-OCT-2026 — G1-F PROPUESTA

La auditoría G1-P1 anterior de 10 pasadas sigue válida. **ÚNICA actualización posterior:** el plan maestro [ERP2_PLAN_MAESTRO_CIERRE_PRODUCTO_20261028.md](ERP2_PLAN_MAESTRO_CIERRE_PRODUCTO_20261028.md), sección **G1-F**, contiene propuesta **NO aprobada** para conciliación fail-closed de `uncertain_review`: confirmar mismo Work y datos antes de conciliar pedido manual; si no se demuestra ni éxito ni ausencia de efectos, conservar HOLD sin reenvío. No ejecutar nuevo prompt Codex ni cambiar código por esa propuesta. El límite G2 fue contrastado en el PR21: `MeliListingReadScope` es lectura HTTP acotada con validación de seller, pero `/__test/m02/listings-preview` sigue siendo TEST ONLY y no prueba una pantalla productiva. La versión activa del plan es G1-F y el handoff G1-P1 es evidencia histórica verificada, no una nueva autorización de release.


## G2-A LECTURA ÚNICA — CIERRE DE CONTEXTO 29-OCT-2026

**Checkpoint activo (suplementa, no reemplaza G1-P1):** El plan [ERP2_PLAN_MAESTRO_CIERRE_PRODUCTO_20261028.md](ERP2_PLAN_MAESTRO_CIERRE_PRODUCTO_20261028.md) ahora contiene **G2-A**, primera superficie de publicaciones **solo lectura**, requisitos y QA de proveedor/empresa comprobables. Contraste del PR21 demuestra `MeliListingReadScope` y `items.bulk` existentes; `MeliListingPreviewController` sigue TEST ONLY. Documentación ML consultada el 29-oct-2026 avala nuevo multiget, distingue vendedor privado y búsqueda pública y exige `search_type=scan` para más de 1000 ítems. Esto es diseño técnico documentado **NO código implementado, NO proveedor activo**. G1-F persiste `PROPOSAL_NOT_APPROVED`: ninguna reconciliación `uncertain_review` se autoriza por la palabra «continúa». La próxima tarea de ChatGPT es dirigir el siguiente slice seguro G2 y conservar G1 HOLD para release/cron/real Meli; Codex no tiene nueva orden. No duplicar QA 959 ni reabrir los 429 cerrados sin anomalía nueva.


## G2-A CANDIDATO FUENTE AISLADO — 2026-10-29

ChatGPT preparó un candidato de implementación **aislado, NO aplicado** para `/mercadolibre/listings` (solo lectura en simulador loopback local/test, F14 requerido para proveedor real). Paquete de conversación: `ERP2_G2A_PUBLICACIONES_SOLO_LECTURA_CANDIDATO_20261029.zip`, SHA-256 `e3746cfae90e236c3edb47ceaf4ec5fa3eafdcfc7439d88ecbb09a04f03a1d78` (11.731 bytes). **El ZIP no está disponible automáticamente desde GitHub; el usuario debe conservarlo/adjuntarlo físicamente a Codex Luna.** Contiene controlador, vista, script preflight/aplicación y smoke, README y 5/5 manifiesto. Local ChatGPT: PHP lint PASS, smoke HTML/XSS/paginación PASS, instalador probado contra repo simulado (idempotencia y respaldo), **NO prueba de integración real/DB/browser**. El script exige HEAD PR21 `83e5dda...` y marcador aislado G1; si difiere, STOP sin rebase. Próximo residuo local: una única aplicación + QA focal/final en candidato Windows existente, luego auditoría de ChatGPT. `G2A_SOURCE_PREPARED=YES`, `G2A_APPLIED_TO_WINDOWS=NO`, `G2A_PRODUCT_READY=NO`, `G1_F_UNCERTAIN_REVIEW=UNAPPROVED_HOLD`, `REAL_MELI_HTTP=0`, `REMOTE_WRITES=OFF`. No se creó PR/commit funcional.


## G2-A CANDIDATO REVISADO V2 — SUSTITUYE V1 (2026-10-29)

**No usar/aplicar el ZIP inicial de 11.731 bytes:** `ERP2_G2A_PUBLICACIONES_SOLO_LECTURA_CANDIDATO_20261029.zip` (SHA256 `e3746cfae90e236c3edb47ceaf4ec5fa3eafdcfc7439d88ecbb09a04f03a1d78`) queda **SUPERADO ANTES DE SU EJECUCIÓN**. **Nueva única entrega para Codex:** `ERP2_G2A_PUBLICACIONES_SOLO_LECTURA_CANDIDATO_AUDITADO_V2_20261029.zip`, SHA256 `7c18458e9dca2fcba68e3dd0e19b144fa0852dccc8b73c27f98f504777c81e4c`, tamaño 12.528 bytes; archivo generado por ChatGPT **en la conversación**, no almacenado en GitHub. El usuario debe descargarlo y adjuntarlo físicamente al entorno Codex si se decide ejecutar. No reenviar V1.

**Motivo real de V2:** V1 confundía un `MeliRateLimitException(providerCooldown=true)` con pacing local; V2 clasifica respuesta 429 remota, cooldown existente y espera manual como eventos distintos. V2 suprime `ApiUsageRecorder` del **simulador local** para no registrar tráfico sintético como consumo real; conserva gate HTTP y cooldown local en DB desechable. Corrige tests focales de contrato. Fuente sin cambios: PR21 HEAD `83e5dda0a47d553bf79099ceec14290fcf564e8d`; código G1/P1 no versionado sigue en Windows. **No hay evidencia de que el usuario haya enviado V1 a Codex ni de que lo haya aplicado.** Si se descubre que se aplicó, no aplicar V2 ni forzar reset; reconcile y parar.

**Verificación propia ChatGPT V2:** CRC y manifiesto 5/5 SHA PASS, lint PHP 3 archivos PASS, smoke HTML/XSS/precio/paginación PASS, preflight/aplicación/segundo intento rechazado PASS en repositorio sintético con Git; **QA funcional MariaDB/browser, del candidato Windows y de proveedor real NOT_RUN**. `G2A_READY_TO_HANDOFF=YES`, `G2A_IMPLEMENTED_IN_WINDOWS=NO_VERIFICADO`, `G2A_RELEASE_CERTIFIED=NO`, `G1_UNCERTAIN_REVIEW=HOLD`, `REAL_MELI_HTTP=0`. Una sola operación Codex de integración+QA focal y suite final si V2 no fue ejecutado; no reabrir G1 ni fabricar permiso de deploy.


## G2-A V3 — CHECKPOINT INDEPENDIENTE POST EJECUCIÓN / 29-OCT-2026

**Última evidencia recibida (Codex, árbol LOCAL sin commit):** `ERP2-G2A-LISTINGS-LOCAL-READONLY-V3-ONCE-20261029` ejecutó `/mercadolibre/listings` solo simulador loopback; `APP_ENV=production` no envía HTTP real. ZIP entregado `ERP2-G2A-EVIDENCE-20261029.zip` SHA256 `badc5c756e4d8428d52bb3f59996d0b9bdde3edf65363a15fff7352eb322248f`; informe adjunto SHA256 `19abc23dde69fd4cbaa705a33d7c8099b1ce9e3fb02db216508ce0680e2c2d59`; ZIP CRC PASS. Logs documentan **970 tests / 8639 aserciones / 1 omitido / 0 fallos**, PHPStan/Composer/lint PASS, capturas reales 390×844 y 1440×900 incluidas y contrastadas byte a byte. Los G1-P1 39 archivos protegidos: 37 hashes iguales, 2 deltas previstos (rutas y checkpoint), 0 no autorizados **según evidencia del ejecutor**. Código G2-A NO está en PR21/GitHub (HEAD `83e5dda0a47d553bf79099ceec14290fcf564e8d`, OPEN/DRAFT al contraste del 29-oct).

**Hallazgo forense de ChatGPT — posible falso FAIL por raíz equivocada:** `wip-before.txt` muestra 8/8 HASH_MATCH para HEAD histórico `401034e8e3f8117a855bc274727649d37994050e`, y los ocho SHA coinciden exactamente con el handoff histórico previo. El checkout ORIGINAL de esos WIP es `C:\codex\ERP2-reports\ERP2-L4-M03-M04-QA-GATE-REPAIR-20261023\repo` (documentado en handoff maestro del 28-oct); el informe Codex comparó al final solo `C:\codex\ErpMeli 2.0` y el candidato `...\candidate`, sin reportar una lectura final de ese root original. Que GitHub no encuentre SHA `401034...` es compatible con commit únicamente local; tampoco permite afirmar pérdida. `WIP_FINAL_ORIGINAL_ROOT=NOT_VERIFIED`, `WIP_LOSS=NOT_ESTABLISHED`, `WIP_8_OF_8_FINAL=NOT_YET_CERTIFIED`. **No sobreescribir/recuperar WIP por conjetura.**

**Siguiente y único trabajo operativo necesario:** verificación SOLO LECTURA de existencia, HEAD y SHA de ocho WIP en el checkout histórico exacto, con fallback acotado si fue movido a D:. No repetir G2-A 970 tests, no reinstalar ZIP V3, no modificar G1/Work/FIFO/Financial/Billing, no registrar API real. El prompt focal en esta conversación es `ERP2_PROMPT_CODEX_LUNA_WIP_PROCEDENCIA_SOLO_LECTURA_G2A_20261029.md` (SHA256 `2550fc338e672a1e9189a62f230b17230726c7e6954dfb0b23c08f31e8051699`), no publicado en GitHub. `G2A_FUNCTIONAL_LOCAL_QA=PASS_EVIDENCE`, `G2A_LOCAL_COMPLETE=NO_UNTIL_WIP_PROVENANCE`, `G2A_RELEASE_CERTIFIED=NO`, `G1_UNCERTAIN_REVIEW=HOLD/DECISION_PENDING`, `REAL_MELI_HTTP=0`. Esta anotación **supersede** declaraciones anteriores del checkpoint que decían G2-A solo preparado/no aplicado.


## G2-A — PROCEDENCIA WIP RESUELTA / 29-OCT-2026 (ESTADO ACTUAL)

**ESTADO ACTUAL; SUPERA el STOP_GLOBAL del apartado G2-A V3 anterior.** Codex ejecutó la verificación focal READ_ONLY del checkout histórico EXACTO `C:\codex\ERP2-reports\ERP2-L4-M03-M04-QA-GATE-REPAIR-20261023\repo` y REPORTÓ que `git rev-parse HEAD=401034e8e3f8117a855bc274727649d37994050e`, 8 rutas WIP, 4 tracked modified + 4 untracked, ninguna fuera de la raíz, ninguno link. Informe adjunto `WIP_PROVENANCE_READONLY_20261029.md`, tamaño 5033 bytes, SHA256 **`f5cfbeb3ee4e209e2a335bbada4854a2cc5c1eddb38a3222c3c00bd7ca9f4412`** verificado por ChatGPT.

**Contraste realmente independiente:** ChatGPT leyó dicho informe y el ZIP previo `ERP2-G2A-EVIDENCE-20261029.zip` SHA256 `badc5c756e4d8428d52bb3f59996d0b9bdde3edf65363a15fff7352eb322248f`; los **8/8 SHA-256 esperados y reportados como observados coinciden EXACTAMENTE** con los 8/8 de `evidence/wip-before.txt` anterior. Se confirmaron directamente los SHA de `evidence/qa-full-final.log` `b56e0b7955857339ac92236ef3e5511f022e10d7883a60333604e95d5e5065c1` y `browser-qa-final.txt` `ee92889d54a625bde3c7159f8d9f443aff2f7e1856a67532d52399d9d0f3b1f6`; ZIP CRC PASS. La lectura de disco Windows/HEAD fue **ejecutada por Codex y reportada**, no por acceso directo de ChatGPT a ese Windows. Ya no hay evidencia de WIP perdido: el FAIL previo comparaba los **checkouts incorrectos**.

**DECISIÓN:** `WIP_8_OF_8=PASS_REPORTED_WITH_INDEPENDENT_HASH_CROSSCHECK`, `G2A_LOCAL_COMPLETE=PASS_SCOPE_ONLY`, `G2A_LOCAL_FUNCTIONAL_QA=PASS_EVIDENCE_REUSED` (970 tests / 8639 assertions / 1 skipped / 0 failures), `G2A_BROWSER=PASS_EVIDENCE_REUSED` (390×844 / 1440×900), `WIP_PROVENANCE_BLOCKER=RESOLVED`, `G2A_RELEASE_CERTIFIED=NO`, `PR21_LOCAL_G2A_UNCOMMITTED=YES`, `REAL_MELI_HTTP=0`. No reejecutar 970 tests, ZIP V3 ni verificación WIP sin delta. **NO** se infiere que estos ocho WIP estén dentro del candidato G2-A: permanecen protegidos en el checkout histórico SEPARADO.

**Invariantes vigentes:** `G1_UNCERTAIN_REVIEW=HOLD/DECISION_PENDING`, `G1_RELEASE_CERTIFIED=NO`, `MONTHLY_REPORT_FACTURABLE=NO`, `HOSTINGER_F14_F15_F16=NOT_RUN`, ninguna escritura remota/autorización real; PR21 OPEN/DRAFT y GitHub sin código G2-A. El viejo prompt `ERP2_PROMPT_CODEX_LUNA_WIP_PROCEDENCIA_SOLO_LECTURA_G2A_20261029.md` se ejecutó y está CERRADO, **NO REENVIAR**. `NEXT=CHATGPT_DIRECTION_G2_AND_UNCERTAIN_REVIEW_DECISION`; `CODEX_NEXT=NONE` hasta que exista un lote nuevo autorizado y diferente.
