# PROMPT PARA CODEX LUNA — ERP MELI 2.0 / G1 CIERRE OPERATIVO ÚNICO

**TASK_ID:** `ERP2-G1-OPERATIVE-CLOSURE-ONE-BATCH-20261028`  
**MODO:** `LOCAL_ISOLATED_IMPLEMENT_QA_EVIDENCE` · **Una sola ejecución coherente, sin ping-pong.**  
**DIRIGE:** ChatGPT GPT-6. **EJECUTA:** Codex Luna únicamente en Windows/clon aislado.  
**AUTORIZACIÓN:** cambios de fuente exclusivamente en candidato local protegido; sin commit, push, PR, merge, deploy, Hostinger, OAuth real, llamadas HTTP reales a Mercado Libre, escrituras remotas ni alteración de worktrees ajenos.  
**IMPORTANTE:** G1 V2 **YA ESTÁ APLICADO**. No reenviar ni reaplicar `apply_g1.py`, migración 016, Payment Facts/M02/014/015 ni el paquete original. La suite previa 935/8255 es baseline PASS LOCAL de ese candidato, NO de este lote futuro.

## 1. Identidad y autoridad iniciales: leer una vez

- GitHub: `sierraglobalcompany-rgb/ErpMeli2.0`, PR #21 `OPEN / DRAFT / UNMERGED`, **HEAD** `83e5dda0a47d553bf79099ceec14290fcf564e8d`, **tree** `26fde37a0f14abed4b0cb7b47fa77c7499a47143`, base `36ad68b3a2f4b0f62a27d6863afb47972cf00eda`. Si cambia HEAD, conciliar delta ANTES de aplicar; sin rebase automático.
- **Candidato G1 final:** `C:\codex\ERP2-reports\ERP2-G1-HTTP-MANUAL-GLOBAL-PACE-20261028\candidate`, HEAD Git = PR21 pero working tree modificado, **17 tracked + 6 untracked** según cierre previo. Marcador `.ERP2_G1_ISOLATED_CANDIDATE` con texto `ERP2_G1_ISOLATED_ONLY`. **No** confundir HEAD limpio de Git con el candidato modificado.
- **WIP protegido:** `C:\codex\ERP2-reports\ERP2-L4-M03-M04-QA-GATE-REPAIR-20261023\repo`, HEAD `401034e8e3f8117a855bc274727649d37994050e`, ocho paths con hashes congelados en `ERP2_HANDOFF_MAESTRO_AUDITADO_POST_G1_20261028.md`. No reset/checkout/rebase/normalizar CRLF/copiar ni borrar. Conservar también original histórico y H7C sin tocar.
- **Plan maestro:** `https://github.com/sierraglobalcompany-rgb/ErpMeli2.0/blob/docs/erp2-cierre-20261028/docs/ERP2_PLAN_MAESTRO_CIERRE_PRODUCTO_20261028.md` con G1-A y G1-B. Leer del PR21 exacto: `AGENTS.md`, `docs/ERP2_AUTHORITY.md`, `docs/CURRENT_CHECKPOINT.md`; si se cambia UI, Human-First y `design-system/erp-meli-2-0/MASTER.md`. PR21 funcional y rama documental son independientes.
- **Evidence de cierre anterior:** informe SHA-256 `c3657164fbb3d81869ba76e3643046c0e386c69d6dd54543d8b35ba016bfedc6` y ZIP SHA-256 `62917f5f7e77462f23c821939d37fd3d9953801d541bdc4d8a3a6171baee3ab8` (28/28 archivos SHA). El stdout original reportó **935 tests / 8.255 aserciones / 1 skipped / 0 fails**. No repetir suites anteriores antes de modificar código.
- Objetivo irreversible: **manual y configurable** el intervalo HTTP 250–60000 ms, 2000 predeterminado; **sin autocalibración**, sin gRPC, sin batching de trabajos, sin segundo WorkRunner ni nuevas colas. Una página de Billing con muchos detalles = una llamada HTTP; un Work puede hacer varias llamadas físicas secuenciales. 429 remoto ≠ espera de pacing local.

## 2. Preflight de fuente final SIN volver a empezar G1

1. Confirmar ruta de candidato y SHA/tree del commit. Tomar manifest SHA-256 de TODOS los archivos modificados/untracked, baseline `git status --porcelain=v1`, `git diff --binary` con contenido de negocio saneado; incluir **los cinco cambios RED→GREEN** que faltaban en ZIP anterior. Comparar los **15 hashes G1/focal** con `candidate-g1-source-hashes.txt` del ZIP anterior y 9 hashes del baseline M02, sin exigir que coincidan después de aplicar ESTE lote.
2. Emitir un `PRECHANGE_SOURCE_BUNDLE.zip` solamente con: patch íntegro de tracked, bytes nuevos `.php`/`.sql` versionables, manifest hashes, mapa cambios y SHA/tree. NO incluir `.env`, credenciales, datos reales, archivos privados, tokens, cookies, dump DB, marcador aislado ni archivos WIP. Guardar dentro de la carpeta NUEVA de reporte G1-CLOSE y al final en evidencia final; no entregar un ZIP separado como un nuevo gate.
3. Auditoría de código directa antes de editar: `MeliClient`, `MeliHttpDispatchGate`, `MeliRateLimitException`, `ApiUsageRecorder`, `BillingC0Runtime`, `bin/billing-c0-smoke.php`, `bin/work.php`, `WorkExecution`, `WorkRunner`, `SystemSettingsController`/vista, `Routes.php`, pruebas asociadas. Confirmar la diferencia entre 429 verdadero y `meli_local_pacing`. **No refactorizar por estética**; registrar cualquier defecto de alta gravedad que no pueda corregirse sin cambiar arquitectura.
4. Registrar 8 WIP before con sus hashes existentes; no navegar dentro del WIP para ejecutar tests. La suite 935 previa sigue siendo referencia, no cobertura de cambios de este lote.

## 3. Implementación CONJUNTA y mínima, en este mismo candidato

**A. C0 Billing dentro del gate HTTP real.** `app/Modules/Billing/C0/BillingC0Runtime.php` actualmente crea `new MeliClient(...)` sin `dispatchGate` (PR21 líneas ~104). Reutilizar exactamente `MeliHttpDispatchGate` existente, con **PDO independiente**, misma identidad de lock por DB que `bin/work.php` y callback OAuth en `Routes.php`. Revisar/ajustar entrada `bin/billing-c0-smoke.php` y tests C0 para suministrar la dependencia sin acceso externo. **Fail closed** si C0 no puede crear/usar gate; nunca degradar al ritmo privado de instancia. No activar `BILLING_C0_REAL_HTTP=1`; `APP_ENV=test`, `REAL_MELI_HTTP=0`, transporte fake. Conservar READ-only y límite de páginas/cursor; prohibido realizar canary LIVE.

**B. Operación manual visible sin segundo motor.** En `Configuración del sistema` (o superficie operacional YA EXISTENTE) System Admin debe poder solicitar «Procesar ahora» y ver el estado humano. Reutilizar `WorkExecution::runManualOne` / `WorkRunner` / `work_items` y sus locks, tanto con automatización ON como OFF. **Prohibido** `shell_exec`/`exec`/PowerShell desde HTTP, spawn sin supervisión, cron nuevo, polling JS, triggers de múltiples Works, endpoints públicos, autoejecutar al renderizar GET y bloquear web hasta 45 s. Ruta POST con Session Admin activo + CSRF; validar estado. **Decisión arquitectónica:** preferir una orden manual persistente de **un solo uso** en la fila global de `system_settings` consumida por el `bin/work.php` existente en su siguiente tick CLI; si se precisa una columna mínima, hacer migración incremental `017` sin tabla adicional. El mismo runner debe ejecutar *a lo sumo un Work por solicitud manual*, sin doble procesamiento con automático ni pérdida silenciosa del pedido ante crash. El indicador debe decir «Solicitud registrada; será atendida en la próxima ejecución del worker» hasta observar completado; **no mostrar «procesado» al hacer POST**. Si el entorno local no tiene tick cron, indicar explícitamente «Worker aún no ejecutado», sin prometer ejecución inmediata. Añadir contrato idempotente de un trigger pendiente: clicks repetidos no multiplican ejecuciones. Probar interrupción/restablecimiento y definir semántica exacta de reintento; si no puede demostrarse sin complejidad indebida, conservar CLI y clasificar UI `BLOCKED` con causa precisa; no inventar otro engine.

**C. Contabilidad honesta de HTTP.** Inspeccionar `ApiUsageRecorder` + `MeliClient::request`. `api_usage_daily.requests` hoy cuenta respuestas procesadas y omite `send()` que arroja una excepción; no llamar a ese campo «intentos totales». Usar una sola ruta de contabilización por intento físico: si un `send` terminó sin respuesta, registrar `transport_error` de forma distinguible de 5xx y 429 y contar UNA vez, no duplicar el intento. Reutilizar `api_usage_daily`; un solo `ALTER` incremental 017 si hace falta una columna de `transport_errors` (sin crear tabla ni ledger). Un rechazo anterior a send por lock/cooldown/validación **no** debe contar como intento HTTP ni 429. Distinguir 
  - HTTP 429 recibido, 
  - error de transporte sin respuesta, 
  - rechazo local de pacing, 
  - éxito.
  Prueba con transporte fake que lance excepción: exactamente un intento, cero respuestas y cero 429; respuesta real 429: exactamente un intento/una respuesta/uno 429. Si DB recorder falla, registrar límite de exactitud honesto, no inventar entrega exactamente una vez de la red.

**D. Feedback real, no falsificable por query string.** En G1 V2 el valor `$apiCalibrationSaved` se derivaba de `?api_calibration_saved=1` en `SystemSettingsController::show()`: un GET forjado puede mostrar «guardada» sin haber efectuado POST. Sustituir por **flash de sesión de un uso** creado SOLO después de `updateApiInterval()` exitosa, consumido por el GET autorizado; mostrar `role=status` en parte alta visible de `/settings/system` sin necesidad de scroll en 390×844; el intervalo actual sigue visible y coincide con DB. Invalid POST / URL con `?api_calibration_saved=1` jamás muestra falso éxito. Reutilizar componentes CSS del Design Master; sin introducir JS extra ni duplicar avisos.

**E. Protección de la frontera HTTP compartida.** Validar dos procesos SO separados, no solo dos conexiones en un proceso; idéntico lock C0/CLI/web, intervalos reales con fake, pérdida de `GET_LOCK` antes de `send` = 0 solicitudes, defer local = 0 respuestas 429, lock liberado en finally. No implementar un ritmo adaptativo. No saltar el gate de C0, OAuth o Work. Mantener 429 remoto, `Retry-After`, cooldown por familia y `meli_local_pacing` separados. Si se detecta bypass real adicional, corregirlo dentro de esta frontera sin ampliar módulos ajenos.

**Presupuesto anti-ruido:** modificar owners existentes y como máximo una migración 017, tests focales en archivos existentes o 1 nuevo justificado. Prohibidas capas genéricas y un segundo worker/cron; eliminar cualquier rama muerta generada por el lote si se comprueba que no tiene consumidores.

## 4. Matriz de QA obligatoria — una ejecución, no cinco rondas

- **SQL:** MariaDB 11.4 desechable `tmpfs`, solo localhost. Instalar 001→017 fresca, ensayar 012→015→016→017 en DB sintética, checksum 014 incompatible fail-closed; sin DB del usuario ni tocar 016 ya aplicado al candidato. Guardar output y hashes de migración.
- **HTTP / concurrencia:** fake transport prueba solicitudes físicas cron/CLI y C0, exclusión real de **2 procesos OS**, pérdida lock, calendario de despachos, no ráfaga, 429 remoto vs local, reintento diferido, error de transporte contabilizado, C0 sin gate debe fallar sin enviar.
- **Operador:** con auto OFF y ON, POST manual CSRF, roles 403, token 419, duplicados, cero Work elegibles, status claro, recuperación tras kill/reinicio; nunca más de una unidad manual por trigger en la semántica declarada. Probar que botón no llama un shell ni lanza HTTP real.
- **UI:** 390×844 y 1440×900 (con prueba DOM/geometría del aviso arriba), zoom/foco y confirmación visible tras guardar; GET query `?api_calibration_saved=1` aislado no debe anunciar guardado. Mantener lectura del intervalo exacto. Capturas sin secretos. No repetir toda la H9 visual.
- **Negativas:** 1 Work puede causar varias GET secuenciales, pero jamás paralelismo físico oculto; 429 proveedor vs local; ninguna llamada API sin gate; contador por envíos, no por recursos; no cambiar Amount/Financial/Billing fiscal/Report monthly. `REAL_MELI_HTTP=0`, `REMOTE_WRITES=OFF` todo el tiempo.
- **QA focal** solo archivos afectados, suites Work/MeliClient/C0/Settings/UI y migraciones; después **UNA** suite final del candidato realmente modificado, Composer validate, PHPStan, lint y `git diff --check`. No repetir baseline 935 antes del delta ni ejecutar H9/H10/M02 históricos.
- **WIP:** 8/8 hashes antes/después y ausencia de alteración a otros checkouts. No commit/push/merge/deploy. No falsificar PASS si falta herramienta OS/browser: certificar solo el resto y reportar bloqueo independiente con evidencia concreta.

## 5. Evidencias y cierre

Un solo directorio NUEVO bajo `C:\codex\ERP2-reports\ERP2-G1-OPERATIVE-CLOSURE-20261028\`. Entregar únicamente: 
1. `FINAL_REPORT.md` con alcance, causales y dictamen de cada gate; referencias a code line/diff, exactos hashes antes/después, DB/QA/roles/concurrencia/UX, 429 y contadores, WIP. Distinguir **BASELINE_REUSED** de **QA_FINAL_THIS_TREE**.
2. `evidence.zip` sanitizado con `PRECHANGE_SOURCE_BUNDLE.zip`, patch final tracked, copia de todos los **archivos nuevos de producto/tests**, manifest SHA-256 (no incluir secretos, tokens, storage/private, dumps, sesiones, DB real, capturas con PII). Guardar stdout PHPUnit final completo, no solo resumen. Prueba de dos procesos OS y capturas focales. CRC y 100% hashes verificados.
3. `SHA256SUMS.txt` con hashes de `FINAL_REPORT.md` y `evidence.zip`.

**Contrato de salida:**
```
TASK_ID=ERP2-G1-OPERATIVE-CLOSURE-ONE-BATCH-20261028
G1_SOURCE_FINAL_AUDIT=PASS|PARTIAL|BLOCKED
C0_SHARED_HTTP_GATE=PASS_LOCAL|FAIL|BLOCKED
WEB_MANUAL_TRIGGER=PASS_LOCAL|FAIL|BLOCKED
WEB_MANUAL_SEMANTICS=ONE_PENDING_ASYNC_REQUEST|EXPLICIT_BLOCKER
CROSS_OS_PROCESS_GATE=PASS_LOCAL|FAIL|NOT_RUN
TRANSPORT_ERROR_ACCOUNTING=PASS_LOCAL|PARTIAL|BLOCKED
HTTP_PROVIDER_429_VS_LOCAL=PASS_LOCAL|FAIL
CALIBRATION_FLASH_GENUINE=PASS_LOCAL|FAIL
QA_FINAL_THIS_TREE=PASS|FAIL|NOT_RUN
WIP_8_OF_8=PASS|FAIL|BLOCKED
REAL_MELI_HTTP=0
REMOTE_WRITES=OFF
G1_LOCAL_COMPLETE=YES|NO
G1_RELEASE_CERTIFIED=NO
HOSTINGER_F14_F15_F16=NOT_RUN
GITHUB_WRITES=0
NEXT=CHATGPT_INDEPENDENT_AUDIT
STOP=YES
```
**Sin ping-pong:** hasta dos intentos técnicos por la misma causa ambiental. No pedir micro-prompts; resolver todos los cambios dentro de ESTE único lote. Si un riesgo grave impide una subparte, continuar los checks independientes, explicar por qué, no improvisar una cola o un motor nuevo. Si este prompt no está acompañado de los archivos de código local adecuados, **no pedir otra copia del paquete G1 V2**: el candidato está en Windows; verificar la ruta de origen y entregar evidencia.