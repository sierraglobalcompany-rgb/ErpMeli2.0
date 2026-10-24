# ERP MELI 2.0 — Ley de ingeniería

Esta regla es obligatoria para cualquier persona, IA o agente que diseñe, programe, revise o modifique ERP MELI 2.0.

## Puerta KISS obligatoria

Antes de proponer o implementar cualquier cambio, responder internamente:

1. ¿Es KISS?
2. ¿Es simple?
3. ¿Está optimizado para el problema real?
4. ¿Es eficiente?
5. ¿Se puede mejorar usando menos piezas, menos estado, menos consultas o menos abstracciones?

Si alguna respuesta es no, dudosa o existe una solución claramente más sencilla, no implementar todavía: simplificar y volver a evaluar.

## Reducción de ruido

ERP MELI 2.0 no debe acumular código, archivos, caminos alternos, compatibilidad, historial técnico ni abstracciones que ya no aportan al sistema actual.

Antes de agregar o conservar una pieza:

1. Debe servir hoy para una necesidad real.
2. Debe evaluarse si cabe en una pieza existente.
3. Si duplica lógica, datos, flujo, documentación o responsabilidad, preferir fusionar.
4. Si fue reemplazada u obsoleta, eliminarla tras comprobar referencias, tests y contratos.
5. Git conserva el historial; no mantener código o documentos muertos "por si acaso".
6. Si una pieza no puede borrarse, debe demostrar utilidad actual, auditabilidad necesaria, compatibilidad real o rollback real.

## Regla anti-parches

- No parche sobre parche.
- Si una solución nueva sustituye a otra, revisar en el mismo cambio qué código, tests, configuración o documentación vieja puede retirarse.
- No crear archivos `legacy`, `old`, `v2`, `final`, `new` ni caminos paralelos como forma normal de evolución.
- La compatibilidad temporal debe tener una razón y una condición de eliminación.
- Una eliminación sólo es correcta cuando está respaldada por búsqueda de referencias, QA y revisión de contratos/datos.

## Orden de prioridades

Correcto > Simple > Estable > Mantenible > Eficiente > Escalable.

La escalabilidad futura no justifica complejidad presente sin evidencia.

## Reglas no negociables

- Reutilizar primero `Work`, `MeliClient`, MariaDB y módulos existentes.
- No crear motores, colas, estados, tablas, servicios, capas, dependencias ni abstracciones nuevas si la solución correcta cabe en lo existente.
- No implementar arquitectura especulativa.
- Un cambio pequeño debe producir un diff pequeño y una responsabilidad clara.
- La lógica de negocio no depende de timezone de PHP, MySQL o Hostinger.
- Fechas externas se normalizan determinísticamente desde la fecha fuente remota.
- `created_at`/`updated_at` nunca sustituyen la fecha fuente de Mercado Libre.
- Históricos no generan retries eternos.
- Ejecutar sin error no significa cobertura completa: debe demostrarse.
- Tests antes del GREEN: RED → fallo esperado → GREEN mínimo → QA → checkpoint.
- No mezclar refactors no relacionados.
- No usar float para dinero exacto.
- Remote writes permanecen OFF hasta F16 y autorización explícita.
- Desarrollo y QA normales usan `REAL_MELI_HTTP=0`; el comportamiento Mercado Libre se prueba localmente con el `MeliClient` existente, `MeliTransport` inyectado/fakes y MariaDB local.
- Hostinger, OAuth real ERP2 y canaries Mercado Libre READ-only pertenecen a certificación de preproducción F14 y no bloquean desarrollo local de módulos.
- Si una semántica externa no está documentada ni puede inferirse con seguridad, mantener fail-closed ese comportamiento específico en vez de inventarlo.

## Regla de eliminación

Ante dos soluciones igualmente correctas, elegir la que tenga menos código, menos estado, menos consultas, menos componentes, menos dependencias, menos pasos operativos y menos caminos alternos.

Pregunta final obligatoria:

> ¿Podemos resolverlo correctamente dejando el sistema con menos cosas que entender y mantener?

---

# HUMAN-FIRST UI/UX LAW — OBLIGATORIA

Para cualquier tarea que cambie una interfaz, experiencia visible o forma de interacción, antes de modificar código el agente DEBE leer:

```text
docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md
design-system/erp-meli-2-0/MASTER.md          # cuando exista
design-system/erp-meli-2-0/pages/<page>.md   # si existe override
docs/CURRENT_CHECKPOINT.md
```

Esta obligación se activa para cambios en:

```text
views/templates/components/pages
CSS/design tokens/theme
navigation/sidebar/topbar/bottom nav
forms/tables/lists/cards/charts
modals/drawers/dialogs
status/copy/empty/loading/error states
keyboard/focus/accessibility
responsive/mobile/tablet
information hierarchy/data density
cualquier workflow user-facing
```

Antes de implementar, el agente debe poder demostrar:

```text
HUMAN_FIRST_LAW_LOADED=YES
PROJECT_DESIGN_MASTER_LOADED=YES|NO
PAGE_OVERRIDE_LOADED=YES|NO|NOT_PRESENT
CURRENT_CHECKPOINT_LOADED=YES
TRUTH_OWNER_VERIFIED=YES
AUTHORIZATION_MODEL_VERIFIED=YES
TARGET_VIEWPORTS_DEFINED=YES
UI_TASK_SCOPE=<one microblock>
```

Si falta una autoridad necesaria, no empezar la implementación visual.

## Prioridad Human-First

```text
TRUTH
→ AUTHORIZATION
→ HUMAN COMPREHENSION
→ ACCESSIBILITY
→ INTERACTION
→ RESPONSIVE
→ VISUAL CONSISTENCY
→ POLISH
```

Nunca inventar rutas, datos, métricas, capabilities, permisos, stock, dinero o estados para satisfacer un mockup.

Reglas no negociables:

```text
UNKNOWN != 0
CONFLICT != 0
MISSING STOCK != 0
NO DATA != OK
NO INFINITE EXCEL
RAW MACHINE LANGUAGE IS SECONDARY
TECHNICAL DETAIL VIA PROGRESSIVE DISCLOSURE
MOBILE IS NOT SHRUNK DESKTOP
```

## Método UI obligatorio

```text
READ AUTHORITY
→ DEFINE HUMAN JOB
→ VERIFY TRUTH/ROLES
→ ONE MICROBLOCK
→ RED
→ MINIMAL GREEN
→ AUTOMATED QA
→ REAL BROWSER QA
→ HUMAN-FIRST AUDIT
→ CHECKPOINT
```

No abrir el siguiente bloque UI sin checkpoint.

## Green gate UI

Una interfaz no se declara GREEN sólo porque compila o porque los tests pasan. Según riesgo, debe demostrar:

```text
TRUTH=GREEN
AUTHORIZATION=GREEN
HUMAN_COMPREHENSION=GREEN
ACCESSIBILITY=GREEN
RESPONSIVE=GREEN
AUTOMATED_QA=GREEN
REAL_BROWSER_QA=GREEN
NO_INFINITE_EXCEL=GREEN
CHECKPOINT=PERSISTED
```

Si no pudo verificarse una categoría requerida:

```text
BLOCKED / NOT_CERTIFIED
```

## Anti-drift

Si este chat/agente conserva contexto anterior y contradice el repositorio:

```text
REPOSITORY AUTHORITY WINS
```

Si un prompt contiene branch/SHA/checkpoint viejo:

```text
STOP → RECOVER AUTHORITY → CONTINUE
```

La ley Human-First no deja de aplicar porque cambió el chat o el agente.

## L1 KISS + Ponytail
Antes de editar software, consulta `.agents/skills/kiss-ponytail/SKILL.md`: comprende el flujo/callers, elimina solo lo no requerido, reutiliza primero código existente, biblioteca estándar, capacidad nativa y dependencias actuales. Cambia lo mínimo completo preservando seguridad, datos, validaciones, UX, pruebas y L2–L6. `ponytail-audit` solo cuando se solicite/justifique. No confundir esta integración con el plugin global y sus hooks, que requieren instalación y confianza separadas.

## PingPongLaw L1–L6 — referencia canónica fijada

**Canon para consulta:** https://github.com/sierraglobalcompany-rgb/PingPongLaw/blob/84e961cadf482d234f4c0ee2ca60e8c7e9b388e8/docs/engineering/LEYES_IMPLEMENTACION_1_A_6.md
**Router de skills (fuente, no instalación local):** https://github.com/sierraglobalcompany-rgb/PingPongLaw/blob/84e961cadf482d234f4c0ee2ca60e8c7e9b388e8/.agents/skills/pingponglaw/SKILL.md

Al iniciar una tarea, comprobar la autoridad del repositorio y, si la fuente es accesible, leer realmente el canon fijado y seleccionar únicamente las skills pertinentes. Una URL no significa `SKILL_LOADED`, plugin instalado ni hooks habilitados; si la fuente no puede consultarse, declarar `CANON_READ=BLOCKED` y no fingir validación. La skill local `.agents/skills/kiss-ponytail/SKILL.md` se conserva como adaptador L1 para el trabajo offline, no como canon alternativo.

- **L1 — KISS + Ponytail:** escoger la solución mínima completa, reutilizando owners, plataforma y dependencias sin recortar seguridad, datos ni QA.
- **L2 — Antirruido:** comprobar equivalencia y consumidores antes de unir o retirar; conservar lo incierto.
- **L3 — Antiparches:** identificar causa raíz, corregir al dueño y comprobar regresiones; no apilar remedios.
- **L4 — Diseño humano:** prevalecen SPEC, mockup aprobado y reglas Human-First propias del ERP; las fuentes externas son consultivas.
- **L5 — Verificación independiente:** pruebas atribuibles al SHA/build y aceptación proporcionada al riesgo; QA del autor no equivale a verificador externo.
- **L6 — Antiping-pong:** ChatGPT investiga, decide e implementa lo accesible; Codex recibe solo un lote necesario por límite real, con pruebas y cierre.

**Protección del proyecto:** esta referencia no reemplaza las normas específicas existentes en las ramas activas ni altera políticas de diez pasadas, permisos, multiempresa, cron/Work, QA, seguridad, ramas, F14/F16 o la prohibición de escrituras remotas sin autorización. Si al integrar ramas aparece conflicto, conservar ambas instrucciones y reconciliarlo antes de merge; no sobrescribir el `AGENTS.md` de producto. No instalar ni activar hooks, plugins, CI o skills globales por esta referencia.

**Cierre por tarea:** registrar leyes aplicables, evidencias `PASS/FAIL/BLOCKED/NOT_RUN`, pendientes reales y `NEXT` único o `NINGUNO`. No lanzar auditorías forenses ni diez pasadas por rutina si no las exige la instrucción vigente o un riesgo demostrado.
