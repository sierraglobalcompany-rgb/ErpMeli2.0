# ERP MELI 2.0 — SEIS LEYES CANÓNICAS DE INGENIERÍA

**Versión documental:** 1.0 ERP2 · **Origen:** `LEYES_1_A_6_MAESTRO_UNIVERSAL_IMPLEMENTACION_V1_0.md` (usuario, 2026-10-24 UTC) · **Alcance:** únicamente ERP MELI 2.0.

Este es el **canon transversal** L1–L6 de ERP MELI 2.0. La entrada permanente del agente es `AGENTS.md`, que **obliga a consultar este archivo al inicio y al cierre de cada tarea**. Las reglas específicas preexistentes siguen vigentes y no son sustituidas: `AGENTS.md`, `docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md`, `design-system/erp-meli-2-0/MASTER.md`, los overrides de página existentes, SPEC y `docs/CURRENT_CHECKPOINT.md`. El canon no otorga permisos de GitHub ni de producción; el checkpoint actual no vive en este documento estable.

## Jerarquía, alcance y conflicto

Seguridad y autorizaciones del usuario > requisitos/contratos aprobados > diseños aprobados en su ámbito visual > realidad comprobada de código, Git y QA > reglas pertinentes del repositorio y este canon > guías auxiliares. La realidad observada no cancela un requisito nuevo. En conflicto, registrar `CONFLICTO_ID / fuentes / impacto`, detener **solo** la acción insegura o que necesita decisión, continuar tareas independientes y **no convertir una suposición en PASS**.

**ChatGPT:** dirección técnica, análisis, implementación y pruebas que permitan sus herramientas; decide objetivo, invariantes, diseño/alcance y necesidad real de delegación. **Codex/Antigravity:** ejecuta el lote local completo permitido, resuelve obstáculos técnicos recuperables y presenta evidencias; no define negocio, cambios de permisos o rediseños por su cuenta. **Ley 5:** el implementador no certifica independientemente su propio trabajo.

## L1 — KISS: simplicidad correcta, eficiente y optimizada

- Definir problema, usuario, resultado, contrato vigente y alternativa más sencilla suficiente antes de añadir piezas.
- Reutilizar módulos PHP/Slim y componentes existentes; nuevas capas, servicios, colas, tablas, dependencias, rutas o frameworks solo con necesidad demostrada.
- Optimizar el trayecto humano A→B y el código, pero **nunca a costa de autorización, seguridad, precisión, recuperación, observabilidad o QA necesarios**.
- Prueba de cierre: `objetivo → solución mínima elegida → alternativa considerada → complejidad justificada → resultados reales`. No refactor global especulativo por un defecto focal.

## L2 — Antirruido: conservar, unir o retirar solo con evidencia

- Inventariar piezas presuntamente repetidas y **consumidores** estáticos, dinámicos, integraciones, migraciones y uso manual. Falta de referencias encontradas ≠ código muerto.
- Clasificar `CONSERVAR / UNIR / SIMPLIFICAR / MEJORAR / RETIRAR_PROPUESTO / INCIERTO`; registrar impacto, riesgos, regresión y reversión.
- Proteger WIP, parches históricos únicos, evidencia, datos y respaldos antes de cualquier limpieza. Git remoto no equivale a clon local.
- Reusar canon, SPEC, README y estilos existentes; no crear múltiples copias finales, prompts redundantes, rutas paralelas ni UI decorativa/duplicada.
- Retirada con riesgo contractual o diseño aprobado: propuesta documentada, **sin borrar sin autorización**.

## L3 — Antiparches: causa raíz e integración

- Registrar el fallo real, rol, estado, SHA, esperado/observado y caso negativo; distinguir hipótesis de causa demostrada.
- Rastrear entrada→validación→controlador/servicio→datos→vista/efecto; corregir el propietario real en lugar de acumular parches CSS, flags eternos o pruebas blandas.
- Revisar consumidores y regresiones próximas. Pruebas RED auténticas antes de GREEN mínimo cuando proceda; jamás debilitar asserts, introducir skips oportunistas o esconder el síntoma.
- Documentar `defecto / evidencia / causa / diff integrado / focal+vecinos / rollback`.
- Guardas ERP2: dinero **sin float**; `company_id + account_id + external_order_id` para ventas; **sin cruces de empresas**; canceladas ≠ pagadas; no recrear problemas ERP1 de 429 Financial/Billing, FIFO/colas ni batching HTTP/gRPC no controlado.

## L4 — Human-First: diseño útil, fiel y verificable

**Autoridad especializada**: primero `docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md` y `design-system/erp-meli-2-0/MASTER.md`; override `design-system/erp-meli-2-0/pages/<page>.md` solo si existe; luego `docs/CURRENT_CHECKPOINT.md`. Estos documentos **no son copias a reemplazar**.

- Modos: `AUDITAR_EXISTENTE` (no codificar si solo se pidió auditar), `DISEÑAR_NUEVO`, `IMPLEMENTAR_APROBADO`, `REFINAR`.
- Prioridad `verdad → autorización → comprensión → accesibilidad → interacción → responsive → consistencia → acabado`. `UNKNOWN != 0`; inventario sin definir ≠ cero; sin datos ≠ correcto.
- Pantalla/control: `referencia aprobada y versión/viewport/estado, o SIN_APROBAR / rol / tarea / permiso / dato / validación / efecto / error / recuperación`.
- Reutilizar tokens, componentes, lenguaje light data-first; evaluar foco/teclado/lectores, barra fija, scroll, zoom, 390/430/tablet/desktop según riesgo.
- Comparar producto **renderizado** contra referencia pertinente y probar acciones reales; código, mockup o CI solos no prueban fidelidad visual.
- Anti UI Slop (`github/awesome-copilot/skills/anti-ui-slop`) y UI UX Pro Max (`nextlevelbuilder/ui-ux-pro-max-skill`) son **referencias opcionales** para fallos concretos, nunca sustituyen el diseño aprobado ni autorizan instalar programas.
- Un lote grande de Codex puede contener **varios microbloques UI con gates separados**; no saltar checkpoints Human-First. Catálogo interno `/catalog` ≠ catálogo público compartible.

## L5 — Verificación independiente: evidencias del objeto exacto

**Tres fuentes:** (A) promesa aprobada — SPEC, permisos, mockup dentro del alcance visual; (B) objeto real — rama, SHA, diff, build/entorno; (C) observación primaria — pruebas y capturas de producto real, acciones por rol, persistencia y regresión.

- **Gate de aptitud** del requisito/mockup: `APTO / AJUSTE_INTERNO / REDISEÑO_PROPUESTO / FUERA_DE_ALCANCE / RIESGO_BLOQUEANTE`.
- **Gate de entrega:** para cada requisito usar `PASS / FAIL / BLOCKED / NOT_RUN / NO_APLICA` con evidencia atribuible al **mismo SHA/build**. CI verde ≠ QA visual ni ejecución en entorno distinto.
- Dictamen por alcance: `APROBADO_FOCAL / RECHAZADO / BLOQUEADO_POR_EVIDENCIA / PARCIAL`. Sin prueba esencial no hay PASS. P0 seguridad/datos; P1 incumplimiento esencial; P2 detalle; P3 mejora.
- Preferir revisor distinto y pruebas propias. Si el mismo agente revisa dos veces, declarar `REVISION_ADVERSARIAL_INTERNA`, no independencia certificada. Codex reporta `QA_LOCAL_PASS` si corresponde pero **no se autoaprueba**.
- Evidencia mínima: `criterio / SHA-build / prueba ejecutada / resultado / ruta a evidencia / límites`; re-test cuando cambie el SHA.
- **Pruebas de DB destructivas:** `TestDatabase::reset()` puede hacer `DROP DATABASE`; ejecutar solo en MariaDB NUEVO y desechable, verificado por ID/hostname, loopback, sin bind mounts ni volúmenes compartidos; `APP_ENV=test`, `APP_URL=http://localhost`, `REAL_MELI_HTTP=0`. Nunca tocar DB de producción ni datos de clientes reales.

## L6 — Antipíng-pong: máximo avance útil por transferencia

- **Detector de ruido:** PP-01 delegar análisis que ChatGPT puede hacer; PP-02 prompts por microarchivo/botón; PP-03 repetir pruebas sin nueva hipótesis; PP-04 detenerse por error recuperable; PP-05 reenviar resultados sin auditarlos; PP-06 delegar decisiones de negocio/permisos/diseño; PP-07 bloquear todo por gate parcial; PP-08 crear reportes sin nueva decisión/evidencia.
- Antes de delegar, ChatGPT cierra: `OBJETIVO / HECHOS / ESTADO / ENFOQUE / INVARIANTES / NO_TOCAR / ACEPTACIÓN / PRUEBAS / RIESGOS / CAPACIDAD_LOCAL_FALTANTE`.
- Codex recibe **un lote coherente** de `preflight → diagnóstico → integración → pruebas focales/vecinos → browser si aplica → reporte/evidencia → cierre`. No un prompt por comando. Resuelve autónomamente fallos `A_RECUPERABLE`; aísla `B_PARCIAL`; detiene el subpaso `C_DECISION`; ante riesgo a WIP/secretos/producción, `STOP_GLOBAL`.
- Reintentos finitos por hipótesis nueva (orientación: máximo dos para misma causa). Sin bucles watch, task concurrente sobre clon compartido, suite total repetida sin cambio, ni nuevo prompt automático al devolver Codex.
- Entrega: informe Markdown final en carpeta de reportes **fuera del repo** (PASS/PARTIAL/FAIL), ZIP únicamente si existen evidencias físicas pertinentes y permitidas; ambos en la misma carpeta, integridad SHA-256, sin secretos. Ley 5 después de Codex. Un solo `NEXT` útil o `NINGUNO`.

## Inicio y cierre de **cada tarea** (ChatGPT/Codex)

**Inicio:** identificar `repo, branch, HEAD, worktree, estado WIP, permisos y NO_TOCAR`; leer `AGENTS.md` aplicables (incluidos overrides locales) y este canon (versión y secciones pertinentes). Para UI, leer autoridades especializadas. Registrar compacto `CANON_L1-L6=LEIDO|PARCIAL|NO_ACCESIBLE`, sin ritual de reproducir el manual. Si el canon no es accesible, no inventar carga.

**Cierre:** revalidar **L1–L6** contra el diff y las pruebas reales; reportar `código cambiado / SHA / estado árbol / focal+regresión+browser (PASS|FAIL|BLOCKED|NOT_RUN) / secretos+alcance / conflictos / quién puede aprobar / NEXT o NINGUNO`. Informes no autorizan publicación remota.

**Prohibiciones permanentes ERP2:** no modificar `main`, checkout histórico, otros proyectos, Financial/Billing/FIFO/Work/OAuth/MeliClient por tareas UI sin nuevo alcance; no `git reset --hard`, `git clean`, push/merge/deploy, datos productivos, API ML real ni elevar `REAL_MELI_HTTP=1` sin autorización expresa. Diferenciar commits locales A/B/C/D de remoto; un parche no equivale a implementación.

## Matriz mínima de auditoría de diez pasadas (no repetir suite diez veces)

P1 autoridad/repo/permisos · P2 KISS · P3 antirruido/consumidores · P4 causa raíz/regresión · P5 UX/mockup · P6 evidencia y aceptación · P7 autonomía y transferencias · P8 seguridad/WIP/DB/ML · P9 canon único/AGENTS/carga · P10 cierre/checkpoint/NEXT.

**Estado de instalación:** este archivo solo es efectivo en la raíz autorizada del proyecto cuando existe físicamente, es enlazado desde `AGENTS.md` y se verifica su lectura por el ejecutor. Preparar/copiar este Markdown en un ZIP no es instalarlo en Windows ni en GitHub.
