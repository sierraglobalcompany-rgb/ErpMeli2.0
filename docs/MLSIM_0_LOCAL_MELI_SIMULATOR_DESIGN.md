# MLSIM-0 — Diseño mínimo del simulador local Mercado Libre

Fecha: 2026-10-12. Checkout auditado: `7e09bd80e171763eadd4e08e316794d6c3e24706`, rama `impl/v3-b-sales-audit-20261010`, CLEAN. Functional baseline `1ee9da64ea6d23b0f97424d72f1398dd37a8a416`: 475 tests / 3372 assertions / 1 skipped / 0 failures/errors. No se ejecutó nuevamente QA funcional por un diseño exclusivamente documental.

## MLSIM-2 — cobertura vigente, 2026-10-13

Baseline `54ea1ba1161897c702f1fbda0a4048722987246d`, rama `impl/v3-b-sales-audit-20261010`, CLEAN. Functional SHA `84b8d6b5750be9ba187a386d5107023609601d9b`. Decisión del usuario LOCAL-FIRST; F14 LIVE=DEFERRED_BY_USER_LOCAL_FIRST. LOCAL_PRODUCT_READY=YES, LOCAL_PROVIDER_SIMULATION=COMPLETE_CURRENT_SCOPE, NEXT=LOCAL_STABILITY_REVIEW, F15=PAUSED, F16=UNAUTHORIZED y remote writes OFF.

La auditoría confirmó un gap único del callback exitoso: fakes componían servicios; web tests probaban start/guards. OAUTH_HTTP_SIM_NEEDED=YES en esta fase. Sólo Routes extiende MELI_TEST_BASE_URL, APP_ENV=test, HTTP literal127.0.0.1/puerto válido; fuera de test se ignora. Bootstrap/Routes se ejecutan in-process con PSR-7; Curl→MLSIM cruza HTTP real local. Estado/PKCE generado y código sintético bastan: no servidor `/authorization`.

| Operación actual | Estado local | Secuencia representativa / límite |
|---|---|---|
| orders.get | CERTIFIED_LOCAL_HTTP | SyncOrderHandler + CLI; money/IDs exactos, partial/invalid JSON |
| orders.search | CERTIFIED_LOCAL_HTTP | Audit capture/repair/confirm + CLI; volumen/401 siguen en fakes |
| billing.period.details | CERTIFIED_LOCAL_HTTP | Tres GET paginados, mismo Work; partial y429/cooldown |
| packs.get | CERTIFIED_LOCAL_HTTP | Pack + dos órdenes independientes:3GET; partial206:1GET/rechazo; sin fetch consumer/crawler productivo |
| orders.billing_info | CERTIFIED_LOCAL_HTTP | 1GET site/id por caso; COMPLETE/MISSING/UNKNOWN/UNAVAILABLE; sin tax/shipping fallback ni fetch consumer/perfil fiscal |
| oauth.token | CERTIFIED_LOCAL_HTTP | Callback POST form/PKCE; refresh1POST; locks/401/invalid_grant/ambigüedad CERTIFIED_FAKE_ONLY_WITH_JUSTIFICATION |
| users.me | CERTIFIED_LOCAL_HTTP | Callback GET tras token; tenant/cuenta/cifrado/expiry/Sales303; replay0calls |

Diez scripts nuevos sobre ocho originales intactos: tres packs, cinco billing-info y OAUTH_CONNECT/OAUTH_REFRESH. Router finito sin ERP bootstrap/DB/transport/proxy; cuerpo literal, query READ allowlist, unexpected/excess fail500. Form exacto y verifier S256 validados sin log de valores; sólo desafío hash temporal propio, limpiado por helper existente. No tokens/form/Authorization/PII en trace. Financial HTTP0/Work0. Para billing-info404 MeliClient lanza excepción: el test verifica ese HTTP y explícitamente UNAVAILABLE del normalizador, sin atribuirlo a un consumer inexistente.

Budget:1 archivo productivo +3 archivos de test existentes;0 nuevas clases/services/repos/tablas/migraciones/Work types/deps/queues/runners. RED real antes del GREEN; guards correctos no tuvieron RED fabricado. QA: MLSIM31/594; vecinos101/723; full648/5519/1skip/0failures/0errors/0deprecations. Composer strict/PHPStan/syntax178/diff PASS;182 input hashes coinciden. MariaDB detenida; procesos/temp propios0. Evidence/patch: storage/exports/mlsim-2/.

Esta certificación sólo prueba contratos locales actuales. No ML/OAuth/app reales, DB productiva, hosting, deploy, F14 LIVE/F15/F16. Los apartados siguientes conservan diseño/recibo históricos MLSIM-0/1; sus decisiones, QA y límites corresponden a esos SHA, no al alcance vigente. El checkpoint determina el estado actual.

## MLSIM-1 V2 — recibo histórico de cierre

Autoridad de implementación: `ERP_MELI_2_0_MLSIM_1_IMPLEMENTATION_PLAN_V2_OPTIMIZADO_2026-10-12.md`, solicitud explícita del usuario. Sustituye el plan MLSIM-0 de cinco tareas y el sample51 por cuatro tareas / AUDIT_REPAIR_1. HEAD before268de288d6d5ea05a8e2bc85cd2efecfd0fbf197, clean. Functional SHA `3e45c756b35093f19d69cc428bcc7849e9146e43`.

Implementado: tests/Support/MeliSim/router.php, scenarios.json y tests/Integration/MeliHttpSimulationTest.php. Tres archivos nuevos, una clase PHPUnit; producción files/classes/dependencies/tables/migrations/Work types/queues/workers/schedulers/services=0. Router no carga ERP, DB, credenciales reales ni transport saliente. APP_ENV=test, bind127.0.0.1, trace absoluto bajo temp propio, fixed allowlist8 escenarios/3READ operations. No proxy/fallback/static file serving. Host no literal rechazado antes de conexión.

RED observado: Task1 cuatro fallos de ausencia router; Task2 tres casos incompletos (cinco mensajes contando teardown); Task3 cinco casos de scripts ausentes (diez mensajes contando teardown). El primer arranque Windows requirió SystemRoot/WINDIR existentes, preservados en el env mínimo del child sin heredar DB/ML secrets. Task4 zero invariants son regresiones LC1/Financial ya correctas: pasaron directamente; no se fabricó RED ni se modificó owner para provocar fallo. Logs conservados localmente.

| Caso | GET loopback exactos | Adicionales |
|---|---:|---|
| Order happy/exact | 1 | 0 |
| Order partial header | 1 | 0 |
| Order invalid JSON | 1 | 0 |
| Audit repair N1 | 3 | Secuencia search/order/search; VALID |
| Audit partial206 | 1 | 0 authority/child/extra |
| Billing2 páginas + terminal | 3 | mismo Work; from_id0/101/102; exact money/COP |
| Billing partial header | 1 | 0 facts/cursor/caught_up/extra |
| Billing429 | 1 | Retry-After60 a cooldown; retry inline0, followup0 |
| Disabled session | 0 |403;0 Work |
| Stale repair claim | 0 |0 child/parent/audit mutation |
| Financial read | 0 |KNOWN exact local fee + UNKNOWN billing,0 Work |

Dos GET adicionales y un POST local pertenecen exclusivamente a pruebas negativas unexpected/excess del harness; POST/oauth/token se rechaza como UNKNOWN, no es OAuth simulator. No calls ML reales. Counter JSONL ordenado registra antes de response; unexpected/excess hacen fallar caso normal. Header/body/Authorization values no se vuelcan al trace, sólo presence. Body literal cruza cURL preservando90071992547409.1234/IDs>2^53/null.

QA: MLSIM17 tests/288 assertions; neighbors189/1927; full492/3660/1skip/0failures/0errors, exit0. PHPStan/strict Composer PASS, syntax165 PASS, diff check PASS. **Una deprecación existente** expuesta por Curl real en PHP8.5: app/Integrations/MercadoLibre/Transport/CurlMeliTransport.php llama curl_close(), sin efecto desde8.0 y deprecated8.5. No se suprimió ni corrigió código productivo; PHPUnit exit0 y cero errores/fallos, pero reporta esa advertencia. Skip existente de symlink Windows. No limpieza/reenqueue business con sleep/polling: sólo available_at en DB test; espera corta exclusiva del startup TCP. Procesos propios terminados, dirs propios borrados, DB local detenida.

MLSIM does not certify Mercado Libre. MLSIM-1 does not certify production bin/work.php wiring. MLSIM-1 does not certify real browser OAuth/callback. packs.get and billing-info remain contract/fake-only. Sales Audit large/multi-page behavior remains fake-tested; HTTP volumetric verification is deferred unless LC3/LC4 shows value. No LOCAL_PRODUCT_READY claim: P1 sigue pendiente. No LC2/F14live/F15/F16/deploy/push/merge.

## 1. Decisión y puerta KISS

**MLSIM_NEEDED=YES; HTTP_LAYER_NEEDED=YES; OAUTH_HTTP_SIM_NEEDED=NO.**

Se necesitan tres recursos HTTP locales para demostrar cURL → status/header/body → MeliClient → handlers reales. Los 38 fake transports existentes siguen siendo la herramienta principal para lógica/errores/rotación OAuth. No se añade un proveedor alternativo configurable en producción, framework de mocks, servidor OAuth, DB del simulador ni lógica Financial.

¿Es correcto/simple/eficiente y adecuado al problema? Sí: constructor e interfaces actuales permiten la composición de test; tres archivos nuevos y una clase PHPUnit bastan. ¿Podemos resolver con menos piezas? DELETE authorize/control endpoints/pack/billing-info/http-timeout/framework; REUSE MeliClient/CurlMeliTransport/Work/TestDatabase; SIMPLIFY estado a un trace temporal y escenarios finitos. No exportar todos los fakes a una jerarquía común sólo para MLSIM.

Este diseño **no declara LOCAL_PRODUCT_READY**: P1 de LC0 aún impide la operación cotidiana. MLSIM-1 cubre contratos E2E locales actuales; LC2 sigue pendiente para UI/operador, y una futura LC3 podrá demostrar la experiencia completa. El simulador no inventa pantallas o productores que hoy faltan.

## 2. Seams actuales — inventario

| FILE | OWNER | PURPOSE | WHAT IT ALREADY PROVES | WHAT AN HTTP SIMULATOR WOULD ADD | DUPLICATION RISK |
|---|---|---|---|---|---|
| app/Integrations/MercadoLibre/Transport/MeliTransport.php | Cliente/transport | send(method,url,headers,body) | Contrato de inyección común | Implementación Curl existente cruza un socket real local | No nueva interfaz/adapter |
| app/Integrations/MercadoLibre/Transport/CurlMeliTransport.php | CurlMeliTransport | cURL, headers lower-case, timeout5/30s, TLS, no redirects | Fakes no ejercitan cURL ni captura de headers por callback | Status206/429, header mixto y cuerpo literal cruzan socket | No nuevo transport; no copiar opciones cURL |
| app/Integrations/MercadoLibre/Transport/RemoteHostPolicy.php | Guard local/test | Bloquea api.mercadolibre.com en local/test | Unit RemoteHostPolicyTest cubre rechazo | Se admite loopback actual | No afirmar firewall de todo Internet: otros hosts son admitidos; harness controla destino exacto |
| app/Integrations/MercadoLibre/Client/MeliClient.php | MeliClient | Constructor recibe transport y baseUrl; registro, decoder, pacing, cooldown | 1 send/request, WRITE guard, rate y errores | Wiring Curl y bytes/headers reales | No baseUrl nueva en AppConfig |
| config/meli_operations.php | Registro único | Siete operaciones | Métodos/templates/family/classification y parámetros | HTTP usa sólo tres READ con consumidores | No agregar operaciones al registro para MLSIM |
| app/Integrations/MercadoLibre/Client/MeliClientResponse.php | READ helpers | Completitud/seller/pack/fiscal envelope | Unit MeliReadContractTest y LC1 | Un header HTTP real llega al guard actual | Pack/billing-info siguen CONTRACT_ONLY; no resolver imaginario |
| app/Integrations/MercadoLibre/Auth/OAuthAuthorizationFlow.php | Flow | State/TTL/PKCE por sesión; authorizationUrl ya inyectada | Unit + OAuthHttpStartRouteTest/callback guards | UI autorización local aportaría poco al core | Mantener FAKE_ONLY, nunca seguir redirect real |
| app/Integrations/MercadoLibre/Auth/OAuthConnectService.php | Connect | AUTH POST form + users.me GET + cifrado/persistencia | OAuthConnectServiceTest, no retry de código ambiguo | No requerido por E2E Sales/Billing con fixture conectada | No simular OAuth UI ni issuer |
| app/Integrations/MercadoLibre/Auth/OAuthRefreshService.php | Refresh | Lock/reread, one-use POST, 401/invalid_grant/attention | Refresh/StatusGuard/Unauthorized tests | Socket OAuth innecesario para rotación lógica | Mantener FAKE_ONLY; no copiar tokens ERP1 |
| app/Core/Http/Routes.php | Wiring web | Authorization URL y callback MeliClient base fijas | Start/guard/callback state y routes locales | No seam de OAuth válido sobre HTTP sin cambiar composition root | No afirmar callback success HTTP totalmente simulado |
| bin/work.php | Wiring CLI | MeliClient base fija, runAutomatic25/45s | WorkCliEntrypointTest y handlers/WorkExecution | No acepta URL MLSIM hoy | MLSIM-1 usa composición de test actual; no ejecutar CLI automático en escenarios |
| app/Modules/Billing/C0/BillingC0Probe.php | C0 | Closure fetchPage / forMeliClient; loop explícito maxPages | BillingC0ProbeTest, BillingC0MeliClientProbeTest | Probe puro puede recibir cliente loopback | No duplicar handler ni convertirlo en productor |
| app/Modules/Billing/C0/BillingC0Runtime.php; bin/billing-c0-smoke.php | C0 runtime/operador | Runtime crea cliente base fija; CLI production + real-opt-ins, maxPages1..20 | Runtime/CLI guards | No valor de forzar CLI production para MLSIM | Mantener CLI intacto y no invocarlo; probe directo sólo si prueba wiring nuevo |
| storage/exports/lc0/{partial-probe,local-state-probe}.php | Evidencia histórica local | Probes temporales de validators/claim/session | Reprodujeron P0 ahora cerrados por tests LC1 permanentes | Ningún valor de trasladarlos a HTTP | No convertirlos en framework ni depender de storage exports para tests |
| tests/Integration/SalesVerticalSliceEndToEndTest.php | Test/ERP | Bootstrap webhook → Work → fake order → Sales UI | Tenant/event-dedupe/persistencia de punta a punta in-process | Sustituir sólo send por Curl loopback en nuevo caso | No daemon que empuje webhooks |
| tests/Integration/OrderWebhookReceiverTest.php; OrderWebhookHttpRouteTest.php | Webhook fixture | Evento sintético con seller/application/resource | Validación, ambigüedad cuenta, body guard, dedupe | No endpoint webhook del simulador | POST fixture al ERP, no al MLSIM |
| tests/Support/TestDatabase.php; tests/bootstrap.php; phpunit.xml | QA | Reset destructivo DB desechable, autoload, APP_ENV=test/REAL_MELI_HTTP=0 | Migrations, fixtures y aislamiento de prueba serial | Se reutilizan sin DB nueva | No parallel reset de la misma DB; verificar target local antes reset |
| tests/Integration/WorkCliEntrypointTest.php; BillingC0CliRuntimeTest.php; MeliRateLimitTest.php | Harness previo | proc_open con argumentos array y PHP_BINARY; terminate/finally | Procesos PHP portables y cleanup | Reusar patrón para php -S, sin clase servidor genérica | No shell Unix, taskkill por nombre ni daemon |
| bin/{migrate,cleanup,catalog-rehearsal,invoicing-key,runtime-preflight}.php | CLI existentes | Setup/retención/import desechable/credenciales máquina/preflight | Tests/guards correspondientes | Ningún recurso sim requerido | No reimplementar esos CLI en MLSIM |

Inventario completo de los 38 fakes por archivo/owner al final. No se los elimina: cada uno conserva el escenario focal del contrato que ya prueba. La reutilización de comportamiento no exige importar clases auxiliares desde otros archivos Test, cuyo autoload puede depender del descubrimiento de PHPUnit.

## 3. Operaciones exactas y consumidores

Todas provienen del registro actual, sin consultar proveedor externo.

| Key / method / path | Family | Caller productivo actual / test-only | Parser/normalizer | OAuth / 401 | 429 | Pagination owner | Decisión |
|---|---|---|---|---|---|---|---|
| oauth.token POST /oauth/token | oauth AUTH | OAuthConnectService, OAuthRefreshService / OAuthQueueTransport, RefreshQueueTransport | JSON normal bigint-string; valida token/expires_in; TokenCipher/persist | Setup form PKCE o refresh; POST one-shot, sin retry interno | Cooldown app:oauth, excepción al caller | Ninguna | KEEP_FAKE_ONLY |
| users.me GET /users/me | users READ | OAuthConnectService / client/identity/rate tests | JSON normal, id/site/nickname requeridos | Token inicial; ningún refresh de connect | Excepción/cooldown app:users | Ninguna | KEEP_FAKE_ONLY |
| orders.get GET /orders/{order_id} | orders READ | SyncOrderHandler / OrderGet, precision, E2E | LosslessJsonDecoder, normalizeOrder, orderReadState con seller/completitud | getValidAccessToken; 401 recovery acotado y segundo GET | defer same Work al retryAt, 0 inline | Ninguna | IMPLEMENT_HTTP_SIM |
| orders.search GET /orders/search | orders READ | SalesAuditHandler capturas A/B / search/audit tests | JSON_BIGINT_AS_STRING; normalizePage paging/id/date con timezone; guard LC1 | Igual recovery acotado | defer, 0 inline | SalesAuditHandler próxima página Work, repair secuencial | IMPLEMENT_HTTP_SIM |
| packs.get GET /packs/{pack_id} | orders READ | Ningún caller negocio / MeliOrderGetContractTest + MeliReadContractTest | Lossless; packMembership puro requiere responses ya resueltas | Caller manual; no recovery propio | MeliClient únicamente | Ningún resolver implementado | CONTRACT_ONLY / KEEP_FAKE_ONLY |
| orders.billing_info GET /orders/billing-info/{site_id}/{billing_info_id} | orders READ | Ningún caller negocio / OrderGet path + READ helper unit | Lossless; billingInfoReadiness estructural MCO | Caller manual; no recovery propio | MeliClient únicamente | Ninguna | CONTRACT_ONLY / KEEP_FAKE_ONLY |
| billing.period.details GET /billing/integration/periods/key/{period_key}/group/ML/details | billing READ | BillingPeriodSyncHandler, C0Probe/Runtime / Billing fake tests | Lossless + preparePage/BillingDetailNormalizer; strings exactos | Token preflight, 401 recovery + mismo recurso una vez | defer same Work, 0 inline | Handler mismo Work/cursor; C0 loop acotado explícito | IMPLEMENT_HTTP_SIM |

Registry no tiene WRITE negocio, Shipment ni fiscal_documents. No simular items/publicaciones/stock/precios/messages/claims/payments/Mercado Pago. Inventory/Catalog locales no son API ML. No promover endpoints CONTRACT_ONLY por roadmap.

## 4. OAuth separado: opción elegida

Actual: `/oauth/mercadolibre/connect` valida active user/admin/empresa, begin guarda state/verifier/issued_at y company binding; devuelve redirect a `https://auth.mercadolibre.com.co/authorization`. Callback recibe code/state, valida active user/admin del bound company, consume state/PKCE, POST form authorization_code one-shot, GET users.me, cifra/persiste. Refresh tiene lock por account, reread tras lock, POST one-shot, rotate/version/expires; invalid_grant→reauth_required; 5xx/resultado ambiguo→attention; 429→cooldown; 401 recurso permite recovery acotado. Estos contratos ya tienen tests determinísticos.

| Opción | Valor | Complejidad/duplicación | LOCAL_PRODUCT_READY | Decisión |
|---|---|---|---|---|
| A fakes/flow + fixture sintética conectada para E2E | Prueba PKCE/session/código form/identity/rotación sin depender del proveedor | Ningún endpoint/seam nuevo; mantener tests actuales | Suficiente para el contrato OAuth local; operador prueba módulos sobre fixture conectada | CHOOSE |
| B authorize+token+users local para browser OAuth completo | Probaría success callback y cookies por socket | ≥3 endpoints adicionales; cambiar authorize/API base wiring Routes; simular issuer y repetir fakes | No requerido para core local; no sustituye grant real | DELETE del alcance MLSIM-1 |

No authorize endpoint ni callback helper nuevo. No seguir redirects de connect. Para muestras locales: account + tokens **sintéticos** cifrados por TokenCipher en DB ERP de prueba, expiry fija suficientemente futura y now fixture explícito; no account ficticiamente productiva. Mantener resultados de OAuth fake aparte. Browser full OAuth sigue gate externo; success callback wiring no queda cubierto por MLSIM-1.

## 5. Endpoints HTTP mínimos

Únicamente tres rutas GET arriba; localhost literal `127.0.0.1`, puerto del harness. No token endpoint. Operación/params coinciden con registry vigente.

| Recurso | Consumidor E2E | Validación nueva de HTTP | Fuera de alcance |
|---|---|---|---|
| order ID sintético conocido | Work order.sync por webhook/repair | cURL/header/JSON literal → exact money + canonical + seller authority | búsqueda/descubrimiento live, pack resolution |
| orders.search | SalesAudit A/B | query seller/window/sort/offset/limit y response206 vía socket | Generar una historia arbitraria o reglas provider desconocidas |
| billing period details ML | Billing handler | from_id/document_type/limit1000/sort; header missing y cursor literal | Otra paginación, C0 auto runner o provider quota |

UNKNOWN método/path/query siempre se registra y hace fallar el test. Router no sirve archivos estáticos ni hace proxy/fallback HTTP. No control/reset/stats/health HTTP endpoints; control por env de child process y trace local.

## 6. Scenarios — IDs y matriz mínima

Seleccionar una secuencia finita por escenario al arrancar. Respuestas se toman de **body literal string**, status/headers definidos, y expected method/path/query. No motor de generación de orders, cursor, cuotas ni tenant state. Fixtures mínimas sintéticas y sin PII real. Tabla indica dónde se ejecuta cada categoría; no multiplicar todo por cada endpoint.

| ID | Capa / ruta | Secuencia / aserción |
|---|---|---|
| ORDER_HAPPY_EXACT | HTTP order | 1 GET200, ID grande como lexema, total/unit_price exactos, sale_fee nullable; persist/update/idempotency por replay test separado |
| ORDER_PARTIAL_HEADER | HTTP order | 1 GET200 con X-Content-Missing mixto; no canonical write ni extra call |
| AUDIT_REPAIR_1 | HTTP search/order | N=1 L=50 M=1: A0,order_missing,B0; exactamente3 GET, VALID sólo tras B; cuerpo explícito por página |
| AUDIT_PARTIAL_206 | HTTP search | Un206 terminal aparente; LC1 contrato failed, evidencia/run intactos, ningún child |
| BILLING_TWO_PAGES | HTTP details | from0→101→102→empty: tres claims/GET, misma row; BILL/CREDIT_NOTE como variantes explícitas del mismo recurso, facts/amount/currency strings |
| BILLING_PARTIAL_HEADER | HTTP details | Un200 + X-Content-Missing: results; same Work defer, facts/cursor/caught_up intactos |
| FIRST_CALL_429 | HTTP details | Primera GET429 con Retry-After; defer, segunda invocación client bajo cooldown→0 dispatch; no esperar deadline ni reintentar proveedor |
| INVALID_JSON | HTTP order | Un200 con body literal malformado → safe exception/retry durable, no facts; no segunda claim en test |
| ORDER_401_RECOVERY / AUDIT_401_RECOVERY / BILLING_401_RECOVERY | FAKE_ONLY existing | Recurso401→AUTH POST una vez→recursoGET segunda vez; segunda401 termina. No endpoint OAuth HTTP añadido |
| RESOURCE_403_404_500_503 | FAKE_ONLY existing | Rechazo permanente404 exact order/403; retry cap5 para5xx; Retry-After existing. Status wiring ya observado206/429; no ganar valor repitiendo todos por HTTP |
| TRANSPORT_AMBIGUOUS / TIMEOUT | FAKE_ONLY existing | Excepción de send; refresh attention sin segundoPOST; recurso retry bounded. No sleep30s ni cambio timeout cURL |
| SELLER_MISMATCH / CRITICAL_MISSING / NULL_ZERO / BAD_CURSOR | FAKE_ONLY existing | order seller, UNKNOWN≠0, dates/shape, non-progress Billing; no permutations HTTP repetidas |
| AUTH_CODE / REFRESH / INVALID_GRANT / AUTH_429_5XX | FAKE_ONLY existing | Flow/Connect/Refresh fakes prueban todas las decisiones locales; no issuer ML fake completo |
| PACK_MEMBERS / BILLING_INFO_MCO | FAKE_ONLY CONTRACT_ONLY | Helpers actuales; no HTTP sim mientras no haya consumer E2E |
| DISABLED_SESSION / STALE_REPAIR / FINANCIAL_READ | Local ERP + contador sin requests | Denial/guard/read model deben dejar delta HTTP0; no endpoint nuevo |

Happy Billing de una página se obtiene ejecutando sólo el primer claim de TWO_PAGES, no escenario duplicado; terminal vacío es el último step. Si se necesita stream de una página no vacía + terminal, se omite segundo step no vacío en fixture pequeña, sin parser nuevo. BILL/CREDIT_NOTE se parametriza expected query, no documento fiscal del cliente. No thresholds reales de cuota.

Cantidad inicial: ocho scripts HTTP y variantes BILL/CREDIT_NOTE, no más de tres operaciones. Cap explícito≤8 llegadas por caso inicial (audit3, Billing3, demás1); fixture contiene expected_call_count exacto. Más llamadas no son una respuesta alternativa aceptable.

## 7. Contador de requests — contrato

Un archivo temporal `requests.jsonl` por proceso/run válido de test. Un router con APP_ENV inválido rechaza antes de fixture/trace; el probe negativo de ese guard no se atribuye como llamada de un run válido. Append bajo flock antes de responder; cada HTTP recibido produce **exactamente una línea**, incluidos unknown methods/paths/queries y llamadas más allá del último step. Router PHP reejecuta por request: variables PHP globales NO son contador persistente. El JSONL acotado a la vida del test es el único estado mutable; no DB ni ledger empresarial.

Registro mínimo: `seq` entero consecutivo, `method`, `operation` (o UNKNOWN), `path` canónico permitido (UNKNOWN + hash para un path no admitido, sin imprimir su texto arbitrario), query allowlist sintética normalizada, `authorization_present` boolean, `scenario`, `step`, `status`, `matched` boolean. Sin timestamp de negocio, Authorization/token/cookie, body de request ni payload de comprador. Análisis/harness lee JSONL para total, by_method, by_operation, by_path y trace ordenada. No métricas productivas.

- Total = cantidad de líneas; primera seq1; sum(by_method)=sum(by_operation)=total; por path se conserva ID sintético. Orden no depende de wall-clock.
- READ sólo GET, permitido exactamente por expected step. POST/PUT/PATCH/DELETE recibidos también cuentan y matched=false. No archivo estático ni respuesta satisfactoria ante unexpected.
- Respuesta escrita después del append. Crash entre llegada y respuesta conserva intento HTTP recibido; fallo de append es fallo del harness, nunca un supuesto0 llamadas.
- Counter cuenta **requests recibidos por HTTP**, no conexiones TCP de readiness ni intentos de socket fallidos antes de recepción. Transport-ambiguity/timeout se cuentan por send en los fakes existentes. No afirmar que ingress equivale a todo outbound Internet.
- OAuth FAKE_ONLY tiene su propia lista requests actual. Reportar `http_sim.GET`, `fake_AUTH.POST`, `fake_users.GET` por separado; si se suman, declarar `total_MeliClient_dispatch = http_sim + fake_dispatch` con origen. No inventar orden global de trazas de fuentes distintas ni describir POST fake como HTTP socket.

Asserts de aceptación: happy order GET1; webhook repetido antes processing1 Work/GET1; partial owner GET1 y extra0;429 primera1 y follow-up bajo cooldown delta0; disabled y stale repair delta0; Financial delta0 aun manteniendo server en ejecución. Baseline snapshots antes/después de cada acción para evitar atribuir setup al negocio.

## 8. Reset/control/fixtures

Env child `APP_ENV=test`, `MLSIM_SCENARIO=<ID allowlisted>`, `MLSIM_TRACE=<absolute temp file>`. Root fixtures dentro de tests, conocido por router; no ruta remota/arbitraria de fixture. Sólo process start selecciona escenario. Nuevo proceso/directorio vacío resetea; no truncar trace mientras servidor atiende ni cambiar escenario mediante endpoint.

Cada script finite steps especifica expected method/path/query y response status/headers/body literal. El índice sale de count de líneas bajo flock; response debe coincidir con step esperado. Unexpected se registra como UNKNOWN/matched=false, responde error sintético y el harness detiene el caso: no usarlo como evento legítimo para iniciar otros claims/retries. Nunca reenviar a ML por falta de fixture.

Moneda/decimal/IDs: no json_decode + json_encode de un body provider con número monetario; preservar lexemas como90071992547409.1234 y IDs>2^53. Body externo es string dentro del archivo de escenarios; fechas ISO con offset literales. ERP normaliza source time; server no calcula UTC del negocio. Null, campo ausente y cero tienen fixtures diferentes en fakes; ejemplo HTTP representative suficiente.

## 9. Servidor, puertos y cleanup Windows

`PHP_BINARY -S 127.0.0.1:<port> tests/Support/MeliSim/router.php`, proc_open con array y cwd absoluto, sin shell intermedia. Child env sanitizada sólo valores de test, sin credenciales DB/ML heredadas (router no usa DB). Mismo PHP/cURL/JSON/flock que ERP, sin Node/Python server/Redis/Docker/dependencias.

Harness en la clase PHPUnit nueva:

1. Crea directorio único con nombre de run aleatorio, trace vacío y stdout/stderr a archivos (evitar pipes llenos). Verifica proc_open y entorno test; fallo claro, no skip silencioso de HTTP crítico.
2. Reserva temporalmente un puerto vía stream_socket_server tcp127.0.0.1:0, obtiene puerto y libera antes de php -S. Existe race entre release/bind; no afirmar reserva permanente.
3. Startup bounded3s: child debe seguir activo, stderr confirmar bind/listen en dirección esperada y conexión TCP loopback completar. Handshake readiness no envía HTTP y no cuenta. TCP solo no prueba identidad: combinar evidencia del proceso/bind, no aceptar otro servicio en ese puerto. Colisión detectada permite hasta3 intentos locales de startup, sin ningún retry de calls ML.
4. Tests seriales, un servidor por caso. No PHP_CLI_SERVER_WORKERS (Windows) ni procesos persistent shared. Actual TestDatabase.reset destruye DB compartida: no habilitar paralelismo DB porque puertos sean únicos; sólo futuro DB aislado permitiría paralelismo.
5. En finally/tearDown siempre proc_terminate del recurso child directo, cierre de handles y proc_close; deadline de test y assert cerrado. No matar procesos por nombre/puerto ni borrar directorios ajenos; validar temp root/run antes de removal. Si crash del runner impide finally, detectar huérfano propio e informar cleanup, no daemon watcher.

Sin HTTP health/stats endpoints. Seguridad router fail-closed si APP_ENV distinto de test, ausencia de scenario/trace o bind no loopback. No ejecución desde public, no inclusión desde bootstrap productivo, no registry WRITE. No env selector en UI/producción.

## 10. E2E target y límites de wiring

| Flujo | MLSIM-1 local contract / contador | LC2/LC3 pendientes |
|---|---|---|
| A Sales | Fixture webhook POST a Bootstrap in-process → order.sync → WorkExecution/handler con Curl loopback → canonical → GET Sales UI tenant. HTTP order1, duplicate webhook0 adicionales | Una futura prueba browser/socket contra ERP completo; no claim de wiring PHP-Slim por socket sólo por in-process |
| B Sales Audit | POST audit existente con csrf/admin → fixed manual claims A/repair/B → search/order loopback → VALID en DB. Muestra1/limit50/missing1 permite3 GET | Resultado visible de auditoría NO implementado; P1 LC2. No inventarlo en simulador |
| C Billing | Test encola explícito con WorkRepository, tres claims sobre la misma row, cursor/amount/terminal.3 GET | Producer/formulario Billing y manual CLI son P1; no usar bin/work automático como sustituto |
| D Financial | Tras A/C, forOrder PDO-only → KNOWN/UNKNOWN/CONFLICT según facts. Snapshot trace before/after, delta0 | Surface operador P1; NO dependency simulator/client en modelo |
| E OAuth | Fake-only Flow/Connect/Refresh actuales; no HTTP sim | Grant/UI real externo; success callback full socket no cubierto |
| F Invoicing/PostSale | Tests bridge/InvoiceRequest/artifacts sintéticos actuales; Sales origin puede ser A, bridge acceptance local. Delta ML0 | Companion legal/fiscal externo y vista operator P1; pack sigue fail-closed |

No webhook push daemon: payload propio de test va a ERP; MLSIM sólo responde. Ninguna tarea del simulador decide Work, empresa, cuenta, estado de cursor ni Financial. Usar account fixture seller sintético (p.ej.100), nunca credenciales/seller real como sandbox. No usar source ERP1.

## 11. Modelos exactos de llamadas

Sales Audit estable: `P=max(1,ceil(N/L))`; N incluye todas las observaciones del conjunto de ventana remota, guard bands incluidas. `M` sólo los pedidos faltantes de la ventana canónica reparables. CapturaA=P GET search; reparación=M GET order; confirmB=P GET search. `GET=2P+M`. N0 aún1 request por pass. Guard bands no se convierten automáticamente en órdenes a reparar. Ejemplo MLSIM-1 V2: N1/L50/M1→1+1+1=3. SalesListController fija AUDIT_PAGE_LIMIT=50. No fixture51 ni repetición HTTP multi-page: los fakes existentes cubren ese comportamiento y cualquier verificación volumétrica HTTP posterior requiere valor demostrado en LC3/LC4. Ninguna página search requiere GET vacío adicional después de alcanzar paging.total.

Cambios de total/set o respuestas parciales deben detener contrato y **no** conservar fórmula happy como éxito. Script no genera volúmenes ilimitados ni búsqueda de un sample perfecto. Número de claims debe ser finito explícito, incluidas etapas repair wait; no ejecutar untilVALID forever. Reclamaciones de repair wait pueden sumar claims locales sin GET; no confundir claim count y remote count.

Billing: `K` páginas no vacías → `K+1` GET incluido vacío terminal. Normal un claim=1 GET; siguiente claim reusa WorkID/dedupe/cursor. K2→3, K1→2. BILL/CREDIT_NOTE son streams separados; request count se mide por stream.429/206/header parcial→1 GET en claim y no inline retry. No esperar30s ni polling mientras no eligible: para probar próxima página se prepara available_at sólo en DB test (como fixtures actuales), manteniendo ID/claim semantics.

401 bounded existente: recurso hasta2 GET; valid token inicial +401 permite1 AUTH POST fake; si además hubo refresh preflight y nueva401, techo2 AUTH POST por claim. Separar AUTH de recurso y contadores; esas matrices permanecen fake-only. MLSIM HTTP normal usa token fixture vigente y no admite AUTH POST. Un POST sorpresivo es fallo visible, no endpoint a añadir por comodidad.

## 12. Seams y presupuesto de archivos/clases

**Producción MLSIM-1: 0 archivos, 0 clases, 0 config keys, 0 operaciones nuevas.** Reusar `MeliClient::__construct($transport,...,$baseUrl,...)`, `CurlMeliTransport(...,'test')`, registro y handlers; el test compone por constructor. Intervalo0 de test ya soportado; pacing2s sigue fake/timed tests.

Layout implementado por MLSIM-1 V2:

| Archivo futuro | Responsabilidad | Clases nuevas |
|---|---|---:|
| tests/Support/MeliSim/router.php | Envío de fixture finita + trace físico de requests | 0 |
| tests/Support/MeliSim/scenarios.json | Un bundle de strings status/header/body/expected steps sintéticos | 0 |
| tests/Integration/MeliHttpSimulationTest.php | PHPUnit, process lifecycle, assertions y composición E2E; private helpers locales | 1 (TestCase) |

Total3 archivos /1 clase de test; no Scenario/Server/Factory/FakeProvider class. No migraciones ni archivo por endpoint. Los fakes existentes se quedan en sus focal tests; no require otro Test.php para importar un fake helper. En caso de prueba de recovery usar tests fake existentes, no otra clase OAuth sólo por MLSIM.

`bin/work.php`, Routes callback y BillingC0Runtime hardcodean base API. **No son composition roots configurables para el simulador**. No cambiar AppConfig/Environment para decir que MLSIM-1 prueba esos entrypoints por socket. La composición in-process y manual claims de test es el seam exacto requerido hoy.

Si LC3 demuestra una necesidad real de `bin/work.php` o callback browser conectados al simulator, evaluar aparte seam acotado en `AppConfig::fromEnvironment`, `Environment::all` y los `new MeliClient` de `bin/work.php`/`Routes`; authorize URL del Flow ya tiene constructor inyectable pero Routes fija valor. Sólo local/test, destino127.0.0.1 validado y producción ignorar/rechazar selector. No añadir esas piezas en MLSIM-1 ni prometerlas aquí; repetir puerta KISS y RED específico antes. Preferir test composition sobre config global.

## 13. Plan MLSIM-1 V2 — cuatro tareas RED → GREEN

| Task | RED test | GREEN mínimo | Production files | Test files | Counter assertion | STOP condition |
|---|---|---|---|---|---|---|
| 1 lifecycle/counter | Router inexistente, non-test, unexpected/excess y cleanup | Router loopback, JSONL temporal, PHP process owner; host literal guard | 0 | Exactamente3 permitidos | Readiness0, unknown/excess registrados y fallan | Bind/cleanup inseguro, proxy o necesidad de producción |
| 2 Orders | Falta body/scenarios para exact/partial/invalidJSON | Literal response y composición actual | 0 | Mismos3 | Happy1 GET; negativos1 y extras0 | Float loss o nueva operación/transport |
| 3 Audit/Billing/429 | Scenarios faltantes y presupuestos específicos | Audit N1 + reparación + B, Billing2 páginas/terminal, guards | 0 | Mismos3 | Audit3; Billing3; partial1;4291 y followup0 | Cambio productivo, sleeps de Work o widening de escenario |
| 4 zeros/QA/closure | Regresiones de autoridad/Financial y scope exacto | Nuevos asserts del mismo TestCase, focal/neighbors/full QA y docs | 0 | Mismos3 | disabled0/stale0/Financial0;8 escenarios/3 operaciones | Cambio producción, no pasar QA o iniciar LC2 |

La tarea1 es una prueba de harness, no endpoint de negocio UNKNOWN añadido al registro. Requests de test al simulator son loopback local, no REAL_MELI_HTTP. Las cuatro tareas fueron ejecutadas bajo V2; no autoriza ampliar alcance. No implementar producción frente a un test RED que depende de UI P1 ausente; clasificar esa dependencia y detener ese caso.

## 14. Capas y QA futura

- UNIT/INTEGRATION_FAKE: todos los contratos edge, OAuth, timeouts, money/missing/seller, 401, status matrices, rate, locks y claims. Son rápidos y determinísticos; preservarlos.
- HTTP_SIM: Curl real loopback + bytes/header/status y contador exacto; pocos casos dirigidos. Un proceso test no aporta prueba de OAuth issuer/cuota real/TLS externo.
- FULL_LOCAL_E2E: DB desechable + Work/handlers reales + ERP Bootstrap in-process + loopback ML. Sales/Audit/Billing contados; Financial/bridge0. Interfaz operator completa depende de LC2/LC3.

Nueva clase descubierta por phpunit.xml tests/ no nueva suite framework. Focal seleccionable por nombre; suite completa incluye casos críticos. Server setup sólo en tests que lo usan, no bootstrap global. Full suite local Composer/Stan/syntax/PHPUnit/diff más fake regressions LC1. Cero skip silencioso para procesos/cURL cuando se exige FULL local; reportar requisito técnico preciso. MariaDB necesaria ya existe, Docker no es obligatorio.

## 15. Riesgos, no objetivos y score

False confidence: fixtures inventadas representan reacción ERP, no respuesta provider certificada. Money/time/null/truth se compara con expecteds independientes en test, no tomar éxito del script como verdad. Pack/fiscal siguen UNKNOWN/fail-closed. HTTP ingress no equivale a todas las send fallidas ni certifica TLS/quotas; suite fake completa esas otras capas. In-process ERP no prueba success browser callback ni entrypoint bin/work con URL alternativa.

Production leak: router en tests, bind127.0.0.1, APP_ENV=test obligatorio, sin UI/env config productiva, sin uploader/deploy. RemoteHostPolicy no es allowlist exhaustiva de Internet; la composición test debe verificar URI base exacta y no seguir Location. No permitir scenario file con URLs externas ni permitir GET proxy. Duplicación: ocho scripts HTTP representativos; dejar matrices detalladas en fakes. No reutilizar success fixtures de servidor como única expectativa.

ERP1 runtime **no** es simulador. ERP1 DB **no** es simulador. ERP1 app **no** es simulador. ERP1 tokens **no** son fixtures. No sandbox/proxy/oracle implementado. Mercado Libre test users/app/grants sólo futuros después LOCAL_PRODUCT_READY y autorización externa independiente. Companion, DIAN/Alegra, SSH, hosting, deploy, F14live/F15/F16 fuera de alcance.

| Criterio (10=mejor; risk10=menor riesgo) | Score | Rationale |
|---|---:|---|
| correctness | 9 | Owners reales/guards/counters, límites declarados |
| simplicity | 9 | Tres routes y tres files, no OAuth/DB/control API |
| determinism | 8 | Scripts/seq estables, startup race acotada y detectada; DB serial |
| provider_independence | 10 | Sintético/sin Internet/tokens ni ERP1 |
| noise | 9 | No duplicar every edge ni helperhierarchy |
| false-confidence risk (10=riesgo bajo) | 8 | Contrato vs live y wiring parcial claramente separados |
| maintainability | 9 | Registro/transport/handlers actuales; una clase test |

Scores son evaluación de diseño, no QA ni certificación. Ningún score<8; si MLSIM-1 necesita superar presupuesto o aparece cobertura ficticia, rediseñar antes de añadir piezas.

## 16. Paquete adversarial para otra IA — no enviado

Contexto: revisar diseño a partir del SHA7e09bd80e171763eadd4e08e316794d6c3e24706 y functional1ee9da64ea6d23b0f97424d72f1398dd37a8a416. LC1 cerró tresP0; P1 abierto. Propuesta3 GET routes/3files/1TestCase,0 producción; OAuth/pack/fiscal fake-only; trace JSONL temporal. No autoridad para implementar ni llamar APIs externas.

Entregar hallazgos como: severidad, evidencia código, reproducción/falso positivo posible, menor pieza que DELETE/SIMPLIFY/REUSE, test RED y efecto en presupuesto. No proponer plataformas/deps por hábito.

1. ¿Dónde crea falsa confianza la composición de test frente a bin/work.php y callback Routes hardcodeados? ¿Se distingue in-process ERP de browser/socket E2E?
2. ¿Qué facts/headers/cuotas/tax semantics no certifica el proveedor? ¿Alguna fixture desbloquea pack/billing-info sin consumer o evidencia real?
3. ¿Qué endpoint, scenario, archivo o helper se puede borrar? ¿Ocho scripts dan valor sobre38 fakes o se duplica lógica innecesaria?
4. ¿Hay loops ocultos en claims, repair wait, scripts/counter, readiness o deadline? ¿N0/guard bands/cambio de set contradicen2P+M?
5. ¿Prueba ERP o inventa ML? ¿Router calcula canonical, Financial o cursor en vez de devolver texto sintético?
6. ¿Se simula algún endpoint sin caller? Demostrar que sólo order/search/Billing tienen consumidor E2E.
7. ¿Financial toca simulator/client/tokens/Work? Exigir diff0 y counter delta0 aun con server activo.
8. ¿Puede ejecutarse simulator accidentalmente en producción, servir archivos o hacer fallback remoto? ¿APP_ENV/test/bind y URI exacta son suficientes para alcance declarado?
9. ¿El counter registra antes de respuesta, unknown y excess calls sin esconder errores? ¿Se mezclan HTTP requests con send fallida/FAKE_AUTH y conexiones readiness?
10. ¿La reserva temporal de puerto puede aceptar otro proceso? ¿Cleanup Windows mata únicamente child propio? ¿Hay files abiertos que impidan removal?
11. ¿Body money/IDs se roundtrippea por float? ¿Null/missing/zero y UTC fuente tienen expecteds independientes?
12. ¿Puede la DB compartida ser reseteada concurrentemente? ¿Se inventa estado provider en una DB nueva?
13. ¿Full local OAuth HTTP es realmente necesario para contrato local, o el fixture connected oculta un requisito operator que pertenece a LC2/externo?
14. ¿Algún fallo de QA o escenario invita a añadir clases/URL config/colas/retry frameworks innecesarios? Señalar exactamente dónde rompería KISS.

## 17. Cierre MLSIM-0

MLSIM-0 fue design-only. MLSIM-1 V2 añadió exclusivamente los tres archivos tests indicados, reutilizó MariaDB desechable y PHP loopback para QA, sin cambio productivo/config/schema/registry. SSH/Hostinger/ERP1runtime/MLreal/OAuthreal/Alegra/DIAN/deploy/remote writes0. Ver recibo actual superior; no extender implementación.

CURRENT=MLSIM-1 CLOSED — representative local HTTP simulator. LOCAL_PRODUCT_READY=NO. NEXT=LC2 P1 operator completeness, NOT_STARTED. LC3/LC4=PENDING; F14LIVE=DEFERRED; F15=PAUSED; F16=UNAUTHORIZED.

STOP — MLSIM-1 V2 solamente; LC2 no ejecutado.

## Anexo — todos los fake transports actuales

Inventario por declaración `implements MeliTransport`: 37 clases con nombre y una anónima, ninguna creada por MLSIM-0. FILE es relativo al repo auditado. OWNER es el test focal existente. Los nombres de tests indican prueba conductual concreta, no porcentaje de cobertura; se citan ejemplos del archivo, no se asegura que cada auxiliar se use en todos sus casos. HTTP añade sólo socket compartido en las tres operaciones seleccionadas; los demás escenarios permanecen fake-only.

| FILE | OWNER / fake | PURPOSE | WHAT IT ALREADY PROVES (ejemplos) | WHAT HTTP ADDS | DUPLICATION RISK |
|---|---|---|---|---|---|
| `tests/Integration/BillingC0MeliClientProbeTest.php` | BillingC0MeliClientProbeTest / BillingC0SequenceTransport | Respuesta/lista requests del consumidor BillingC0MeliClientProbeTest | testProbeComposesExactReadOnlyBillingRequestAndSequentialCursor | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/BillingC0RuntimeTest.php` | BillingC0RuntimeTest / BillingC0RuntimeTransport | Respuesta/lista requests del consumidor BillingC0RuntimeTest | testConnectedAccountWithFreshTokenReturnsOnlyRequiredRuntimeData; testProbeComposesExistingMeliClientReadOnlyWithInjectedTransport | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/BillingPeriodSyncHandlerTest.php` | BillingPeriodSyncHandlerTest / BillingPeriodSyncFakeTransport | Respuesta/lista requests del consumidor BillingPeriodSyncHandlerTest | testNonEmptyFirstPagePersistsDetailsAndDefersSameWorkAtomically; testValidPageReplayUpsertsOneDurableBillingFactWithItsContext | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/DebugCorrelationTest.php` | DebugCorrelationTest / DebugCorrelationTransport | Respuesta/lista requests del consumidor DebugCorrelationTest | testDebugOnCorrelatesWebhookWorkAndPhysicalOrderHttpWithoutSecrets; testDebugOffKeepsSameBusinessOutcomeAndWritesNoDvr | KEEP_FAKE_ONLY; ningún endpoint adicional | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/FinancialFactSufficiencyTest.php` | FinancialFactSufficiencyTest / FinancialReplayFakeTransport | Respuesta/lista requests del consumidor FinancialFactSufficiencyTest | testCanonicalBillingContextSupportsDirectDerivedAndAmbiguousIdentity; testCanonicalFactsPreserveShippingBillingAssociationsCurrencyAndLateAdjustments | KEEP_FAKE_ONLY; ningún endpoint adicional | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/MeliBillingPeriodDetailsContractTest.php` | MeliBillingPeriodDetailsContractTest / BillingPeriodRecordingTransport | Respuesta/lista requests del consumidor MeliBillingPeriodDetailsContractTest | testRegistryDeclaresExactBillingPeriodDetailsReadOperation; testBillingPeriodRequestUsesPeriodFirstCursorContract | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/MeliClientBoundaryTest.php` | MeliClientBoundaryTest / RecordingTransport | Respuesta/lista requests del consumidor MeliClientBoundaryTest | testOfficialOperationRegistryContainsOnlyImplementedContracts; testFakeTransportObservesExactlyOnePhysicalRequest | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/MeliClientBoundaryTest.php` | MeliClientBoundaryTest / TimedRecordingTransport | Respuesta/lista requests del consumidor MeliClientBoundaryTest | testOfficialOperationRegistryContainsOnlyImplementedContracts; testFakeTransportObservesExactlyOnePhysicalRequest | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/MeliClientLosslessNumbersTest.php` | MeliClientLosslessNumbersTest / LosslessNumberTransport | Respuesta/lista requests del consumidor MeliClientLosslessNumbersTest | testExactNumberOperationPreservesJsonNumberLexemesAndLeavesStringsUntouched; testNormalOperationKeepsNativeJsonDecodeBehavior | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/MeliClientResponseMetricsTest.php` | MeliClientResponseMetricsTest / QueueTransport | Respuesta/lista requests del consumidor MeliClientResponseMetricsTest | testSuccessfulJsonResponseIsNormalizedAndOnePhysicalCallIsCounted; testClientErrorIsNormalizedAndCountedWithoutLeakingResponseBody | KEEP_FAKE_ONLY; ningún endpoint adicional | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/MeliOrderGetContractTest.php` | MeliOrderGetContractTest / SalesOrderRecordingTransport | Respuesta/lista requests del consumidor MeliOrderGetContractTest | testRegistryDeclaresExactOrderReadOperation; testExactOrderRequestResolvesOnlyTheOrderIdPathParameter | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/MeliOrdersSearchContractTest.php` | MeliOrdersSearchContractTest / OrdersSearchRecordingTransport | Respuesta/lista requests del consumidor MeliOrdersSearchContractTest | testRegistryDeclaresSellerOrderSearchReadOperation; testSellerOrderSearchBuildsBoundedDateFilteredPage | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/MeliOrdersSearchContractTest.php` | MeliOrdersSearchContractTest / (anonymous) | Respuesta/lista requests del consumidor MeliOrdersSearchContractTest | testRegistryDeclaresSellerOrderSearchReadOperation; testSellerOrderSearchBuildsBoundedDateFilteredPage | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/MeliRateLimitTest.php` | MeliRateLimitTest / RateLimitQueueTransport | Respuesta/lista requests del consumidor MeliRateLimitTest | testRetryAfterCreatesCooldownAndDoesNotRetryPhysicalHttp; testMissingRetryAfterUsesBoundedBackoffWithJitter | KEEP_FAKE_ONLY; ningún endpoint adicional | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/OAuthConnectServiceTest.php` | OAuthConnectServiceTest / OAuthQueueTransport | Respuesta/lista requests del consumidor OAuthConnectServiceTest | testAuthorizationCodeExchangeUsesFormPkceAndPersistsEncryptedTokensUsingExpiresIn; testDifferentExpiresInValueIsUsedInsteadOfHardcodedLifetime | KEEP_FAKE_ONLY; ningún endpoint adicional | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/OAuthRefreshServiceTest.php` | OAuthRefreshServiceTest / RefreshQueueTransport | Respuesta/lista requests del consumidor OAuthRefreshServiceTest | testValidAccessTokenIsReturnedWithoutRefreshHttp; testExpiredTokenRefreshesOnceAndPersistsNewSingleUsePair | KEEP_FAKE_ONLY; ningún endpoint adicional | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/OAuthRefreshStatusGuardTest.php` | OAuthRefreshStatusGuardTest / OAuthStatusGuardTransport | Respuesta/lista requests del consumidor OAuthRefreshStatusGuardTest | testAttentionAccountNeverReusesRefreshTokenAutomatically | KEEP_FAKE_ONLY; ningún endpoint adicional | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/OrderSyncMalformedWorkTest.php` | OrderSyncMalformedWorkTest / MalformedOrderSyncGuardTransport | Respuesta/lista requests del consumidor OrderSyncMalformedWorkTest | testMalformedOrderSyncClaimIsTerminallyFailedAndCannotBeRecoveredToPending | KEEP_FAKE_ONLY; ningún endpoint adicional | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/OrderSyncWorkProcessorTest.php` | OrderSyncWorkProcessorTest / OneOrderTransport | Respuesta/lista requests del consumidor OrderSyncWorkProcessorTest | testProcessorMapsClaimToSyncOrderHandler | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/OrderSyncWorkRunnerPacingTest.php` | OrderSyncWorkRunnerPacingTest / TwoOrderTimedTransport | Respuesta/lista requests del consumidor OrderSyncWorkRunnerPacingTest | testTwoOrderSyncClaimsSharePhysicalRequestPacingAcrossRunnerCycle | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/SalesAuditBaselineLifecycleTest.php` | SalesAuditBaselineLifecycleTest / SalesAuditBaselineLifecycleTransport | Respuesta/lista requests del consumidor SalesAuditBaselineLifecycleTest | testNewEquivalentValidRunSupersedesPriorValidBaselineAndPrunesItsEvidence; testNewInternallyConfirmedDifferentFingerprintPreservesPriorBaselineAndEndsAttention | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/SalesAuditCaptureHandlerTest.php` | SalesAuditCaptureHandlerTest / SalesAuditCaptureTransport | Respuesta/lista requests del consumidor SalesAuditCaptureHandlerTest | testPartialPageCannotAdvanceEitherCapturePass; testOnePagePersistsObservedEvidenceAndNeverEnqueuesOrderSync | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/SalesAuditConfirmMismatchTest.php` | SalesAuditConfirmMismatchTest / SalesAuditConfirmMismatchTransport | Respuesta/lista requests del consumidor SalesAuditConfirmMismatchTest | testTerminalCaptureBMismatchCompletesWorkAndMovesRunToAttention | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/SalesAuditConfirmValidTest.php` | SalesAuditConfirmValidTest / SalesAuditConfirmValidTransport | Respuesta/lista requests del consumidor SalesAuditConfirmValidTest | testTerminalCaptureBEqualityCompletesWorkAndMovesRunToValid | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/SalesAuditContinuationTest.php` | SalesAuditContinuationTest / SalesAuditContinuationTransport | Respuesta/lista requests del consumidor SalesAuditContinuationTest | testNonTerminalPageStoresStableTotalAndEnqueuesExactlyOneContinuation; testRemoteTotalDriftFailsCurrentPageWithoutPartialEvidenceOrContinuation | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/SalesAuditRepairRuntimeTest.php` | SalesAuditRepairRuntimeTest / SalesAuditRepairRuntimeTransport | Respuesta/lista requests del consumidor SalesAuditRepairRuntimeTest | testRepairingSalesAuditEnqueuesOneChildAndDefersParentWithoutRemoteHttp; testTerminalRepairChildMovesRunToAttentionWithoutRecreation | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/SalesAuditTerminalFingerprintTest.php` | SalesAuditTerminalFingerprintTest / SalesAuditTerminalFingerprintTransport | Respuesta/lista requests del consumidor SalesAuditTerminalFingerprintTest | testTerminalHandlerPersistsFingerprintTransitionsToRepairingAndEnqueuesOneContinuation; testTerminalCaptureWithNoMissingLocalOrdersTransitionsDirectlyToConfirming | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/SalesAuditTransientFailureTest.php` | SalesAuditTransientFailureTest / SalesAuditTransientTransport | Respuesta/lista requests del consumidor SalesAuditTransientFailureTest | testOAuthPreflightDatabaseFailureEscapesWithoutBusinessMutationOrHttp; testBusyRefreshLockDefersAuditWorkWithoutAttemptOrAccountMutation | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/SalesAuditUnauthorizedTest.php` | SalesAuditUnauthorizedTest / SalesAuditUnauthorizedTransport | Respuesta/lista requests del consumidor SalesAuditUnauthorizedTest | testUnauthorizedSearchRefreshesTokenAndRetriesExactlyOnce; testSecondUnauthorizedAfterRefreshFailsTerminallyWithoutEvidence | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/SalesVerticalSliceEndToEndTest.php` | SalesVerticalSliceEndToEndTest / SalesSliceOrderTransport | Respuesta/lista requests del consumidor SalesVerticalSliceEndToEndTest | testWebhookRunsThroughWorkerAndBecomesVisibleInTenantSalesUi | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/SalesWorkPoisonLoopTest.php` | SalesWorkPoisonLoopTest / PoisonLoopGuardTransport | Respuesta/lista requests del consumidor SalesWorkPoisonLoopTest | testUnsupportedWorkTypeIsTerminallyFailedAndCannotBeRecoveredToPending | KEEP_FAKE_ONLY; ningún endpoint adicional | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/SalesWorkProcessorTest.php` | SalesWorkProcessorTest / SalesAuditProcessorTransport | Respuesta/lista requests del consumidor SalesWorkProcessorTest | testProcessorDoesNotTurnRunStatusDatabaseFailureIntoAuditScopeOutcome; testProcessorDispatchesSalesAudit | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/SalesWorkProcessorTest.php` | SalesWorkProcessorTest / BillingRoutingProcessorTransport | Respuesta/lista requests del consumidor SalesWorkProcessorTest | testProcessorDoesNotTurnRunStatusDatabaseFailureIntoAuditScopeOutcome; testProcessorDispatchesSalesAudit | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/SyncOrderDecimalPrecisionTest.php` | SyncOrderDecimalPrecisionTest / DecimalPrecisionTransport | Respuesta/lista requests del consumidor SyncOrderDecimalPrecisionTest | testLargeJsonNumberDecimalsArePersistedWithoutFloatPrecisionLoss | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/SyncOrderHandlerPersistenceTest.php` | SyncOrderHandlerPersistenceTest / OrderQueueTransport | Respuesta/lista requests del consumidor SyncOrderHandlerPersistenceTest | testExactOrderIsNormalizedAndCurrentClaimCompletesAtomically; testSaleFeePersistsExactRemotePerUnitValueWithoutMultiplyingQuantity | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/SyncOrderHandlerRemoteFailureTest.php` | SyncOrderHandlerRemoteFailureTest / RemoteOutcomeTransport | Respuesta/lista requests del consumidor SyncOrderHandlerRemoteFailureTest | testOrderNotFoundFailsTerminallyWithoutPersistingAnOrder; testOrderNotFoundAfterUnauthorizedRefreshFailsWithoutAnotherRetry | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/SyncOrderHandlerTokenFailureTest.php` | SyncOrderHandlerTokenFailureTest / NoOrderHttpTransport | Respuesta/lista requests del consumidor SyncOrderHandlerTokenFailureTest | testOAuthPreflightDatabaseFailureEscapesWithoutBusinessMutationOrHttp; testRefreshRateLimitDefersWorkOnceAndKeepsAccountConnected | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
| `tests/Integration/SyncOrderHandlerTokenFailureTest.php` | SyncOrderHandlerTokenFailureTest / QueuedOAuthWorkTransport | Respuesta/lista requests del consumidor SyncOrderHandlerTokenFailureTest | testOAuthPreflightDatabaseFailureEscapesWithoutBusinessMutationOrHttp; testRefreshRateLimitDefersWorkOnceAndKeepsAccountConnected | Socket Curl sólo en order/search/Billing; no reemplaza este focal | Alta si se porta íntegramente; REUSE focal |
