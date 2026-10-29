# ERP MELI 2.0 — Plan maestro de cierre de producto (propuesta de revisión)

**Corte:** 2026-10-28, America/Bogota. **Estado:** PROPUESTO / NO AUTORIZA MERGE NI DEPLOY. **Base de lectura:** PR #21, HEAD 83e5dda0a47d553bf79099ceec14290fcf564e8d, más entregas locales Payment Facts V2 + M02 + split 014/015, todas sin commit. El documento es fuente de rumbo, no prueba de implementación.

## Objetivo que manda
Completar primero un ERP utilizable en local, manteniendo las propiedades esenciales de ERP1 sin trasladar sus problemas: 429, amplificación HTTP de Billing, colas FIFO bloqueadas, trabajadores obsoletos, pérdida de cobertura y confusión entre UNKNOWN y cero. Después, y solo con autorización nueva, probar runtime/Hostinger y proveedor REAL en modo de solo lectura. Remote writes OFF.

## Leyes obligatorias (leer el canon, no copiarlo en prompts)
L1 KISS/Ponytail: una aplicación PHP/Slim, una MariaDB, una cola work_items, un WorkRunner y un MeliClient. DELETE > SIMPLIFY > REUSE > ADD. 
L2 Antirruido: no crear prototipos paralelos, shadow ledgers, informes redundantes ni rutas inactivas sin consumidor. Borrar/fusionar solo con evidencia.
L3 Antiparches: resolver causa raíz con prueba negativa y regresión; no apilar salvaguardas especulativas.
L4 Human-First: respetar Design Master, verdad de negocio, permisos reales, móviles, teclado, cero != desconocido; no declarar mockup fiel sin fuente verificable.
L5 Evidencia: código/CI SHA exacto > evidencia primaria > relato; distinguir PASS LOCAL, PARTIAL, NOT_RUN, CERTIFIED y RELEASE READY.
L6 Anti-ping-pong: ChatGPT dirige y audita; Codex ejecuta un lote coherente solo si hace falta PC, browser o MariaDB; un solo NEXT y reutilización de tests válidos.

Leer también AGENTS.md, docs/ERP2_AUTHORITY.md, docs/CURRENT_CHECKPOINT.md, design-system/erp-meli-2-0/MASTER.md y la ley visual al tocar UI.

## Estado de verdad al corte
- PR #17 a #21: abiertos Draft/no merged. PR #21: 83e5dda0a47d553bf79099ceec14290fcf564e8d, base PR #19. Main NO contiene automáticamente PR #21.
- PR #21 core: Work/FIFO, cooldown 429, Sales Audit, Billing por período, Financial indexado, catálogo local, inventario local, ERP API, bridge y PostSale implementados localmente en alcances documentados.
- Entrega local posterior no publicada: Payment Facts V2, M02 test-only con lectura simulada, migraciones 014+015 separadas; suite reportada 929 tests/8215 assertions/1 skip/0 FAIL. NO atribuir ese QA a PR #21 ni a producción.
- Monthly V3.2: diagnóstico local propuesto sin consumidor de producto ni QA MariaDB integrada; NO enviar el prompt ERP2_MONTHLY_V32_ONCE_INTEGRATE_QA_20261028 hasta re-priorización. MONTHLY_REPORT_FACTURABLE=NO.
- Ocho WIP de M02/M03/M04 con SHA históricos deben permanecer intactos. No editarlos, resetearlos ni usar su checkout para tests.
- F14 LIVE, F15, F16, cron/hosting real y cobertura de proveedor NO certificados; RELEASE_READY=NO.
- README previo declara LOCAL_PRODUCT_READY para el alcance L1-L9, NO para paridad de catálogo público/privado, reporte mensual ni aceptación del producto completo.
- Los mockups finales aprobados no se han identificado con artefacto, ruta, SHA y estado equivalente: APPROVED_MOCKUP_FIDELITY=NOT_VERIFIABLE. El Design Master sí está versionado.

## G0 — Reconciliación de candidato y protección (sin nueva implementación)
Entregable: una matriz codebase/PR/artefactos que elija UN candidato sobre PR #21 y un SOLO parche 012–015/M02/Payment Facts; resolver solapamiento del WIP sin copiar el Canary Reader duplicado. Documentar rutas, SHA, fuente, tests reutilizados. Definir compatibilidad de 014 combinado ya aplicado antes de cualquier upgrade de DB persistente. No commit/merge sin autorización específica. GATE: baseline y permisos claros, cero paths protegidos modificados.

## G1 — Operación Work/FIFO/429/cron/manual completa (prioridad máxima)
Reutilizar WorkRunner, WorkExecution y bin/work.php --manual-one; NO nuevos workers, colas ni cron engines. La misma aplicación puede ejecutar el worker como proceso CLI separado del proceso web (e incluso operarse en un host dedicado autorizado), siempre contra la MISMA MariaDB, el MISMO esquema work_items, el MISMO GET_LOCK y sin segundo orquestador. Eso es separación de procesos, NO microservicios ni múltiples colas. Confirmar 1 Work global, FIFO por available_at/id, dedupe, GET_LOCK, claim_token, estado pending/running/done/failed, poison item, 429 Retry-After, cooldown y reanudación tras caída. Verificar apagado de automatic y que manual-one procesa a lo sumo 1 elemento con el mismo lock, visible desde UI como Procesar ahora solo cuando rol y CSRF sean correctos y la ejecución sea segura para request; cero background polling. Cron Work y cleanup se validan en simulador/local, con pruebas de solapamiento, reinicio y presupuesto, sin instalar cron en Hostinger. Presentar al usuario estado humano: pendiente/en curso/reprogramado/fallido; una acción manual sin hacer HTTP real. GATE: E2E sintético de enqueue→runner→429→retry→done, bloqueo concurrente, auto OFF, manual ON, y cero segundo motor.

**Autoridad ERP1:** contrastar funcionalidades con main HEAD 3739cb2c95b926466c8346a9ead2329908e08bf8 verificado el 28-oct-2026; no considerar vigente el snapshot fd07d148 de la especificación ni asumir que el título del README heredado refleja el commit. Una feature legacy solo entra al alcance cuando tenga consumidor de ERP2 y caso de uso aprobado.

## G1-A — Contrato HTTP físico y calibrador manual (decisión 28-oct-2026)

**Requisito del usuario:** calibrador de consumo de Mercado Libre **manual, visible, configurable y persistente**. No debe estar oculto en constantes de PHP, ni modificar automáticamente el intervalo en función de métricas. **Calibrar es una acción humana; aplicar la configuración y respetar las respuestas 429 es comportamiento automático de seguridad.**

- **Superficie:** Configuración del sistema / apartado «Consumo de API Mercado Libre», accesible únicamente al administrador autorizado. Mostrar valor vigente, unidad **milisegundos entre intentos HTTP**, descripción del efecto, guardado explícito con CSRF y confirmación visible. Un valor global configurable es el punto de partida KISS; desgloses por cuenta/familia requieren evidencia posterior.
- **Valor inicial:** conservar 2000 ms existente como *predeterminado técnico provisional*, NO como límite publicado por Mercado Libre. Permitir ajustar manualmente dentro de un intervalo positivo técnicamente validado (límites finales mediante QA). Cambios guardados en system_settings tipada y consumidos por **todos** los constructores de MeliClient de ejecución efectiva. No aceptar 0 en producción; 0 solo en tests aislados si corresponde.
- **Visibilidad:** reutilizar api_usage_daily/meli_cooldowns para mostrar solicitudes contabilizadas, resultados 429, operación/familia y próximo desbloqueo, indicando el alcance de su agregación diaria. Contar por **intento físico HTTP** y distinguir solicitudes de recursos recibidos. Si excepciones de transporte no están contabilizadas en el agregado, reconocerlo y probarlo antes de afirmar exactitud. No agregar dashboard ni tabla histórica nuevos.
- **Acción humana de calibración:** administrador observa datos, selecciona intervalo y pulsa «Guardar calibración». La aplicación **nunca** sube ni baja por sí sola ese valor. Cualquier prueba manual de tráfico HTTP real está bloqueada hasta F14 y autorización específica; antes, solo transporte simulado. Sin autocalibración, sondas ni campañas de tráfico.
- **Protección 429:** Retry-After, cooldown por familia, defer del Work y ausencia de reintentos inmediatos siguen automáticos; eso **no** es autocalibración. Nunca saltar cooldown aunque el administrador elija intervalo menor.
- **Concurrencia:** lastDispatchAtNs en MeliClient es estado de UNA instancia PHP, no un intervalo global entre procesos. Medir actores reales (web, cron, CLI) y verificar intercalación con transports simulados. Reutilizar WorkRunner/GET_LOCK y componentes existentes antes de considerar coordinación persistente mínima; no crear motor nuevo ni cron calibrador.
- **Protocolo:** cada invocación a MeliTransport::send es un intento HTTP individual. orders.get, orders.search y páginas Billing son GET separados. No gRPC, fan-out ni lotes internos de trabajos que generen ráfagas. Billing por página con limit 1000 es **un GET** que devuelve registros, no mil solicitudes. items.bulk es **un GET** multirecurso ya presente en M02 test-only; NO habilitarlo en producción ni sustituirlo en silencio por veinte GET sin decisión explícita. Distinguir solicitud oficial multirecurso de motor batch interno y preservar el objetivo: minimizar llamadas reales sin tráfico no calibrable.
- **QA G1:** admin ve y cambia intervalo con controles 403/419/422, persistencia y propagación a CLI/web; transporte simulado prueba intervalos, conteo de intentos, 429 diferidos, ausencia de autocalibración y no ráfagas con cron/manual/OAuth. Ejecutar una suite global solo tras cambio funcional coherente. No depender de Hostinger.

**Auditoría exacta PR #21:** MeliClient tiene minRequestIntervalMs=2000 y pacePhysicalRequest() en memoria; SystemSettings y /settings/system NO exponen calibración. ApiUsageRecorder agrega respuestas pero excepción de transporte solo registra debug. bin/work.php limita 25 Works/45 s, NO llamadas HTTP. MeliCooldownRepository persiste 429 y backoff. Estado: CALIBRADOR_MANUAL_UI=NO_IMPLEMENTADO; HTTP_ATTEMPT_ACCOUNTING=PARTIAL; RUNTIME_MULTI_PROCESS_CALIBRATION=NOT_CERTIFIED.

## G1-B — Estado local post-QA / 28-oct-2026 (sin merge)

**No extrapolar a GitHub:** el candidato G1 existe SOLO en el clon aislado Windows `C:\codex\ERP2-reports\ERP2-G1-HTTP-MANUAL-GLOBAL-PACE-20261028\candidate`, sobre PR #21 @ `83e5dda0a47d553bf79099ceec14290fcf564e8d`; incluye baseline local Payment Facts/M02/014/015 y migración 016. El HEAD de PR21 permanece sin G1; código fuente G1 final NO versionado. Este documento es actualización de estado, no publicación del candidato.

**Auditoría independiente de evidencia (ChatGPT):** informe SHA-256 `c3657164fbb3d81869ba76e3643046c0e386c69d6dd54543d8b35ba016bfedc6`; ZIP evidencias `62917f5f7e77462f23c821939d37fd3d9953801d541bdc4d8a3a6171baee3ab8`; ZIP CRC PASS y manifest 28/28; informe externo idéntico al ZIP; la salida original PHPUnit contiene 935 tests/8255 assertions/1 skipped/0 failures/0 errors; tests nuevos 6/39 PASS. Código final de cinco correcciones RED→GREEN no presente en el ZIP, solo hashes; **SOURCE_CODE_INDEPENDENT_AUDIT=PARTIAL**.

**Aceptación local acotada:** calibración manual visible 250–60000ms, predeterminado 2000ms, guardado por System Admin CSRF, valor de prueba 750ms; SQL 016 crea dos columnas en `system_settings`; gate GET_LOCK usa PDO dedicado, coordinación probada con dos conexiones dentro de un proceso; 429 proveedor conserva cooldown; espera local usa status 0 y `meli_local_pacing`, no se atribuye al proveedor; QA focal y completa PASS reportado, sin MELI real. WIP históricos 8/8 SHA idénticos (raíz `C:\codex\ERP2-reports\ERP2-L4-M03-M04-QA-GATE-REPAIR-20261023\repo`).

**Gates abiertos obligatorios:** (1) `Billing/C0/BillingC0Runtime.php` crea MeliClient sin dispatchGate (no habilitar C0 real); (2) falta web «Procesar ahora» aunque `bin/work.php --manual-one` existe; (3) no se ejecutó carrera con dos procesos OS ni caída DB durante send; (4) `api_usage_daily.requests` no incluye todos los fallos de transporte; (5) screenshot móvil tras POST/303 vuelve arriba y no demuestra confirmación visible sin desplazamiento; (6) cron/Hostinger/REAL_MELI_HTTP no certificados; (7) para revisión línea a línea solicitar un paquete **sanitizado del diff final de fuente y rutas nuevas** vinculado a hashes, NO reejecutar suite completa sin nuevos cambios.

**Decisión:** `G1_IMPLEMENTED_LOCAL=YES`, `G1_RELEASE_CERTIFIED=NO`, `LOCAL_PRODUCT_COMPLETE=NO`, `MONTHLY_REPORT_FACTURABLE=NO`, `REAL_MELI_HTTP=0`, `REMOTE_WRITES=OFF`. No commit/push/merge/deploy a PR funcional. Próxima entrega: un solo lote coherente para resolver faltantes G1, con pruebas focales de sus cambios y un gate final una vez, después de auditoría de código; no emitir micro-prompts ni reabrir H9/H10/M02.

## G1-C — Cierre operativo acordado, pendiente de ejecutar

**Fecha:** 2026-10-28, America/Bogota. **Estado:** PLANIFICADO / NO EJECUTADO / NO AUTORIZA PUBLICACIÓN. PR21 HEAD `83e5dda0a47d553bf79099ceec14290fcf564e8d` (open/draft/no merge). G1 V2 local ya aplicado y QA 935/8255/1 skip reportada; no reaplicar transformador ni repetir esa suite antes de cambios reales.

**Nuevo hallazgo forense:** la confirmación `?api_calibration_saved=1` del controlador G1 V2 puede mostrar un «guardado» sin POST previo. La aceptación exige aviso de un solo uso derivado de sesión y **solo** tras update persistido exitoso, visible arriba en móvil; GET con query fabricada no acredita un guardado. El backend de G1 final aún no ha sido auditado por bytes completos en ChatGPT: en evidencia de Codex faltó diff de cinco correcciones adicionales, sí se conservan hashes y QA.

**Lote único definido:** `ERP2_PROMPT_CODEX_LUNA_G1_CIERRE_OPERATIVO_UNICO_20261028.md` generado por ChatGPT (SHA256 `f0458e6098a1815c3d9f0382ee494eaff4edbbe994936ae46e4c145500217a3c`). Incluye prechange source bundle saneado, revisión final en origen, composición `Billing/C0/BillingC0Runtime.php` bajo gate físico existente, una activación manual durable y honesta consumida por el worker CLI existente (sin shell ni motor nuevo), contador HTTP de fallos de transporte, carrera dos procesos OS, flash genuino, tests focales y una suite final. Codex solo puede trabajar en el clon aislado verificado, preservando ocho WIP. **PROMPT_PREPARADO_NO_ENVIADO**.

**Nota KISS del botón manual:** nunca afirmar «Procesado» al hacer POST si el worker lo consumirá en su siguiente tick; mostrar «Solicitud pendiente». No bloquear un request web durante 45 s, lanzar shell desde web ni activar HTTP real. Si demostrar semántica y recuperación requiere otro motor de colas, conservar CLI, marcar `WEB_MANUAL_TRIGGER=BLOCKED` y justificar; no inventar una aprobación.

**Puertas G1 aún abiertas:** C0 shared-gate, manual web, dos procesos OS, contabilización fiel o limitación declarada, aviso genuino, diff final fuente, cron real; NO Hostinger ni real MELI. `G1_RELEASE_CERTIFIED=NO`. No mezclar «G1 local complete» con «release ready» o `MONTHLY_REPORT_FACTURABLE`.

## G2 — Paridad de producto: empresas, cuentas, catálogo, publicaciones e inventario
Inventariar requisitos y funciones actuales ERP1 únicamente como referencia; definir públicamente qué significa catálogo público y catálogo privado ANTES de implementar (ownership y visibilidad, NO asumir que son dos tablas). Reutilizar productos, cuentas, inventory y lector M02; no copiar ERP1 ni el lector Canary del WIP. Crear UI operativa por empresa/cuenta con búsqueda/paginación y estados honestos, pasando de M02 test-only a un flujo de producto cuando exista una fuente local certificable. Si proveedor real es requisito, conservar estado pendiente hasta F14: no fingir publicación real. Confirmar roles y aislamiento. GATE: matriz de paridad firmada, rutas funcionales, tests empresa ajena y mockups/versiones vinculados.

## G3 — Billing/Financial y Reporte mensual
Conservar Billing por períodos y Financial indexado + readiness de backfill 013; no order-by-order para Billing, no nuevo ledger. Usar Payment Facts como fuente observada, no como prueba de cobertura: Sales Audit por date_created no certifica pagos date_approved. Resolver pagos entre órdenes/packs, fecha Bogotá, duplicados, refunds, monedas, estado unknown/partial/observed y 014 legacy checksum. Construir inicialmente vista/draft de diagnóstico local autorizada SOLO si tiene consumidor concreto; no sumas facturables, aprobar ni facturar sin fuente y cobertura de pagos verificadas. Considerar endpoint Billing order details solo como contraste de identidad, nunca inferir amount del pago. GATE LOCAL: UX draft honesta y QA; GATE FACTURABLE separado tras certificar proveedor/identidad/cobertura.

## G4 — Aceptación visual de producto completo
Autoridad: docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md y Design Master. Buscar el mockup aprobado real por pantalla/estado/viewport/SHA; si no está disponible, entregar diferencia respecto a tokens y rutas, sin porcentaje inventado de fidelidad. Revisar flujos end-to-end desktop y 390/430 móviles, roles, permisos, zoom, foco, acciones visibles, errores/empty states. Reutilizar H9/H10 y capturas cuando SHA y UI no cambiaron; ejecutar solo deltas. GATE: firma humana explícita del candidato, incidencias altas resueltas, sin falso cierre por ausencia de mockup.

## G5 — Candidato local completo y preparación de integración
Matriz de módulos (existente/parcial/ausente/no requerido) y criterios de aceptación. Ensayo MariaDB sobre 012→015 con backup/restore, compatibilidad 014 original y backfill 013, aislados; no interpretar PASS nuevo como upgrade universal. Reconciliar PR #17 vs #18 y #20 vs #21; integración secuenciada sin merge directo a main. Una suite final sobre árbol EXACTO, E2E de flujos modificados, reporte de riesgo y rollback. Gate: LOCAL_PRODUCT_COMPLETE=YES solo después de aceptación del alcance del usuario y evidencia; sin Hostinger ni real MELI.

**Definición de completo:** LOCAL_PRODUCT_COMPLETE acredita flujos, permisos, UI y fallas simuladas del alcance aprobado; no equivale a PROVIDER_PAYMENT_COVERAGE_CERTIFIED ni a REPORT_MONTHLY_FACTURABLE. Si facturación mensual certificada es requisito indispensable de salida, el resultado global seguirá PARTIAL hasta verificarla con proveedor/autoridad permitidos. No maquillar ese bloqueo.

## G6 — Preproducción externa y corte (último)
Solo con decisión/autorización aparte: PHP/MariaDB/permissions/runtime/cron en hosting; OAuth y APIs READ-only reales con cupos acotados y Kill Switch de escrituras OFF, métricas 429/Retry-After y un worker exclusivo; backup/restauración/rollback. F15 cutover y F16 remote writes requieren autorizaciones distintas; ninguna fase local las activa.

**Estado del prompt V3.2 existente:** HOLD / NO ENVIAR. Integra un diagnóstico sin ruta ni consumidor de negocio; 61 pruebas offline no demuestran una función completa. Reusar únicamente si G3 demuestra consumidor real y la verificación de cobertura lo justifica. No repetir 929 tests ni reabrir M02 por rutina.

## Reglas de salida y no-ping-pong para cada lote
Cada prompt: (1) ID; (2) link a ESTE plan y canon AGENTS; (3) HEAD/tree y parches permitidos; (4) objetivo producto + criterio de salida; (5) paths/tareas prohibidos, WIP; (6) escenarios negativos; (7) pruebas SOLO para delta y 1 suite final de candidato si cambió; (8) evidencias hashes y límites; (9) único NEXT. No emitir un nuevo lote solo por un reporte PASS. No superar dos intentos mecánicos por el mismo bloqueo. No declarar percent complete desde cantidad de tests.

## No negociar
No Hostinger temprano, no API proveedor real sin autorización, no escribir a Mercado Libre, no reset de DB de usuario, no tocar ERP1/WIP, no merge/deploy implícito, no mover PR21 sin revisión, no duplicar motores o consumidores. Prompts existentes que contradigan esta priorización quedan HOLD hasta nueva auditoría del alcance.
