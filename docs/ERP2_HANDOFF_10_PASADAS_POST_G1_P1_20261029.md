# ERP MELI 2.0 — HANDOFF MAESTRO / AUDITORÍA INDEPENDIENTE DE 10 PASADAS / POST G1-P1

**Corte:** 2026-10-29 (America/Bogota). **Proyecto:** `sierraglobalcompany-rgb/ErpMeli2.0`. **Modo:** checkpoint durable; este archivo NO es un prompt para volver a implementar G1, NO autoriza merge, push del candidato, deploy ni HTTP real. 

## 0. LEER PRIMERO AL ABRIR OTRO CHAT

1. **ChatGPT es director técnico y revisor**; Codex Luna ejecuta solo operaciones indispensables de la PC/clon Windows. Evitar ping-pong y microfases. No pedir nuevos reportes si una evidencia ya resuelve la decisión.
2. **Estado verdadero:** `G1_P1_SAFETY_QA=PASS_LOCAL_VERIFIED_EVIDENCE`; `G1_LOCAL_COMPLETE=NO`; `G1_RELEASE_CERTIFIED=NO`; `MANUAL_UNCERTAIN_REVIEW=BLOCKED_BUSINESS_DECISION`; `REAL_MELI_HTTP=0`; `REMOTE_WRITES=OFF`.
3. **No hay código G1 publicado en PR21/main.** El candidato funcional está SOLO en el clon local `C:\codex\ERP2-reports\ERP2-G1-HTTP-MANUAL-GLOBAL-PACE-20261028\candidate`, con modificaciones sin commit; no se dispone de una instancia actual de ese checkout en este chat. No confundir un patch verificado con aplicar código.
4. **PR #21 GitHub confirmado:** `OPEN`, `DRAFT`, `NOT_MERGED`; HEAD `83e5dda0a47d553bf79099ceec14290fcf564e8d`, tree `26fde37a0f14abed4b0cb7b47fa77c7499a47143`; base `36ad68b3a2f4b0f62a27d6863afb47972cf00eda`. Reconsultar antes de cualquier integración.
5. **Único bloqueo funcional deliberado:** `uncertain_review` de un Work manual sin prueba concluyente del resultado; no marcar `done`, reintentar ni resetear por intuición. El usuario debe aprobar el criterio de reconciliación de negocio antes de habilitar una acción administrativa de resolución o reanudar a través de esa barrera.
6. La suite completa **959 tests / 8.448 assertions / 1 skip / 0 failures / 0 errors** es la del árbol local G1-P1 final, registrada en log original e inspeccionada por ChatGPT. **No** es certificación de Hostinger, facturación real, cron instalado, UI de todo el producto o Mercado Libre live.
7. **NO ejecutar** los prompts antiguos `docs/NEXT_CODEX_G1.md`, el transformador G1 V2, el lote G1 operativo V2 ni el prompt G1-P1: ya fueron ejecutados/superados. No reconstruir desde cero. La prioridad es cerrar la decisión humana y continuar G2/G3 independientemente, sin saltarse los gates de seguridad.

## 1. LEYES CANÓNICAS Y AUTORIDAD

- Canon fijo de seis leyes: `https://github.com/sierraglobalcompany-rgb/PingPongLaw/blob/84e961cadf482d234f4c0ee2ca60e8c7e9b388e8/docs/engineering/LEYES_IMPLEMENTACION_1_A_6.md` (se consultó en esta auditoría); router `docs/SKILLS_ARQUITECTURA.md` del mismo commit.
- **L1 KISS/Ponytail:** diseño simple, suficiente, sin segunda cola, segundo sender, fanout o orquestador.
- **L2 Antirruido:** no acumular reports, históricos, duplicados ni cambiar reglas sin comprobar consumidores. Conservar evidencias hasta transición aprobada.
- **L3 Antiparches:** causa raíz, contratos, consumidores, tests y regresión; nada de apilar `if` para pasar tests.
- **L4 Human-First:** permisos, estado honesto, botón manual sin engaños, accesibilidad y viewport real; respeta mockup si existe.
- **L5 Evidencia:** separar `VERIFICADO` (inspección de archivo/log/hash), `REPORTADO` (Codex), `NO_VERIFICADO` (sin entorno real), y `DECISION_PENDIENTE`.
- **L6 Anti-ping-pong:** un cambio vertical y un único retorno; ChatGPT decide arquitectura y audita, Codex solo PC runtime. 6 leyes ≠ 9 skills ≠ 10 ejecuciones.
- El plan de cierre en rama `docs/erp2-cierre-20261028` se identifica como **PROPUESTA DOCUMENTAL**, no como una autorización implícita para fusionar o desplegar.

## 2. FUENTES Y SHA DE EVIDENCIA ACTUAL

**Subidos por usuario:** `FINAL_REPORT(9).md` y `evidence(10).zip` (tarea `ERP2-G1-P1-SAFETY-LIVENESS-HTTP-UX-ONCE-20261029`). Archivos físicos del entorno de esta auditoría: `/mnt/data/FINAL_REPORT(9).md`, `/mnt/data/evidence(10).zip`.

| Evidencia | SHA-256 / identidad | Verificación independiente |
|---|---|---|
| Informe final de Codex | `83b99b481974e6f3bb22dedf1b59ec2d8cc49e911a86df9671775d08f85703ac` | Hash calculado |
| ZIP evidencias G1-P1 | `3cd9f5dc032b3fb7c2d7b351d66c7959d5513b70c16b268ab1262100843f4db4` | ZIP CRC OK, manifest SHA 40/40 |
| Diff versionado completo de candidato local | `candidate-tracked.patch` SHA `4c24798f01bafe3faa6b29a7b06c6fdb253cf930d5e38468e47823e899cc6629` | Hash del manifest validado; diff examinado |
| Archivos untracked | Nueve rutas: marcador + gate PHP + migraciones 014/015/016/017 + tres tests | Contenidos presentes en ZIP; SHA del manifest validado |
| PHPUnit final de árbol local | `phpunit-final-tree-clean.log` | Log original muestra 959/8448/1 skip, 0 fail/error |
| PHPStan | `phpstan.log` | `[OK] No errors` |
| WIP histórico | `wip-final-verification.txt` | 8/8 hashes coincide SEGÚN snapshot firmado del ejecutor; no acceso directo a C: desde ChatGPT |
| UI final | cinco capturas viewport: 390×844 y 1440×900 | Imágenes inspeccionadas; estado incierto y flash guardado visibles |

**Límite:** verificación criptográfica del material entregado prueba integridad, no garantiza que el checkout de Windows continúe inmutable desde su captura. Para futuros cambios locales, tomar snapshot de los archivos tocados y comparar con este ZIP; no reiniciar QA base por rutina.

## 3. DIEZ PASADAS REALIZADAS (UNA AUDITORÍA, DIEZ PERSPECTIVAS)

**A01 — Procedencia / autoridad de GitHub: PASS con límite.** Consultados directamente PR21 y plan documental. PR21 OPEN Draft sin merge, HEAD `83e5dda...`. Código G1 no está en PR21; docs separadas. No existe autorización implícita de release.

**A02 — Integridad / inventario de evidencia: PASS.** ZIP 41 entradas en total, 40 ficheros listados en manifest SHA-256; `testzip` correcto. Diff binario del candidato incluye 30 rutas tracked; 9 untracked. Informe de Codex es fuente secundaria y se contrastó con logs, patch, tests y capturas.

**A03 — Work/FIFO, crash y duplicados: PASS local acotado.** `WorkExecution::runManualOne` y `runRequestedManual` rechazan `uncertain_review`, `WorkRunner` ejecuta `beforeRun` bajo lock, `WorkRepository::claimNext` valida `system_settings` bajo transacción y `recoverRunning` excluye Work asociado incierto. Casos negativos en `ManualWorkRequestTest` incluyen bypass CLI, runner directo, recuperación, proceso OS interrumpido. **Riesgo residual de disponibilidad:** el estado incierto detiene la cola global sin acción humana de resolución. Es una *decisión de seguridad intencional*, no un fallo silencioso; sin contrato del usuario se mantiene HOLD.

**A04 — HTTP 429 / cooldown / seguridad de despacho: PASS local acotado.** `MeliClient` registra 429 real y Retry-After/cooldown antes de intentar telemetría best-effort; el defer local se identifica como `meli_local_pacing` y status 0. Liberación fallida del lock POST-send conserva la excepción/respuesta original y deja diagnóstico; un 429 no provoca segundo `send` en pruebas. Lock global `MeliHttpDispatchGate` usa PDO dedicado/MariaDB `GET_LOCK`, aplica intervalo manual. **No probado:** 429/limitación real ni caída de red en producción; falta F14 autorizado.

**A05 — Calibrador y métricas: PASS local acotado.** Intervalo manual `api_min_interval_ms` 250–60000 ms, default técnico 2000 ms, guardado System Admin/CSRF; UI de prueba 750 ms persistido. Un intento registrado representa invocación a transport, no garantía de llegada al proveedor. Métrica de transport errors se añadió en migración 017; fallos de telemetría no deben alterar resultado de negocio; UI reconoce incompletitud. No es autocalibración, 429 no cambia el valor manual.

**A06 — Billing/Financial y verdad fiscal: NO CERTIFICADO LIVE.** El patch del candidato incluye Payment Facts/M02/migraciones 014/015 y C0 Billing con gate. Trabajo fiscal existente no debe tomar `API requests` como recursos ni confundir Work con GET. No se tocaron montos financieros en lote focal P1. La QA sintética no valida F14/F15/F16, billing-info READ de proveedor, cierre mensual real ni Hostinger. Mantener packs no facturables hasta F14.

**A07 — Datos/migración: PASS sintético reportado, producción no probada.** 016 añade intervalo y cursor de despacho en `system_settings`; 017 añade estado manual y `api_usage_daily.transport_errors`. Evidencia de MariaDB 11.4 efímera y migraciones fresh/upgrade/fail-closed en informe; no se conectó a DB real. La migración 014/015 del candidato es herencia, NO duplicar ni aplicar dos veces.

**A08 — UI/roles/viewport: PASS visual acotado.** Cinco capturas finales 390×844 y 1440×900 inspeccionadas. Flash visible tras POST, ausente tras reload según log; valor 750 ms; UI bloquea botón en `uncertain_review`, da ID/razón/UTC, sin overflow según Playwright. **P2 texto:** la frase fija “Se atenderá en la próxima ejecución” también aparece durante `uncertain_review`; es genérica y potencialmente confusa frente al aviso que dice cola en pausa. Corregir copy solo junto a futura acción UI, no abrir microfase.

**A09 — QA / WIP / ejecución: PASS local de evidencia, no release.** PHPUnit 959/8448/1 omitido, 0 fallos/errores; PHPStan PASS, Composer validate PASS, lint 32 archivos PASS, diff-check PASS reportados y logs presentes. Fallo intermedio por APP_URL de runtime navegador no es resultado final; log limpio final permite separar. WIP histórico 8/8 hashes invariables reportados. Ejecutor dice REAL_MELI_HTTP=0, REMOTE_WRITES=OFF, no commit/push/merge/deploy. No reproducidos todos los tests en ChatGPT por falta del mismo runtime/checkout.

**A10 — Plan/L1–L6/valor y cierre: CORREGIR RUMBO LIMITADO.** G1 ya cubre calibración, locks, manual request, C0, 429 y visual local; no ordenar otro barrido G1 ni redefinir sistema. La única operación de G1 funcionalmente bloqueada necesita decisión autorizada para reconciliación de Work incierto. El resto de fases G2–G4 puede analizarse/prepararse sin habilitar red/cron. La siguiente transferencia debe aportar funcionalidad o una decisión, no otro reporte repetido.

## 4. DICTAMEN TÉCNICO Y DECISIÓN HUMANA PENDIENTE

**Aceptación independiente acotada:** `G1_P1_SAFETY_DELTA=PASS_EVIDENCE` y `G1_UI_FINAL=PASS_EVIDENCE`; `G1_LOCAL_COMPLETE=NO`, `G1_RELEASE_CERTIFIED=NO` por `MANUAL_UNCERTAIN_REVIEW=BLOCKED_DECISION` y gates de integración/deploy sin ejecutar.

**Problema único:** el Work manual `uncertain_review` no tiene una transición operativa aprobada. La seguridad actual congela el worker para no repetir una operación de resultado desconocido. Jamás autorizar `force done`, borrar/retirar Work, reintentar, hacer reset o avanzar cola ignorando la incertidumbre. Esto puede afectar disponibilidad global; no ocultarlo.

**Propuesta de contrato para decisión del usuario (NO APROBADA):**
- Precondición: el administrador revisa work_id, claim identity, estado Work canónico, logs sanitizados, registros del pedido, hechos persistidos y posible resultado remoto read-only. Se genera evidencia de reconciliación ligada a actor/fecha, sin exponer secretos ni alterar el resultado remoto.
- Si se demuestra transacción y resultado en `done` verificable, una futura acción puede **reconciliar estado de solicitud manual** con ese Work *sin repetir su llamada ni crear otro*. Si se demuestra que no hubo despacho/efecto, se propone una transición segura con pruebas.
- Si sigue indeterminado, **mantener `uncertain_review` y HOLD**. Una decisión adicional para liberar Works no relacionados requeriría prueba de aislamiento por Work/tenant/cuenta, FIFO y seguridad fiscal; no habilitar por conveniencia.
- No diseñar ni enviar implementación hasta obtener aprobación sobre este comportamiento. La propuesta no implica acceso a datos reales ni uso de Mercado Libre.

## 5. ROADMAP ERP2 (NO CAMBIAR EL DESTINO)

- **G0:** base local Payment Facts/M02, migraciones 014/015; reconciliada para el candidato, NO versionada en PR21. No rehacer.
- **G1:** Work/FIFO/HTTP calibrador manual, 429, C0, Web manual, QA y UX local PASS acotado. Pendiente: decisión de Work incierto, integración/repositorio + cron/Hostinger/live gates posteriores autorizados.
- **G2:** paridad de catálogo/listings/inventario, empresas y cuentas ML; M02 preview solo sintético/test, no afirmar producción. Preparación independiente permitida sin tráfico real.
- **G3:** Billing/Financial, hechos de pago, cuotas/fees, reporte mensual y certificación F14; NO afirmar reportes facturables o proveedores reales validados antes de lectura externa aprobada. Mantener semántica de recursos vs HTTP y 429.
- **G4:** Human-First / mockups / roles / responsive del **producto completo**, no solamente formulario G1.
- **G5:** integrar candidato seguro y coherente después de autoridad, diff, WIP, migraciones y QA del SHA de integración; no fusionar por inercia.
- **G6:** Hostinger, cron, Mercado Libre READ autorizado, F14/F15/F16, canary y release. No real HTTP hasta aprobación explícita. Escrituras remotas OFF.

**Riesgos heredados ERP1 a no repetir:** errores 429 por saturación, colas FIFO descalibradas, trabajo en batch/fanout/grpc interno, tiempos de espera excesivos, escalado automático oculto, confusión requests/Work/recursos, persistencia fiscal parcial. Todo despacho Mercado Libre centralizado en HTTP y calibración por administrador sin autoadaptación; Retry-After/cooldown automático es protección, no ajuste del calibrador.

## 6. PRESERVACIÓN DE LOS OCHO WIP HISTÓRICOS (REPORTADO; INMUTABLE)

Checkout histórico protegido: `C:\codex\ERP2-reports\ERP2-L4-M03-M04-QA-GATE-REPAIR-20261023\repo`, HEAD `401034e8e3f8117a855bc274727649d37994050e`. SHA-256 verificados por ejecutor pre/post:

| Ruta | SHA-256 |
|---|---|
| `app/Core/Http/views/navigation.php` | `6718c055ae708ba321e55a36c21a354f892d476deb77a2c1c9a4db38b0cf62c1` |
| `app/Modules/Catalog/views/catalog.php` | `ad9d8cbf0566d701db3c241e0633487ff368a429d9b5eb7d72da7ffdb9ceaae4` |
| `tests/Integration/CatalogDesignMasterUiTest.php` | `a33ac842208e6d1214c3f7bdc28c1db2c48655326f15bbfa106f2eed0e812753` |
| `tests/Integration/MeliListingReadScopeTest.php` | `f701f13af62f14864da8093ad609f24bc89a91751e4f56827b4ce432ef48f3a5` |
| `app/Integrations/MercadoLibre/Transport/M02CanaryReadOnlyTransport.php` | `26cc09162bb58f237bcd705420bca23ebe5b10b61a81b85e87f5da6480b45ef2` |
| `app/Modules/Settings/M02F14CanaryReader.php` | `9ca86f7497e3a8db7a73f645ae53c25c90ba0d583b9a4630d08d66d7e885f2a1` |
| `tests/Integration/M02F14CanaryReaderTest.php` | `ff07e1028eae63657fab187e3d6b1a3f4cd299de5c7db259da56f43cce4c5636` |
| `tests/Support/M02CanaryReadOnlyTransport.php` | `5fb64cea707ab74526d04fa1db137494f96e537090c33a361ba5a13fddb239f4` |

NUNCA `reset --hard`, `git clean -fd`, force-push o reemplazo de este checkout. Sin acceso directo desde este chat para comprobar estado **actual posterior a Codex**; hashes provienen de su evidencia final.

## 7. UBICACIONES, DOCUMENTOS Y ESTADO DE PROMPTS

- **Repositorio funcional:** `https://github.com/sierraglobalcompany-rgb/ErpMeli2.0`, PR21 draft base operativa, todavía SIN cambios G1 integrados.
- **Rama documental de continuidad:** `docs/erp2-cierre-20261028`.
- **Plan maestro documental:** `docs/ERP2_PLAN_MAESTRO_CIERRE_PRODUCTO_20261028.md`, sección G1-E tras actualización. Plan marcado PROPUESTO, no permiso de deploy.
- **Handoff vigente:** `docs/ERP2_HANDOFF_10_PASADAS_POST_G1_P1_20261029.md` (este contenido).
- **Históricos / NO REEJECUTAR:** `docs/NEXT_CODEX_G1.md`, `ERP2_PROMPT_CODEX_LUNA_G1_CIERRE_OPERATIVO_AUDITADO_DOBLE_V2_20261028.md`, `ERP2_DICTAMEN_REVISOR_INDEPENDIENTE_PROMPT_UNICO_G1_P1_V2_20261029.md` y G1 V2 apply. Su propósito ya se agotó; conservarlos solo por trazabilidad donde existen.
- **Evidencia de origen** para reproducir diff final: el ZIP adjuntado por usuario a este chat; puede requerirse adjuntarlo a un chat nuevo si se pide inspección línea a línea. No confundir archivo privado adjunto con un recurso disponible automáticamente en GitHub; el plan remoto documenta hashes y resultados.

## 8. PROTOCOLO DE REANUDACIÓN EN UN CHAT NUEVO

1. Leer este handoff completo + plan maestro documental y canon L1–L6 (según necesidad), **no** los prompts obsoletos.
2. Reconsultar GitHub PR21, `main`, rama documental y permisos; comprobar diferencias desde este checkpoint antes de cambios. No asumir que el checkout local C: está montado en ChatGPT.
3. Clasificar hechos VERIFICADO vs REPORTADO vs NO_VERIFICADO. Reutilizar QA G1-P1 959 tests si el código/sha/candidato permanece idéntico.
4. Priorizar **decisión humana de reconciliación** del Work incierto; no hacer una implementación de resolución sin contrato. Mientras tanto, puede avanzar planificación/implementación aislada de G2/G3 que no dependa de desbloquear G1, respetando WIP.
5. Al decidir la política, ChatGPT prepara UNA solución sencilla e integral con tests negativos de duplicados, FIFO/tenant/datos y una única QA del árbol modificado. Codex para pruebas/implementación locales indispensables; ChatGPT audita el retorno.
6. F14/F15/F16, real MELI, cron, Hostinger y release solo con autorización separada. Nunca convertir una suite simulada verde en certificación de proveedor.

**Checkpoint final:** `RELEASE=HOLD`, `G1_P1_SECURITY_TESTS=PASS_LOCAL`, `G1_MANUAL_UNCERTAIN_REVIEW=NEEDS_BUSINESS_DECISION`, `REMOTE_PR21=UNMERGED`, `NEXT_ACTOR=CHATGPT_FOR_PRODUCT_DECISION_AND_ROADMAP`, `CODEX_NEXT=NONE_UNTIL_APPROVED_CONTRACT`.