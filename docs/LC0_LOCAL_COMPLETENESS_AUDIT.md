# LC0 — Auditoría de completitud local

Fecha: 2026-10-12. Autoridad auditada: `d03c6fc7ff95809e889c99b2a628fc83130b126a`, descendiente limpio de `6fd1a7af57a04be5d993e3542d1f164ea8d91601`; delta exclusivamente `docs/CURRENT_CHECKPOINT.md`. Rama `impl/v3-b-sales-audit-20261010`.

## Cierre LC1 de los P0 confirmados

Functional SHA `1ee9da64ea6d23b0f97424d72f1398dd37a8a416`. Fresh QA475 tests /3372 assertions /1 skipped /0 failures /0 errors; PHPStan, strict Composer, syntax163 and diff check PASS. RED partial8 expected failures, repair2, disabled10; GREEN combined200/1910. Se conservaron logs iniciales y RED confirmado; fixture Sales usa available_at explícito para no depender de timing. No P1/P2 implementado.

Delta: seis archivos productivos existentes + cinco de tests existentes; nuevas clases/tablas/migraciones/Work types/colas/workers/schedulers/services/deps/HTTP operations=0. Financial y OAuth refresh/cooldown/401 intactos. Corrección dentro de owners actuales, sin implementación de LC2.

- LC-P0-1 CLOSED: Sales Audit A/B rechaza206 y200 con X-Content-Missing; Billing200 declarado parcial reutiliza el defer206. Sin evidencia/facts/cursor/continuación ni HTTP adicional. El200 vacío completo conserva terminal.
- LC-P0-2 CLOSED: enqueue/dedupe/selección y defer del repair se ejecutan bajo el claim row lock y transacción actuales. Stale token=0 hijos y0 mutaciones; fallo de transición revierte hijo/padre/audit. Se conservan topology y repair wait existentes.
- LC-P0-3 CLOSED: CompanyContext verifica estado activo vigente; Bootstrap aplica esa autoridad a todas las superficies protegidas de sesión actuales. Disabled403 aun con sesión previa; cero writes/enqueue/OAuth; membership y usuarios activos continúan probados.
- Las reproducciones y matrix siguientes documentan el snapshot LC0, no bugs P0 todavía abiertos. Los P1/P2 enumerados no fueron implementados. LOCAL_PRODUCT_READY sigue NO, NEXT=LC2 P1 operator completeness, no iniciado. F14 liveDEFERRED; F15PAUSED; F16UNAUTHORIZED.

## Decisión

`LOCAL_PRODUCT_READY=NO`. Terminar esta auditoría no equivale a terminar el producto. Las dependencias externas no causan este NO: existen faltantes de corrección y operación reproducibles localmente. F15 PAUSED BY LOCAL-FIRST POLICY; F14 LOCAL CONTRACT READY / LIVE DEFERRED; F16 UNAUTHORIZED. No SSH, hosting, red externa, OAuth real, deploy, implementación funcional, push ni merge en LC0.

Prioridad de continuidad tras LC1: **LC2 — P1 operator completeness**. Este informe no autoriza ejecutarlo. La matriz original conserva evidencia LC0; los tres P0 están cerrados por el recibo LC1 superior.

## Evidencia y límites

- Se inspeccionaron código, ocho migraciones, registro HTTP, los siete CLI, rutas y vistas existentes, tests del mismo código, README, AGENTS y authority. No se tomó la lista del roadmap como funcionalidades nuevas obligatorias.
- QA sólo `127.0.0.1:3307/erp_meli2_test`, MariaDB desechable; transports inyectados/fakes. La primera corrida tuvo dos errores de conexión durante el arranque frío. Se conserva y se repite completa tras `SELECT 1`. La excepción Slim sobre `products` ausente durante `ErpApiHttpRouteTest` es una inyección deliberada para verificar que SQL no aparezca en la respuesta.
- Probes separados bajo `storage/exports/lc0`: validadores privados de páginas, sesión deshabilitada y claim de reparación obsoleto. No son nuevas pruebas de regresión ni suman al contador PHPUnit. Los probes privados demuestran aceptación por el validador; no afirman haber ejecutado una certificación completa hasta VALID/caught_up.
- Cobertura por clase, rutas, referencias y resultados reproducibles acompañan la evidencia local. No se usa porcentaje de líneas.
- Datos ERP1: sólo se consultó el informe histórico F13; no se abrió runtime, configuración, credenciales ni snapshot para volver a producir resultados.

## Matriz de módulos

Rutas base de archivos: `app/`, `tests/Integration/` y `tests/Unit/`. Cada fila contiene los ocho campos requeridos. COMPLETE_LOCAL indica el contrato local delimitado, no producto completo ni validación externa.

| Área | STATUS | CURRENT_CONSUMER | CURRENT_OWNER | FILES | TESTS | MISSING_LOCAL_BEHAVIOR | NOISE / DELETABLE | PRODUCTION_DEPENDENCY |
|---|---|---|---|---|---|---|---|---|
| F0 Truth/Preflight | COMPLETE_LOCAL | Operador CLI | RuntimePreflight | `Core/Runtime/RuntimePreflight.php`, `bin/runtime-preflight.php` | RuntimePreflightTest, PlatformCompatibilityContractTest | Ninguno en inspección local; PARTIAL general conserva cuotas/cron UNKNOWN externas | No inventar checker de hosting | Cuotas, permisos y runtime real externos |
| F1 Foundation | PARTIAL_LOCAL | Bootstrap, login, todos los módulos | AppConfig, Environment, Connection, Migrator, AuthService | `Bootstrap.php`, `Core/Config/*`, `Core/Database/*`, `Core/Auth/*`, `database/migrations/001_core.sql` | AppConfigTest, BootstrapTest, FreshInstallTest, MigrationsTest, AuthTenancyTest | Usuario disabled mantiene sesión admitida; falta bootstrap documentado de primer admin/empresa; navegación/selector de tenant incompletos | .env.example no es cargado por Environment; corregir guía, no añadir loader por defecto | Ninguna para demostrar estos faltantes |
| F2 Work | COMPLETE_LOCAL | SalesWorkProcessor | WorkRepository/WorkRunner/WorkExecution | `Work/*.php`, `bin/work.php`, `002_work_items.sql` | WorkRepositoryClaimTest, WorkStaleClaimTest, WorkRetryRecoveryTest, WorkRunnerLockTest, WorkExecutionTest, WorkCliEntrypointTest | Core probado; violación de claim en productor repair pertenece a F4 | Una tabla, tres tipos, ningún motor adicional | Activación/cron posterior |
| F3 Core + OAuth | COMPLETE_LOCAL | SyncOrder, SalesAudit, Billing, callback | MeliClient, OAuthAuthorizationFlow/Connect/Refresh, TokenCipher | `Integrations/MercadoLibre/{Client,Auth,Transport,Json}`, `config/meli_operations.php`, `003_meli_core.sql` | MeliClientBoundaryTest, MeliRateLimitTest, MeliClientLosslessNumbersTest, OAuthAuthorizationFlowTest, OAuthConnectServiceTest, OAuthRefreshServiceTest, OAuthHttpStartRouteTest, OAuthHttpCallbackGuardTest | Contrato genérico local disponible; allowlist de seller de Stage0 no existe, gate específico separado | No nuevo subsistema OAuth/rate/calibración | Grant, permisos, expiración real y seller autorizado |
| F4 Sales | PARTIAL_LOCAL | Webhook, UI Sales, audit, bridge, Financial | SyncOrderHandler, SalesAuditHandler/Repository/RepairHandler, SalesListController | `Modules/Sales/*`, `004_sales.sql` | SalesVerticalSliceEndToEndTest, SyncOrderDecimalPrecisionTest, SalesAuditCaptureHandlerTest, SalesAuditConfirmValidTest, SalesAuditRepairFanoutTest, SalesWorkPoisonLoopTest, SalesListHttpRouteTest | Captura parcial aceptada; repair crea hijo antes de comprobar claim; UI no muestra resultado audit ni pedidos más allá de últimos 100 | Continuaciones audit por página simplificables; no eliminarlas sin pruebas A/B/recovery | Sólo semántica/provider, no estos bugs |
| F5 Debug/Observability | COMPLETE_LOCAL | Settings, webhook/Work, operador | DebugRecorder/Maintenance/Export, AppLogger | `Core/Logging/*`, `Modules/Settings/*`, `bin/cleanup.php` | DebugRuntimeIntegrationTest, DebugCorrelationTest, DebugExportRangeManifestTest, DebugMaintenanceTest, OperationalRetentionTest | Ninguno en DVR limitado; controles globales de empresa admin requieren revisión específica | No incident store ni duplicación AppLogger/DVR; logger tiene consumidores de fallo reales | Capacidad/rotación operativa posterior |
| F6 Billing | PARTIAL_LOCAL | SalesWorkProcessor, Financial | BillingPeriodSyncHandler/Normalizer | `Modules/Billing/*`, `005_billing.sql` | BillingPeriodSyncHandlerTest, BillingDetailNormalizerTest, BillingSchemaTest, MeliBillingPeriodDetailsContractTest | 200 con X-Content-Missing puede marcar terminal; no productor inicial de billing.period.sync en app/bin; no lectura/ejecución manual segura de periodos para operador | Hint from_id duplica cursor sólo por dispatcher actual; C0 se conserva como probe explícito | Certificación período/detalles real separada |
| F6 Financial | COMPLETE_LOCAL | Tests; futura lectura de operador | FinancialReadModel | `Modules/Financial/FinancialReadModel.php` | FinancialFactSufficiencyTest | Modelo completo dentro de hechos disponibles; falta consumidor visible para conciliación (P1 operador, no lógica Financial) | Sin ledger/tabla/cola; no añadirlos | Facts oficiales ausentes siguen UNKNOWN |
| F7 Catalog | COMPLETE_LOCAL | ERP API, rehearsal | ProductRepository | `Modules/Catalog/ProductRepository.php`, `006_catalog.sql`, `bin/catalog-rehearsal.php` | CatalogProductRepositoryTest, ErpApiHttpRouteTest | Create/read/import probado; update/list no existe ni está prometido por contrato F7 actual; acceso operador falta | No links/suggestions/remoto; evaluar separar guardas rehearsal si algún día deja de ser consumidor real | Ninguna para contrato local |
| F8 Inventory | COMPLETE_LOCAL | ERP API | InventoryRepository | `Modules/Inventory/InventoryRepository.php`, `007_inventory.sql` | InventoryRepositoryTest, ErpApiHttpRouteTest | GET/PUT entero no negativo y null probados; falta formulario operador | No movimientos/reservas/warehouses sin consumidor | Stock remoto no es stock físico |
| F9 ERP API | COMPLETE_LOCAL | Sesión del usuario y futura UI | ErpApiController, CompanyContext | `Modules/Api/ErpApiController.php`, `Core/Http/Routes.php` | ErpApiHttpRouteTest | API actual completa; revocación de usuario disabled es defecto de autoridad compartida F1 | No REST CRUD especulativo | Ninguna |
| F10 Boundary | COMPLETE_LOCAL | Tests; contrato futuro, ningún caller productivo | InvoiceRequest | `Modules/Invoicing/InvoiceRequest.php` | InvoiceRequestTest | No adaptador/generación fiscal según contrato | DTO sólo referenciado por tests/authority: candidato DELETE/MERGE con payload bridge tras comparación, no necesidad de otra capa | Facturador externo posterior |
| F11 Bridge | COMPLETE_LOCAL | API de máquina/ejecutor externo | InvoicingBridge/Controller | `Modules/Invoicing/InvoicingBridge*.php`, `008_invoicing_bridge.sql`, `bin/invoicing-key.php` | InvoicingBridgeHttpRouteTest | API y aceptación probadas; no Companion; packs rechazados por contrato, no membership persistido | Sin intents/outbox/runner; no añadirlos | Ejecutor fiscal/Companion y publicación posterior |
| F12 PostSale | COMPLETE_LOCAL | Sources bridge | InvoicingBridge, artifacts | Mismos archivos/schema F11 | InvoicingBridgeHttpRouteTest: cancelación, nota enlazada, idempotencia/conflicto | Contrato local completo; falta superficie de revisión para operador (P1), no decisión legal | No derivar nota cliente desde Billing CREDIT_NOTE | Legalidad/generación fiscal externas |
| F13 Migration | COMPLETE_LOCAL | Operador rehearsal desechable | ProductRepository + catalog-rehearsal | `bin/catalog-rehearsal.php`, `docs/F13_MIGRATION_REHEARSAL.md` | CatalogProductRepositoryTest; guard CLI; evidencia 100 filas histórica | Rehearsal e identidad delimitados; no importer producción, que pertenece a cutover | Tokens/settings/estado derivado SKIP; no ETL universal | Creación/mapeo objetivo y cutover |
| F14 local READ | COMPLETE_LOCAL | Order sync; validadores puros/test caller | MeliClientResponse + registro | `Integrations/MercadoLibre/Client/MeliClientResponse.php`, `config/meli_operations.php` | MeliReadContractTest, MeliOrderGetContractTest, MeliClientLosslessNumbersTest | Helpers completos; no ruta productiva que resuelva pack/billing-info y los conecte a readiness. Mantener fail-closed no es un fallo local | No persistir ni inventar complete pack para desbloquear producción | Live GET y permisos DEFERRED |
| KISS-S1 | COMPLETE_LOCAL | Billing handler | completeCurrentClaim/deferCurrentClaim | `Work/WorkRepository.php`, `Modules/Billing/BillingPeriodSyncHandler.php` | BillingPeriodSyncHandlerTest::testTwoPagesAndTerminalReuseOneWorkAndRefundSuccessfulAttempts; WorkStaleClaimTest; rollback de defer | Paginas mantienen ID/dedupe; ninguna continuación hija Billing | No cambio requerido | Activación posterior, no gate local |

## Otras áreas obligatorias

| Área | STATUS | CURRENT_CONSUMER | CURRENT_OWNER | FILES | TESTS | MISSING_LOCAL_BEHAVIOR | NOISE / DELETABLE | PRODUCTION_DEPENDENCY |
|---|---|---|---|---|---|---|---|---|
| Authentication | PARTIAL_LOCAL | Login y sesión | AuthService/CompanyContext | `Core/Auth/AuthService.php`, `Core/Tenancy/CompanyContext.php`, Routes | AuthTenancyTest, FreshInstallTest | Revocar sesión de usuario disabled en autorización (probe reproducible) | Reusar autoridad actual, no RBAC framework | Ninguna |
| Company/tenant | PARTIAL_LOCAL | Sales/API/Settings/OAuth | Membership queries, CompanyContext | Controllers existentes | API/Sales/OAuth/bridge tenant tests | Selector UI y sesión activa; settings global accesible por admin de cualquier empresa debe distinguir operación global de autoridad tenant | MERGE verificaciones equivalentes sólo al corregir caso real | Ninguna |
| Admin flows | PARTIAL_LOCAL | Empresa admin/instalador | Routes, Settings | Routes, Settings view, bin | Settings/OAuth route tests | Provisión inicial sin manual SQL documentado; menú, selector, desconexión visual/estado account inexistentes | Provision inicial CLI aceptable, no CRUD admin general necesario | Cuentas reales después |
| Error handling | PARTIAL_LOCAL | Formularios y API | Controllers + Slim | Bootstrap, SystemSettingsController, Routes | ErpApiHttpRouteTest, DebugSettingsControllerTest | Retención 2/max_mb fuera de rango terminan excepción/500, no error 422; documentar/red antes de corrección | Reusar validadores/errores existentes | Ninguna |
| CLI/bootstrap/config | PARTIAL_LOCAL | Desarrollador/operador | Environment, bin | .env.example, AppConfig, bin | AppConfigTest, RuntimePreflightTest, CLI guards | README no define export env/provisión; bin/work.php no expone runManualOne; config producción permite claves vacías hasta usar OAuth | Guía mínima en piezas actuales; no Docker/dep/loader nuevo | Quotas/credentials real posteriores |
| Cleanup/retention | PARTIAL_LOCAL | cleanup, operador | WorkRepository, ApiUsageRecorder, DebugMaintenance | bin/cleanup.php, logging, schema | OperationalRetentionTest, DebugMaintenanceTest | webhook_events y evidencia sales_audit_orders crecen sin política de pruning; no demo de necesidad borrar baseline activo | No ejecutar purge de canónicos/artifacts; evaluar evidencia histórica reemplazada | Programar cleanup sólo producción |
| Migrations/schema | COMPLETE_LOCAL | Instalación/QA | Migrator | 001..008, Core/Database/Migrator.php | MigrationsTest, FreshInstallTest, *SchemaTest | No hueco fresh install; interrupción DDL parcial y migración desde versión persistida no probadas por fresh-reset | No tabla claramente muerta confirmada; sin schema de tests | Recovery sobre DB persistida gate cutover |

## A–E: clasificación sin mezclar gates

- **A COMPLETO LOCAL:** contrato Work, order canonical/exact money/tenant, rate safety 429, C0 acotado, Financial local, Catalog create/read/rehearsal, Inventory, ERP API actual, DTO F10, recepción bridge/artifacts, PostSale, mapeo/rehearsal F13, helpers F14 y same-Work S1. COMPLETE se limita a estos contratos.
- **B FALTA LOCAL OBLIGATORIO:** LC-P0-1..3 y LC-P1-1..4 abajo. Ninguno requiere proveedor real.
- **C MEJORA LOCAL OPCIONAL:** reducción continuaciones audit, retención evidencia, alineación README, DTO sin consumer, edición Catalog cuando se confirme necesidad, filtros/estética no bloqueantes.
- **D SÓLO VALIDABLE EXTERNAMENTE:** OAuth/grants, forma/semántica real packs/billing-info, cuotas/headers y disponibilidad endpoints; facturador/navegador/DIAN. No degradan A por sí solos.
- **E PRODUCCIÓN/CUTOVER:** target, backup, DB/user/config/app dedicated, migrations persistidas, mapeo cuentas, habilitación cron/manual claim, observación/rollback; F15 pausado.

## Flujos locales A–I

| Flujo | Prueba actual / resultado delimitado | Falta real |
|---|---|---|
| A Order | SalesVerticalSliceEndToEndTest: webhook Bootstrap → dedupe Work → fake transport → handler → canonical → UI tenant. SyncOrderDecimalPrecisionTest y MeliReadContractTest prueban seller/partial/exact money. LOCAL CONTRACT PASS | No dependencia externa para la cadena actual |
| B Pack | MeliOrderGetContractTest prueba transporte/path; MeliReadContractTest resuelve manualmente responses, filtra seller y rechaza miembros no resueltos; bridge rechaza todo pack. LOCAL CONTRACT PASS, integración automática NOT_IMPLEMENTED | No test único fake pack → resolver productivo → readiness: no resolver/consumer existe. No afirmar E2E automático; fail-closed deliberado, semántica externa pendiente |
| C Billing | BillingPeriodSyncHandlerTest: dos páginas y terminal, uno y mismo Work, facts/cursor/payload/defer atómicos, rollback y claim obsoleto. LOCAL CONTRACT PASS con excepción LC-P0-1 | Falta iniciación de periodo por consumidor operador; header parcial no protegido |
| D Financial | FinancialFactSufficiencyTest: Sales/Billing locales → KNOWN/UNKNOWN/CONFLICT, replay/tenant/read-only. PASS | Exposición operador faltante; no tocar modelo congelado |
| E Catalog | CatalogProductRepositoryTest + API: create/read, SKU tenant, rehearsal rollback/execute/replay/conflict. PASS para contrato actual | Update no implementado, no fingir prueba. Corrección de nombre/SKU es ampliación por necesidad futura, no excusa para inventar módulos |
| F Inventory | ErpApiHttpRouteTest: GET/PUT Bootstrap, enteros, negativo/floats inválidos, foreign404, null ≠ zero. PASS | Formulario operador reutilizando API |
| G Invoicing | InvoiceRequestTest cubre DTO independiente; InvoicingBridgeHttpRouteTest cubre Sales → sources/readiness → PDF/XML fake → hash/idempotencia/conflicto. PASS por dos contratos, no DTO integrado | No caller construye InvoiceRequest en producción. No generación fiscal local |
| H PostSale | Tests bridge cancelado antes/después factura, nota enlazada/idempotencia; Billing CREDIT_NOTE separado. PASS | Vista operador review/artifacts |
| I Service key | InvoicingBridgeHttpRouteTest: issue → bearer hash/read/source → artifacts → tenant/scope/revoke. PASS por clases/rutas | CLI real issue/revoke sin ejecución de Companion; CLI acepta setup admin |

## UI y rutas reales

UI: GET/POST login, POST logout; GET/POST settings/system y POST debug clear/export; GET sales y sales/{order_id}; POST sales/audits; POST company/select sin vista; GET oauth/connect/callback sin pantalla accounts. `/` no está registrado. GET health sólo dice ok, no verifica DB.

API: POST api/products, GET api/products/{id}, GET/PUT inventory; GET bridge sources, POST artifact, POST credit-note. No endpoints web Catalog list/update, Financial, Billing, historial audit, dashboard operativo, documentos descargables, cuentas/usuarios/empresas CRUD.

| Ausencia | Clasificación | Motivo |
|---|---|---|
| Menú/entrada válida, selector de empresas con membership y logout accesible | MUST_HAVE_BEFORE_PRODUCTION | Usuario multiempresa no puede elegir contexto desde UI; select redirige404 |
| Catálogo create/read y cantidad Inventory para usuario normal | MUST_HAVE_BEFORE_PRODUCTION | No usar consola HTTP/manual SQL como operación diaria |
| Billing periodo/cuenta tipo explícito iniciar/estado; leer reconciliación Financial existente | MUST_HAVE_BEFORE_PRODUCTION | Handler sin productor, modelo sin consumer; conciliación no es producto utilizable |
| Resultado auditoría y acceso a ventas anteriores a últimas100 | MUST_HAVE_BEFORE_PRODUCTION | La operación crea trabajo sin mostrar resultado; no permite consultar pedido anterior salvo URL conocida |
| Invoice/PostSale readiness/estado artifacts/revisión | MUST_HAVE_BEFORE_PRODUCTION | Resultado de negocio existe sólo en API machine |
| Instalación de primer admin/empresa; service keys; migrations, cleanup | ADMIN/CLI_ACCEPTABLE | Actos infrecuentes de instalación/administración; requieren procedimiento reproducible, no SQL improvisado |
| Edición básica Catalog | NICE_TO_HAVE (revisar necesidad antes de ampliar) | Contrato actual create/read; no afirmar update existente |
| Botón conectar/estado cuentas, errores de OAuth legibles | NICE_TO_HAVE local; necesario en rollout real | Ruta connect existe; grant real externo |
| Pantallas Work internas, incident store, calibration framework, RBAC genérico | NOT_NEEDED | No necesidad demostrada de negocio |
| Generación legal de factura/nota desde ERP web | NOT_NEEDED para core local | Responsabilidad ejecutor externo |

## Reconciliación MB-04..07

| Bloque histórico | Clasificación | Evidencia actual |
|---|---|---|
| MB-04 Work truth | IMPLEMENTED | Claim token, recuperación, cap5, active dedupe, atomic completion; consumidor repair requiere LC-P0-2, no nuevo engine |
| MB-05 Authority | IMPLEMENTED / STILL_NEEDED_LOCAL específico | FK company/account, membership, seller admission, scoped bearer; LC-P0-3 revocación disabled, selector y control global explícito |
| MB-06 Observability | IMPLEMENTED; incident-store propuesta NOT_NEEDED | DVR/logs/exports, usage daily, safe errors/correlation/cleanup; estado audit visible sí falta |
| MB-07 Calibration | EXTERNAL_ONLY; framework NOT_NEEDED | C0 CLI/probe y helpers F14 actuales; no nuevo subsystem |

## Work, productores y crecimiento

Tres tipos ejecutables: `order.sync`, `sales.audit`, `billing.period.sync`; tipos *.test sólo fixtures. Productor = clase productiva que llama enqueue; no contar tests.

| Tipo | Propósito | Producer count / productores | Completa cuando | Recursión/crecimiento | 429 | 206 | Retry |
|---|---|---|---|---|---|---|---|
| order.sync | Leer evento/pedido faltante, canonical Sales | 2: OrderWebhookReceiver, SalesAuditRepairHandler | Pedido admitido/persistido atómicamente o fallo terminal | Handler no produce Work; webhook crea sólo demanda nueva; repair una order por vez/dedupe. Claim obsoleto repair puede crear hijo (P0) | Same-claim defer hasta cooldown, cero inline | Rechazo contrato/seller/completitud sin mutation | 5xx/transport cap5; 401 una recuperación + un segundo GET |
| sales.audit | Captura A, repair secuencial, captura B para coverage | 3: SalesListController, SalesAuditHandler, SalesAuditRepairHandler | Captura page completa child siguiente; run terminal valid/attention/unavailable | Una continuación por página, no árbol exponencial. Pendientes ≤una continuación por run y una reparación; páginas históricas acumulan terminales. Repair defer30s con intentos reembolsados es polling local (0 HTTP propio). Bug P0 fuera de guard | Defer cero inline, cooldown orders | Falta guard parcial: aceptación demostrada | Remotos cap5; 401 bounded; repair no recrea failed/done sin resolver |
| billing.period.sync | Stream periodo/document_type | 0 productores iniciales en app/bin; operator/tests enqueue directo | Results vacío aceptable → done/caught_up | Una misma row, ID/dedupe y cursor; cero hijos; pending puede durar indefinidamente con 206/429 repetidos, no aumenta rows | Defer cooldown sin quemar intento | 206 defer30s sin facts/cursor; 200 partial-header hueco | 5xx/transport cap5; 401 una excepción refresh/retry |

Billing child continuation=NO. Financial Work/Queue=0. Ninguna cola por dominio ni scheduler. Sales Audit sigue con child continuación: la afirmación global "no child Work fan-out for pagination" es FALSE para Sales y TRUE para Billing. No confundir fan-out exponencial con continuación lineal. No se elimina ese flujo en LC0.

WorkRunner ejecuta hasta25 claims/45s CLI automático; límite temporal se comprueba entre claims, no cancela un claim en curso. ManualOne existe sólo como método probado, no opción CLI. Estado automation OFF bloquea runner automático pero **no** recepción/enqueue webhook ni POST audit: backlog puede formarse sin runner. Gates Stage0 OFF deberán considerar productores, no sólo cron.

## Call graph y 429

| Operación READ / caller | Máximo por invocación/claim | Excepción401 | 429 | Paginación |
|---|---|---|---|---|
| users.me / OAuthConnectService | 1 GET + 1 initial AUTH POST | Ninguna | Stop exception sin retry | Ninguna |
| orders.get / SyncOrderHandler | 1 GET normal; 2 GET máximo | Pre-refresh opcional + recovery POST opcional; máximo2 AUTH POST por claim si expira antes y luego401 | 0 retry inline; defer al retryAt | Ninguna |
| orders.search / SalesAuditHandler | 1 GET/página, 2 con401 | Mismo máximo AUTH2 de token preflight+recovery | 0 inline; durable family orders | Captura A/B siguiente Work; limit≤50; total finite para respuesta coherente; no techo total de demanda |
| billing.period.details / BillingPeriodSyncHandler | 1 página lógica, GET≤2 con401 | Mismo máximo AUTH2 | 0 inline; same Work defer | Siguiente claim mismo Work, limit1000, sin while |
| billing.period.details / BillingC0Probe | CLI1..20 GET explícitos; no Work | No refresh | Exception detiene loop | for acotado maxPages; no usar para Stage0 sin cap≤8 y una página |
| packs.get / MeliClient | 1 por request directo | Ninguna automática | Cliente bloquea cooldown | No consumer business actual; helper puro recibe miembros ya resueltos |
| orders.billing_info / MeliClient | 1 directo | Ninguna automática | Igual | No consumer business actual |

`oauth.token` es AUTH POST con dos callers (connect y refresh), no WRITE negocio. Registro no tiene operaciones WRITE implementadas hoy; guard WRITE probado con operación fake. No operación Shipment/fiscal-document-list existente.

Un request MeliClient despacha sólo una vez. El máximo por proceso Work CLI puede alcanzar25×(2 GET+2 AUTH POST)=100 llamadas en el peor caso teórico de tokens expiran+401; 45s no es techo estricto de calls durante un claim. Esto no pasó en LC0 y no es presupuesto válido para calibración. Sales Audit puede generar muchas llamadas por una petición de mes: demanda explícita, páginas A/B y repairs; UI no informa volumen/avance. High-risk path de consumo: Sales Audit, no Financial.

429: owner único `MeliCooldownRepository` (`app:family`), Retry-After/backoff+jitter, bloqueo antes de transport, intentos reembolsados; tests MeliRateLimitTest + transient handlers. Pacing 2s por instancia, no cuota compartida garantizada. Clientes concurrentes pueden despachar antes de observar nuevo429; runner lock reduce workers concurrentes pero no requests web independientes. Polling repair30s local y 206 Billing defer30s no tienen límite de espera total; crecimiento pending no exponencial. Reducir llamadas antes de arquitectura; ninguna nueva añadida.

## Financial freeze

Constructor único PDO, sólo SELECT y cálculo string/MariaDB DECIMAL, no INSERT/UPDATE/DELETE, dependencia Client/Transport/Work ni productor/ruta nueva. `FinancialFactSufficiencyTest::testFinancialProjectionIsTenantScopedAndReadOnly` y replay cubren invariantes. HTTP=0; Work=0; Queue=0; persistent derived ledger=0. Ausencia/currency conflict/ambiguous association permanecen UNKNOWN/CONFLICT, no ceros inventados.

## Schema y ruido

8 migraciones, 20 tablas de dominio + schema_migrations =21, confirmado con SHOW TABLES local después de reset. SHOW TRIGGERS=[]; no fuga de trigger de pruebas. No FLOAT/DOUBLE monetario, JSON sólo payload Work y contexto Billing allowlist. No tabla queue por dominio, ledger/incident store ni temporal/test en migraciones.

| Tabla(s) | Owner/consumer | Decisión |
|---|---|---|
| companies/users/company_users/system_settings | Auth/tenancy/settings | REUSE; settings singleton global exige autoridad correcta |
| work_items | Work + handlers | REUSE; terminal purge30d implementado; payload acotado por productores |
| meli_accounts/meli_tokens | OAuth/handlers/tenant FK | REUSE; cifrado; tokens jamás importados de ERP1 |
| meli_cooldowns/api_usage_daily | MeliClient safety/usage | REUSE; aggregate retention90d; cooldown no historial de payload |
| webhook_events | Dedupe webhook | Necesario activo; falta política pruning; borrar pierde dedupe de eventos viejos, evaluar ventana real antes DELETE |
| orders/order_items | Sales y bridge/Financial | Canónicos necesarios; no purge improvisado |
| sales_audit_runs/sales_audit_orders | Evidencia A/B/baseline/repair | No dead table; historia sin TTL, baseline y active runs no borrables sin contrato |
| billing_periods/billing_details | Cursor/facts/Financial | Necesarios; hint Work from_id duplicación operativa demostrada, no autoridad alternativa |
| products/product_inventory | Catalog/Inventory/API | Necesarios, no owner account duplicado |
| invoicing_service_keys/invoicing_artifacts | Bridge/PostSale | Hash/revocation/document proof; retención legal no eliminar por TTL genérico |
| schema_migrations | Migrator checksums | Necesaria auditabilidad/recovery |

Columnas de source-time y created/updated tienen funciones distintas. `billing_periods.partial_flag` está siempre0 en writes actuales; handler206 no persiste flag1. Candidato a simplificar tras revisar readers y readiness, no columna muerta demostrada por eso solo. `finished_at` usado purge; `active_dedupe_key` generado sirve active uniqueness; `capture_pass` distingue A/B, no duplicación accidental.

No SQL/table/trigger temporal se añadió al schema. Test rollback trigger se instala/retira sólo fixture. Guías/checkpoint históricos y README tienen drift (README todavía dice F13 parcial/no importer pese a cierre); corregir referencias en un bloque documental, no mantener caminos alternos. AppLogger NO dead code: DebugRecorder usa warning/error en fallo.

## Faltantes locales ordenados

| ID / rank | Comportamiento concreto faltante | Owner y archivos mínimos esperados | Test esperado | Necesidad | KISS action |
|---|---|---|---|---|---|
| LC-P0-1 CLOSED LC1 | Sales Audit acepta206/200+X-Content-Missing como terminal; Billing acepta200+missing como vacío terminal | SalesAuditHandler::normalizePage/processCurrentClaim; BillingPeriodSyncHandler guard; MeliClientResponse actual | Fake206 y200+header en captura A/B y Billing, 0 evidence/facts/cursor/VALID/caught_up; same claim y no HTTP extra | No certificar coverage incompleta como completa. Private probes confirman aceptación actual | REUSE/EXTEND guard actual, no estado nuevo |
| LC-P0-2 CLOSED LC1 | Reparación enqueueNextMissingOrder se ejecuta antes del guard claim; token obsoleto puede producir order.sync | SalesAuditRepairHandler, WorkRepository::completeCurrentClaim existente | Stale repair token →0 hijos/estado; current token→un hijo+diferir atómico; SQL failure rollback | Evitar side effects de executor sin authority actual | MERGE enqueue y transición bajo guard/transaction existente |
| LC-P0-3 CLOSED LC1 | Usuario disabled con sesión previa sigue admitido por CompanyContext/Sales/settings/OAuth membership queries | AuthService/CompanyContext y guards callers actuales; sin RBAC nuevo | Deshabilitar usuario tras login →403 en Sales/API/settings/OAuth, 0 writes/enqueue; active user PASS | Revocación real de acceso, no logout voluntario como seguridad | REUSE/MERGE autoridad actual con estado users |
| LC-P1-1 | Operador sin entrada/menu/selector tenant, select redirige404; ventas>100 y audit outcomes inaccesibles | Routes, SalesListController y vistas existentes | Browser-contract login→select→sales, foreign403; consultar order anterior100; audit status correcto | Uso diario sin URL/SQL manual | REUSE/EXTEND UI existente |
| LC-P1-2 | Catalog/Inventory no operables desde navegador normal; Financial y PostSale sólo clase/API de máquina | ErpApiController/API existentes, Sales/bridge readers, Routes/views | POST producto/PUT stock CSRF+tenant, render null/KNOWN/UNKNOWN/CONFLICT y review status; 0 HTTP | Capacidades de negocio existentes deben ser utilizables | REUSE/EXTEND consumers; Financial sin cambio |
| LC-P1-3 | Ningún productor inicial Billing ni opción pública manualOne CLI | BillingPeriodSyncHandler, WorkExecution::runManualOne, bin/work.php/Routes según actor | Enqueue explícito periodo/cuenta/tipo, foreign reject, dedupe, exactamente1 claim/una página y automationOFF | Evitar SQL ad hoc para usar Billing probado | REUSE/EXTEND existente, no scheduler |
| LC-P1-4 | Primer admin/empresa no provisionable por herramienta/guía reproducible; .env ejemplo no se carga | README/setup local y CLI mínimo si guía actual no basta; Auth/PasswordService/Migrator actuales | DBvacía→migrations→primer admin/empresa→login sin SQL del operador; repeat idempotente; env validado sin OAuth requerido | Instalación local ejecutable por persona distinta al autor | SIMPLIFY/REUSE; no nuevo stack/dependencias |
| LC-P2-1 | Settings invalid retention/max devuelve500; configuración incompleta tarda en fallar | SystemSettingsController/AppConfig/preflight | Valores inválidos422; config requerida según operación, no bloquear core sin OAuth | Error de operación recuperable y diagnóstico | REUSE validadores |
| LC-P2-2 | Continuaciones Sales Audit producen rows terminales por página; evidencia/webhooks/logs sin política completa | SalesAuditHandler, cleanup y owners existentes | Demand-bound continuation, purge preserva A/B/baseline/dedupe/window | Reducir estado si no pierde prueba/recovery | SIMPLIFY/DELETE; no tocar en LC0 |
| LC-P2-3 | DTO InvoiceRequest sin caller productivo; drift README/F13/pack | InvoiceRequest vs bridge DTO actual; README | Referencias/contratos/idempotency iguales antes DELETE, documentación no contradice código | Menos piezas que entender, sin borrar contrato necesario | DELETE/MERGE candidato; SIMPLIFY docs |
| LC-P2-4 | Permisos globales settings no separados de company admin; usuarios sin tests HTTP login/logout/select completos | SystemSettingsController + authority actual | Dos empresas, adminB no controla/lee DVR globalA según owner explícito; csrf invalid, member forbidden | Definir actor operador global antes rollout multiempresa sin framework | REUSE/SIMPLIFY; no nuevo RBAC |

P0 identifica blockers de corrección/seguridad de la auditoría; no incidente productivo observado. Ninguno se corrigió aquí. La reproducción de validators, stale claim y sesión se registra como evidencia; la futura implementación debe empezar por tests de regresión RED. `local-state-probe.json` confirma: disabled user + membership + sesión → CompanyContext admitido y Sales200; token de claim inválido → processCurrentClaim=false pero child order.sync0→1. Sólo DB desechable con fixtures, sin ninguna llamada HTTP.

## Windows Companion y diferidos

Companion no necesario para readiness del core ERP web: todos los contratos de export/recepción se prueban sin él. Sí faltaría para **producto de automatización fiscal completo**: consumidor Windows local que lea API existente con key scoped, maneje readiness/fail-closed, capture PDF/XML y remita artefacto idempotente. Primero fake/local fixture, sin navegador/provider real ni publicación Mercado Libre. Clasificado separately; no empezado ni requisito automático para LC1.

EXTERNAL: dedicación app/grant exact seller, GET reales pack/billing-info/Billing, comportamiento parcial/provider/quota, legalidad fiscal. PRODUCTION: binding HTTPS/docroot, backups, DB/runtime/secrets, migrations recovery persistida, mappings/cutover, manual canary y observación, cron sólo autorización posterior. No convertir ninguno en P0/P1_LOCAL.

## Gate LOCAL_PRODUCT_READY

| Condición | Resultado |
|---|---|
| Flujos canónicos delimitados con prueba determinista | PASS de contratos actuales; partial-read audit/Billing falla |
| Tenant/company | PASS boundaries probados; disabled-session revocation FAIL |
| Exact money/null | PASS |
| Work no crece sin control | Core PASS, repair stale side effect FAIL; continuación audit lineal documentada |
| Financial local-only | PASS |
| Billing same-Work | PASS |
| Remote writes OFF | PASS defaults/registry/tests |
| Ningún MUST_HAVE operador bloquea uso normal | FAIL |
| Fresh QA verde | PASS — 453 tests / 3171 assertions / 1 skipped / 0 failures / 0 errors; no sustituye faltantes anteriores |

STOP — auditoría solamente; F15 pausado, ningún siguiente bloque implementado.

## Recibo QA final

- PHP 8.5.8, MariaDB local contenedor `erp-meli2-clean3-db`, DB `erp_meli2_test` desechable. Container detenido al cerrar.
- Primera corrida: 453 tests / 3165 assertions / 2 errors de conexión inicial en AuthTenancyTest / 1 skipped. Conservada como `qa-phpunit-cold-start.txt` y `junit-cold-start.xml`; no corrección funcional.
- Segunda corrida tras readiness SELECT1: 453 tests / 3171 assertions / 1 skipped / 0 failures / 0 errors, exit0, 105 clases. 2m01s.
- Skip: DebugMaintenanceTest::testMaintenanceNeverFollowsSymlinkOutsideManagedRoots, condición de plataforma Windows; no se afirma ejecución de ese caso.
- Composer validate --strict PASS, PHPStan PASS, sintaxis PASS163 archivos app/public/bin/tests, git diff --check PASS.
- 21 declaraciones de rutas; map GET/POST settings representa dos métodos. Inventario `routes.json`; coverage exhaustiva de casos por clase `coverage-by-class.json`, receipts/probes junto al JUnit.
- QA normal mantuvo APP_ENV=test / REAL_MELI_HTTP=0. SSH/Hostinger/ML real/OAuth real/Alegra/DIAN/deploy/remote writes=0. No functional source/schema/test change.

## Cobertura conductual ejecutada por dominio

Asignación exclusiva por clase; total453/3171. Las listas de tests de la matriz señalan contratos concretos; JUnit y JSON conservan las105 clases y cada caso.

| Dominio | Tests | Assertions | Skipped |
|---|---:|---:|---:|
| Foundation/settings/bootstrap/retention | 18 | 70 | 0 |
| Billing | 74 | 701 | 0 |
| Catalog | 14 | 33 | 0 |
| Debug/logging | 21 | 181 | 1 |
| ERP API | 11 | 51 | 0 |
| Financial | 5 | 126 | 0 |
| Inventory | 6 | 11 | 0 |
| Invoicing/PostSale | 26 | 172 | 0 |
| MeliClient/transport/READ | 87 | 365 | 0 |
| OAuth/cipher | 25 | 154 | 0 |
| Sales | 72 | 525 | 0 |
| Sales Audit | 68 | 581 | 0 |
| Work | 26 | 201 | 0 |

F13 está cubierto dentro de Catalog; F14 dentro de Meli/READ; S1 dentro de Billing/Work; F10–12 dentro de Invoicing/PostSale. F0/1 y admin/auth/config dentro de Foundation. No se infiere una cadena E2E por sumar casos de clases diferentes.
