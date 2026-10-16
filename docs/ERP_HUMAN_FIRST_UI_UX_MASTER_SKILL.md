# ERP HUMAN-FIRST UI/UX MASTER SKILL — V2.1 FINAL UNIVERSAL

**Estado:** MASTER LAW / REQUIRED FOR UI-UX WORK  
**Versión:** 2.1  
**Fecha:** 2026-10-16  
**Uso:** cualquier ERP, CRM, SaaS administrativo, backoffice, sistema operativo de negocio o consola interna  
**Objetivo:** que cualquier agente, chat, desarrollador o auditor pueda retomar el proyecto sin perder la ley UX/UI  
**Principio rector:** **el ERP no existe para mostrar todo lo que sabe la máquina; existe para ayudar a una persona a entender, decidir y actuar correctamente.**

---

# 1. PROPÓSITO

Este archivo es ley de trabajo para:

1. nuevas pantallas;
2. rediseños;
3. auditorías;
4. responsive;
5. accesibilidad;
6. navegación;
7. tablas, formularios, charts y dashboards;
8. copy/status presentation;
9. composición visual;
10. browser QA;
11. planes de mejoramiento;
12. continuidad entre chats/agentes.

No reemplaza la verdad funcional del backend.

La UI nunca puede inventar:

- datos;
- estados;
- permisos;
- rutas;
- totales;
- métricas;
- health scores;
- stock;
- rentabilidad;
- conciliaciones;
- relaciones;
- acciones.

La UI presenta de forma humana **lo que el sistema realmente sabe**.

---

# 2. AUTORIDAD EXTERNA

Fuente principal revisada:

```text
https://github.com/nextlevelbuilder/ui-ux-pro-max-skill
UPSTREAM_REVIEWED_SHA=56ea9cf174ed86786187daa9de0e6247cb0747e4
UPSTREAM_REVIEWED_DATE=2026-10-16
```

Archivos upstream de referencia:

```text
.claude/skills/ui-ux-pro-max/SKILL.md
.claude/skills/ui-ux-pro-max/references/quick-reference.md
.claude/skills/ui-ux-pro-max/references/pro-rules.md
README.md
```

El sitio `https://ui-ux-pro-max-skill.com/` es referencia secundaria. Si difiere del GitHub oficial:

```text
OFFICIAL_GITHUB > WEBSITE_TRANSLATION
```

Las reglas de este documento adaptadas a ERP pueden ser más estrictas que el upstream cuando buscan proteger truth, accesibilidad, seguridad, claridad humana o KISS.

---

# 3. JERARQUÍA DE AUTORIDAD

En cada proyecto:

```text
1. Seguridad / privacidad / requisitos legales
2. Contratos funcionales y verdad del backend
3. ERP HUMAN-FIRST UI/UX MASTER SKILL
4. design-system/<project>/MASTER.md
5. design-system/<project>/pages/<page>.md
6. Arquitectura / permisos / capability map
7. docs/CURRENT_CHECKPOINT.md
8. Tests del mismo HEAD
9. Mockups aprobados
10. UI UX Pro Max upstream como referencia complementaria
11. Preferencias visuales ad-hoc
```

Si hay conflicto:

```text
TRUTH + SECURITY + ACCESSIBILITY > VISUAL PREFERENCE
```

---

# 4. PERSISTENCIA OBLIGATORIA EN GITHUB

La ley no debe existir sólo en un chat.

Estructura mínima recomendada:

```text
/
├── AGENTS.md
├── docs/
│   ├── ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md
│   └── CURRENT_CHECKPOINT.md
├── design-system/
│   └── <project-slug>/
│       ├── MASTER.md
│       └── pages/
│           ├── dashboard.md
│           ├── sales.md
│           └── ...
└── .github/
    ├── pull_request_template.md
    └── skills/
        └── erp-human-first-ui-ux/
            └── SKILL.md
```

Regla:

```text
ONE CANONICAL MASTER
MANY THIN ADAPTERS
```

La autoridad canónica es este archivo. Los adaptadores sólo obligan al agente a leerlo.

---

# 5. ACTIVACIÓN OBLIGATORIA

Esta ley debe cargarse antes de cualquier tarea que cambie:

```text
page
view
template
component
CSS / SCSS / design tokens
theme
navigation
menu
sidebar
topbar
bottom nav
modal
drawer
dialog
form
table
list
card
chart
status
empty state
loading state
error state
copy
label
button
keyboard behavior
focus
responsive behavior
mobile behavior
accessibility
information hierarchy
data density
dashboard
user workflow
```

Aunque el ticket diga:

```text
"small CSS fix"
"rename status"
"add one column"
"just a modal"
```

la ley aplica.

No es necesaria para backend puro salvo que cambie la experiencia visible.

---

# 6. HANDSHAKE DE ACTIVACIÓN

Antes de modificar UI/UX, el agente debe poder afirmar:

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

Si falta autoridad requerida:

```text
DO NOT START UI IMPLEMENTATION
```

Resolver autoridad primero.

---

# 7. DEFINITION OF READY

Antes de diseñar una pantalla deben existir respuestas para:

```text
USER=
JOB_TO_BE_DONE=
TRUTH_OWNER=
AUTHORIZED_ROLES=
PRIMARY_ACTION=
PRIMARY_INFORMATION=
SECONDARY_INFORMATION=
TECHNICAL_INFORMATION=
EMPTY_STATE=
UNKNOWN_STATE=
CONFLICT_STATE=
ERROR_STATE=
LOADING_STATE=
TARGET_VIEWPORTS=
ACCESSIBILITY_TARGET=
RISK_TIER=
```

Si no están definidas, diseñar píxeles es prematuro.

---

# 8. LEY 1 — HUMAN FIRST

```text
PERSONA > MODELO DE DATOS
```

La pantalla debe hablar el idioma de la persona.

Evitar como lenguaje primario:

```text
UNKNOWN
CONFLICT
NONE
PACK_MEMBERSHIP_UNVERIFIED
foreign_key
sync_cursor
work_item
billing_detail
internal_id
raw status code
```

Ejemplo de traducción:

| Raw | Primario humano |
|---|---|
| `paid` | Pagada |
| `pending` | Pendiente |
| `UNKNOWN` | Sin información suficiente |
| `CONFLICT` | Requiere revisión |
| `NONE` | Sin novedad / No aplica / Sin registro |
| `PACK_MEMBERSHIP_UNVERIFIED` | Membresía del paquete sin verificar |
| `disabled` | Deshabilitada |

El raw puede existir en:

```text
Detalles técnicos
Diagnóstico
Auditoría
JSON
Debug
```

pero no debe obligar al usuario normal a traducirlo mentalmente.

---

# 9. LEY 2 — DECISION FIRST

Toda pantalla debe responder rápido:

```text
¿Qué estoy viendo?
¿Qué está pasando?
¿Hay algo que requiere atención?
¿Qué puedo hacer ahora?
```

Idealmente en 5–10 segundos.

Si para entender la página hay que leer decenas de filas, recorrer toda la pantalla o interpretar códigos, la página no está terminada.

---

# 10. LEY 3 — TRUTH BEFORE BEAUTY

```text
BELLO + FALSO = FAIL
FEO + CORRECTO = todavía necesita UX
BELLO + CORRECTO + COMPRENSIBLE = PASS
```

Reglas:

```text
UNKNOWN != 0
ABSENT != false
MISSING STOCK != 0 STOCK
CONFLICT != 0
NO DATA != "todo bien"
NOT CERTIFIED != certified
```

Nunca usar diseño para ocultar incertidumbre.

---

# 11. LEY 4 — PROGRESSIVE DISCLOSURE

Mostrar primero:

```text
resultado
estado
resumen
acción
```

Mostrar después, bajo demanda:

```text
evidencia
detalle
campos secundarios
códigos raw
diagnóstico
auditoría técnica
```

Herramientas válidas:

- `<details>`;
- drawer;
- modal;
- panel expandible;
- drill-down;
- página detalle;
- tooltip accesible;
- acordeón cuando proceda.

No ocultar información crítica. Ocultar complejidad secundaria.

---

# 12. LEY 5 — NO INFINITE EXCEL

Un ERP no debe convertirse en:

```text
tabla
tabla
tabla
tabla
```

Usar tabla cuando:

```text
ROW COMPARISON = PRIMARY TASK
```

No usar tabla por costumbre.

Una entidad individual suele necesitar:

```text
summary card
key/value facts
sections
timeline
compact records
status block
```

Si no hay filas:

```text
NO renderizar cabecera + columnas vacías
```

Mostrar estado vacío humano + explicación + acción útil si existe.

---

# 13. LEY 6 — ONE PAGE, ONE PRIMARY JOB

Cada página tiene un propósito dominante.

Ejemplos:

```text
Dashboard -> entender situación y prioridades
Ventas -> encontrar/revisar ventas
Detalle -> entender una entidad
Catálogo -> gestionar productos
Facturación -> gestionar periodos/proceso
Usuarios -> administrar acceso
```

Si una página intenta resolver muchos trabajos con igual jerarquía:

```text
SPLIT / GROUP / DISCLOSE
```

---

# 14. LEY 7 — ONE PRIMARY ACTION

Cada estado debe tener una acción principal evidente cuando corresponda.

Las acciones secundarias deben verse secundarias.

No crear cinco botones primarios equivalentes.

---

# 15. LEY 8 — HIERARCHY BEFORE DECORATION

La jerarquía se construye con:

1. posición;
2. tamaño;
3. espacio;
4. contraste;
5. tipografía;
6. agrupación;
7. color semántico;
8. iconografía.

No con más sombras, más bordes, más cajas o más colores.

---

# 16. LEY 9 — INFORMATION DENSITY HAS A BUDGET

Primera capa recomendada:

```text
1 propósito
1 acción primaria
3–6 hechos/resúmenes importantes
0–2 alertas prioritarias
1 bloque principal de trabajo
```

Cuando crece la densidad:

```text
GROUP
FILTER
PAGINATE
SUMMARIZE
DISCLOSE
DRILL DOWN
```

No resolver densidad reduciendo fuente o eliminando whitespace.

---

# 17. LEY 10 — TECHNICAL DETAIL IS SECONDARY

Ejemplo:

```text
Primario: Requiere revisión
Detalle técnico: CONFLICT / scope=pack / currency=CONFLICT
```

Se conserva la verdad, pero se presenta por capas.

---

# 18. LEY 11 — CONSISTENCY IS A FEATURE

Acciones equivalentes deben verse y comportarse igual.

No inventar un nuevo button/card/badge/modal/form/table para cada página.

---

# 19. LEY 12 — DESIGN TOKENS BEFORE PATCHES

Color, espacio, radio, sombra, tipografía y z-index deben salir de tokens.

Evitar hex y medidas arbitrarias repetidas por componente.

---

# 20. LEY 13 — NO PATCH CHAINS

Si la UI necesita:

```text
!important
media query para corregir otra media query
JS para arreglar CSS
override del override
pantalla móvil duplicada
```

parar y buscar raíz.

Una excepción técnica debe quedar justificada.

---

# 21. LEY 14 — NOISE MUST BE PROVEN

Todo elemento se clasifica:

```text
KEEP
MERGE
OPTIMIZE
SIMPLIFY
REMOVE
DEFER
```

No conservar sólo porque “ya estaba”. No borrar sólo porque se ve feo. Debe existir evidencia.

---

# 22. LEY 15 — MOBILE IS NOT SHRUNK DESKTOP

En móvil:

- priorizar contenido;
- convertir tablas primarias en cards/listas;
- reducir chrome;
- mantener touch targets;
- evitar scroll horizontal principal;
- mover funciones secundarias a launcher/disclosure;
- respetar safe areas.

---

# 23. LEY 16 — ACCESSIBILITY IS A BLOCKER CATEGORY

Baseline web recomendado:

```text
WCAG 2.2 AA
```

Mínimos prácticos:

```text
normal text contrast >= 4.5:1
large text contrast >= 3:1
meaningful non-text UI >= 3:1
visible focus
logical keyboard order
focus not obscured
semantic headings
form labels
accessible names
color not sole signal
zoom/text scaling usable
reduced-motion respected
```

Preferir HTML semántico sobre `div + role + JS custom` cuando exista control nativo.

---

# 24. LEY 17 — COLOR NEVER CARRIES MEANING ALONE

Siempre:

```text
color + texto
```

o:

```text
color + icono + texto
```

---

# 25. LEY 18 — EMPTY STATES ARE DESIGNED STATES

Deben explicar:

```text
qué falta
por qué puede estar vacío
qué puede hacer el usuario
```

No basta `No data`, `[]` o tabla vacía.

---

# 26. LEY 19 — ERROR STATES ARE PRODUCT STATES

Errores deben comunicar:

```text
qué pasó
qué se conserva
qué puede intentar
qué necesita soporte
```

Nunca SQL, stack traces o códigos internos al usuario final.

---

# 27. LEY 20 — EVIDENCE BEFORE GREEN

No cerrar UI sólo porque compila o se ve bonita.

Se requiere, según riesgo:

```text
automated QA
real browser QA
viewport QA
truth-state QA
accessibility/interactions
checkpoint
```

---

# 28. PRIORIDAD DE AUDITORÍA ERP

Orden real:

```text
0A Truth
0B Authorization
0C Human comprehension
1 Accessibility
2 Touch & Interaction
3 Performance
4 Style consistency
5 Layout & Responsive
6 Typography & Color
7 Animation
8 Forms & Feedback
9 Navigation
10 Charts & Data
```

Visual polish nunca puede preceder truth/security/accessibility.

---

# 29. PRODUCT PROFILE OBLIGATORIO

Cada ERP define:

```text
PRODUCT_NAME=
PRODUCT_TYPE=
PRIMARY_USERS=
SECONDARY_USERS=
PRIMARY_CONTEXT=
CRITICAL_TASKS=
DATA_DENSITY=
RISK_LEVEL=
BRAND_PERSONALITY=
STACK=
SUPPORTED_VIEWPORTS=
SUPPORTED_BROWSERS=
ACCESSIBILITY_TARGET=
LOCALE=
TIMEZONE=
```

Nunca asumir stack sin verificar repositorio.

---

# 30. DESIGN SYSTEM PERSISTENTE

Cada proyecto debe tener:

```text
design-system/<project-slug>/MASTER.md
design-system/<project-slug>/pages/<page>.md
```

Regla:

```text
MASTER = ley global visual
PAGE = sólo excepciones justificadas
```

No duplicar el Master completo por página.

---

# 31. UI UX PRO MAX — PROTOCOLO DE CONSULTA

Si UI UX Pro Max está accesible:

### Nueva página/proyecto/dirección global

Usar `--design-system`.

### Problema puntual

Usar un único dominio relevante:

```text
ux
style
color
typography
chart
icons
web
stack
```

### Implementación de stack

Detectar stack real y luego consultar `--stack <detected-stack>`.

### Contrato de búsqueda

```text
1 intención dominante
2–5 términos útiles
1 restricción útil
```

Si sale off-topic:

```text
retry once narrower
```

Si no hay match:

```text
NO inventar match
usar fallback general etiquetado como fallback
```

Nunca incluir secretos/PII innecesaria en consultas.

Las recomendaciones externas no anulan esta ley ni el repositorio.

---

# 32. COLOR — BASELINE ERP

Usar tokens semánticos.

Baseline adaptable:

```css
--color-primary: #2563EB;
--color-primary-soft: #EFF6FF;
--color-background: #F5F7FB;
--color-surface: #FFFFFF;
--color-surface-soft: #F8FAFC;
--color-text: #0F172A;
--color-text-muted: #64748B;
--color-border: #E2E8F0;
--color-success: #15803D;
--color-warning: #B45309;
--color-danger: #B91C1C;
--color-info: #2563EB;
```

Es baseline, no obligación de marca.

No convertir dashboards en arcoíris.

Dark mode es condicional: si no existe en producto, no inventarlo para satisfacer checklist.

---

# 33. TIPOGRAFÍA

Baseline:

```text
body ~16px
line-height 1.5–1.75
1–2 familias
jerarquía clara
micro text evitar <12px
```

Números comparables:

```css
font-variant-numeric: tabular-nums;
```

Preferir wrap a truncation cuando sea posible.

---

# 34. ESPACIADO

Ritmo recomendado:

```text
4 / 8 / 12 / 16 / 24 / 32 / 40 / 48 / 64
```

Evitar valores arbitrarios sin razón.

---

# 35. BORDES, RADIOS, SOMBRAS

ERP profesional:

```text
border subtle
radius input 8–12
radius card 12–16
radius sheet/modal 16–24
shadow soft
```

Evitar neumorphism o blur decorativo que degrade legibilidad.

---

# 36. ICONOGRAFÍA

- SVG/vector;
- una familia visual;
- stroke consistente;
- no emoji estructural;
- icon-only button con accessible name;
- decorative icon oculto al árbol a11y;
- official brand assets cuando aplique.

---

# 37. NAVIGATION LAW

Desktop ERP:

```text
sidebar persistente
active state
grupos semánticos
topbar ligero
```

Mobile:

```text
bottom nav <=5 destinos principales
resto -> Apps/Más/launcher
```

Drill-down profundo debe tener ruta de retorno clara.

---

# 38. ESTRUCTURA HUMANA DE PÁGINA

Orden recomendado:

```text
1. Contexto
2. Estado
3. Decisión/resumen
4. Acción principal
5. Hechos importantes
6. Trabajo operativo
7. Evidencia
8. Detalle técnico
```

No al revés.

---

# 39. DASHBOARD LAW

El dashboard responde:

```text
¿Qué pasó?
¿Qué requiere atención?
¿Qué debo hacer?
¿Cómo va el negocio?
```

Baseline:

```text
3–5 KPIs principales
1 trend/chart principal
1 área de atención
1 bloque operativo
1 bloque de estado/integraciones
```

No mostrar KPI sin provenance/meaning certificado.

---

# 40. LIST LAW

Una lista operativa debe facilitar:

```text
search
filter
sort
pagination
find
act
```

Antes de agregar columnas, evaluar filtros/drill-down.

Desktop: tabla válida si comparar filas es el trabajo central.

Mobile: cards/listas, no tabla horizontal primaria.

---

# 41. DETAIL LAW

Una página de detalle cuenta una historia:

```text
Resumen
Estado
Alertas
Hechos clave
Contenido operativo
Evidencia
Detalle técnico
```

Un solo registro no debe convertirse automáticamente en mini-Excel.

---

# 42. CATALOG / INVENTORY LAW

Si comparten owner/modelo, una sola superficie humana.

Separar visualmente:

```text
identidad
inventario
acciones
```

Regla:

```text
Sin definir != 0
```

---

# 43. BILLING / FINANCIAL LAW

Workflow-first:

```text
qué periodo
qué cuenta
qué estado
qué requiere atención
qué acción existe
```

Raw IDs/cursors bajo disclosure.

No inventar neto/rentabilidad/reconciliation.

---

# 44. ADMIN LAW

Admin debe usar:

```text
explicación
listado
acción
formulario
guardrail visible
confirmación
```

No raw ACL/JSON como primera capa.

---

# 45. TABLE LAW

Tabla sólo cuando comparación por columnas es tarea primaria.

Para muchas filas:

```text
filter
sort
pagination
search
saved views si evidencia lo justifica
virtualization sólo después de medir
```

No miles de filas por defecto.

---

# 46. CHART LAW

Chart sólo si mejora comprensión.

```text
trend -> line
comparison -> bar
proportion -> donut/pie sólo pocas categorías
distribution -> histogram
```

Siempre:

- leyenda/unidad;
- colores accesibles;
- no depender sólo de color;
- empty state;
- reduced motion;
- summary accesible.

---

# 47. FORM LAW

Todo input:

```text
label visible
helper si complejo
error junto al campo
loading
success/error feedback
```

No placeholder-only.

Acciones destructivas requieren confirmación específica.

Formularios complejos usan progressive disclosure.

---

# 48. FEEDBACK LAW

Después de una acción el usuario debe saber qué pasó.

Estados:

```text
loading
success
warning
failure
partial
```

No congelar controles sin explicación.

---

# 49. TOUCH / INTERACTION

Baseline ERP preferido en web táctil:

```text
>=44x44 CSS px
```

Native:

```text
iOS >=44pt
Android >=48dp
```

No depender de hover.

Tap/press debe tener feedback.

---

# 50. RESPONSIVE CONTRACT

Baseline reusable:

```text
<=767 mobile
768–1023 tablet
1024–1279 compact desktop
>=1280 desktop
```

Pero:

```text
project breakpoints > generic breakpoints
```

Viewport QA sugerida:

```text
390x844
430x932
768x1024
1024x768
1366x768
1440x900
1920x1080
```

Añadir small phone/landscape cuando el alcance lo requiera.

---

# 51. SAFE AREA LAW

Fixed UI debe respetar safe area y reservar espacio de contenido.

Nunca ocultar controles detrás de:

```text
notch
home indicator
bottom nav
sticky footer
keyboard
```

---

# 52. MOTION LAW

ERP baseline:

```text
motion low
```

Animar sólo cuando comunica feedback/state/spatial continuity.

Respetar `prefers-reduced-motion`.

Nunca bloquear interacción por animación.

---

# 53. PERFORMANCE UX

- reservar espacio;
- evitar CLS;
- lazy-load pesado;
- skeleton si espera >~1s cuando ayude;
- input feedback rápido;
- paginate/filter primero;
- virtualize sólo cuando volumen medido lo justifica.

No introducir arquitectura compleja por performance imaginaria.

---

# 54. ROLE-BASED INFORMATION ARCHITECTURE

Auditar por rol:

```text
navigation
routes
actions
data scope
empty states
copy
admin links
sensitive facts
```

No limitarse a ocultar botones.

No mostrar dead links a funcionalidades sin permiso.

---

# 55. SECURITY + PRIVACY UX

Least exposure:

```text
tokens -> never render
passwords -> never render
secret keys -> masked/presence-only
PII -> sólo cuando job/role lo exige
SQL/stack -> never user-facing
```

Acciones admin/destructivas deben mostrar scope e impacto.

---

# 56. MONEY / DATE / TIMEZONE / LOCALE

Definir:

```text
locale
currency
decimal precision
thousand separator
timezone
date format
time format
DST behavior
```

Nunca mezclar monedas silenciosamente ni asumir timezone.

Cuando importe la ambigüedad, mostrar código de moneda.

---

# 57. DATA FRESHNESS

Si los datos pueden estar stale, expresar con hechos reales:

```text
Última actualización
Pendiente de sincronizar
Sin información reciente
Última verificación
```

No inventar “live”, “healthy”, “100% synced”.

---

# 58. ASYNC JOBS

Procesos largos deben traducirse a estados humanos:

```text
Pendiente
En proceso
Completado
Falló
Requiere atención
Diferido
```

No exponer queue row IDs/retry counters como estado primario.

El usuario debe saber si puede salir, cuándo revisar y qué hacer si falla.

---

# 59. BULK ACTION LAW

Bulk actions requieren:

```text
explicit selection
selected count
scope clarity
confirmation destructive
partial success handling
safe retry semantics
```

Aclarar si selección aplica a página actual, resultados filtrados o selección manual.

---

# 60. SEARCH / FILTER LAW

Listas data-heavy deben preferir search/filter/sort/pagination antes que más columnas.

Filtros deben mostrar estado activo y ofrecer reset claro.

Saved views sólo cuando un workflow repetido lo justifica.

---

# 61. AUDIT LOG LAW

Historia/auditoría responde:

```text
quién
qué
cuándo
contexto
resultado
```

Raw JSON/payload bajo disclosure.

---

# 62. ALERT FATIGUE LAW

Un alert merece atención si es:

```text
actionable
time-sensitive
risk-relevant
```

No pintar medio dashboard de rojo/amarillo.

---

# 63. DESTRUCTIVE ACTION LAW

Requiere:

```text
verbo específico
scope específico
impacto
confirmación
feedback fallo
feedback éxito
```

Evitar “¿Estás seguro?” sin contexto.

---

# 64. KEYBOARD-FIRST ERP LAW

Desktop ERP debe ser operable por teclado:

```text
logical tab order
visible focus
Enter/Space semantics
Escape closes transient UI
focus return
no traps
```

Keyboard shortcuts son opcionales. Keyboard operability no.

---

# 65. ZOOM / TEXT SCALE

Verificar al menos:

```text
browser zoom 200%
text growth donde aplique
```

Critical controls/facts deben permanecer accesibles.

---

# 66. PRINT / EXPORT SEPARATION

```text
UI = decisión + operación
EXPORT = inspección masiva + conciliación + archivo
```

CSV/XLSX/PDF pueden contener exhaustividad sin convertir la pantalla principal en Excel infinito.

---

# 67. MULTI-TENANT / MULTI-COMPANY

Cuando aplique, hacer claro:

```text
empresa activa
cuenta activa
site/tenant
scope acción
scope filtros
```

No permitir que cambio de contexto muestre datos de otro tenant o mantenga una selección peligrosa.

---

# 68. DIRTY STATE / UNSAVED CHANGES

Formularios largos deben definir:

```text
dirty state
save state
validation state
navigation-away behavior
```

No perder cambios silenciosamente.

Autosave debe mostrar estado real de persistencia.

---

# 69. DESIGN QA MUST REVIEW COPY

Auditar:

```text
labels
headings
helper text
empty states
errors
success
confirmation
status translations
```

Layout bonito + lenguaje críptico = FAIL.

---

# 70. ANTI-PATTERNS ERP

Bloquear:

```text
infinite Excel
raw debug console
every field visible
empty tables
horizontal scroll mobile
20 nav items
all buttons primary
gray-on-gray
tiny labels
color-only status
raw codes primary
duplicate desktop/mobile backend views
random shadows/radii
raw hex everywhere
modal on modal
nested scroll hell
fixed bars covering content
chart decoration
KPI without provenance
UNKNOWN->0
```

---

# 71. WORKFLOW OFICIAL — AUDIT MODE

Cuando el usuario pide auditar:

```text
READ AUTHORITY
→ INVENTORY
→ REAL BROWSER when available
→ 10 PASSES
→ FINDINGS
→ ROOT CAUSES
→ PRIORITIES
→ PLAN
→ MICROBLOCK FIXES
```

No empezar arreglando sin conocer el sistema, salvo contención de security/blocker.

---

# 72. WORKFLOW OFICIAL — BUILD MODE

Nueva pantalla:

```text
READ AUTHORITY
→ DEFINE JOB
→ READ/GENERATE DESIGN SYSTEM
→ DEFINE STATES
→ DEFINE RESPONSIVE
→ RED
→ MINIMAL GREEN
→ AUTOMATED QA
→ REAL BROWSER
→ HUMAN AUDIT
→ CHECKPOINT
```

---

# 73. MICROBLOCK LAW

Para agentes/IA:

```text
ONE ROOT CAUSE
ONE MICROBLOCK
ONE RED
ONE GREEN
ONE QA
ONE CHECKPOINT
```

Checkpoint al cerrar microbloque y, para sesiones largas, aproximadamente cada 12 minutos de trabajo activo.

No depender del chat.

---

# 74. CHECKPOINT CONTRACT

Guardar:

```text
CURRENT
CLOSED
OPEN
BRANCH
HEAD
CERTIFIED_CODE_HEAD
CI_RUN
CI_RESULT
BROWSER_QA
FINDINGS
NEXT
FROZEN
NO_GO
RESUME_EXACTLY_FROM
```

---

# 75. HUMAN-FIRST BROWSER QA

Browser QA pregunta:

```text
¿en 5–10 segundos entiendo la pantalla?
¿qué requiere atención?
¿qué acción es primaria?
¿hay lenguaje máquina?
¿hay tabla innecesaria?
¿hay pared de información?
¿puedo ignorar detalle técnico?
¿hay overflow?
¿puedo usar teclado?
```

No basta `HTTP 200`.

---

# 76. AUDITORÍA DE 10 PASADAS

## Pass 1 — Truth / Permissions

```text
known/unknown/conflict
roles
scope
security
```

## Pass 2 — Human comprehension

```text
¿qué es?
¿qué pasa?
¿qué hago?
```

## Pass 3 — Information architecture

```text
job primary
navigation
grouping
drill-down
```

## Pass 4 — Density / Infinite Excel

```text
tables
columns
raw IDs
summary
disclosure
```

## Pass 5 — Visual system

```text
tokens
type
spacing
color
iconography
components
```

## Pass 6 — Accessibility

```text
contrast
keyboard
focus
labels
semantics
zoom
```

## Pass 7 — Interaction / Forms / Feedback

```text
states
errors
loading
confirmations
```

## Pass 8 — Responsive

```text
mobile
tablet
desktop
safe area
overflow
```

## Pass 9 — Data-heavy / Performance

```text
filter
sort
pagination
bulk
virtualization only if measured
```

## Pass 10 — Real browser / Cross-page consistency

```text
render
console
5xx
consistency
browser evidence
```

Cada pass devuelve:

```text
PASS_STATUS=GREEN|YELLOW|RED
FINDINGS=
EVIDENCE=
RECOMMENDED_ACTION=
```

---

# 77. FINDING CONTRACT

Cada hallazgo:

```text
ID
PAGE
SEVERITY
EVIDENCE
LAW
EXPECTED
ACTUAL
ROOT_CAUSE
ACTION
DISPOSITION
```

Severidad:

```text
BLOCKER
CRITICAL
MAJOR
MINOR
OBSERVATION
```

Disposition:

```text
KEEP
MERGE
OPTIMIZE
SIMPLIFY
REMOVE
DEFER
```

---

# 78. SCORECARD

Puntuar 0–5 con peso:

```text
Truth                    20
Human comprehension      20
Accessibility            15
Information architecture 10
Responsive               10
Interaction              10
Visual consistency        5
Forms/feedback            5
Data visualization        3
Polish                    2
```

Gate orientativo:

```text
>=90 candidate GREEN
80–89 improve
70–79 major improvement
<70 redesign
```

Pero:

```text
BLOCKER => NOT GREEN
```

---

# 79. PLAN DE MEJORAMIENTO

Orden:

```text
P0 truth/security/a11y blockers
P1 comprehension/navigation/density
P2 responsive/forms/data
P3 consistency/polish
P4 enhancements
```

Cada tarea:

```text
ID
ROOT_CAUSE
SCOPE
WHY
FILES/OWNER
RED
GREEN
QA
BROWSER
CHECKPOINT
DEPENDENCIES
```

No “redesign everything” sin evidencia.

---

# 80. DEFINITION OF DONE

Una UI está DONE sólo cuando:

```text
AUTHORITY_LOADED=YES
UX_CONTRACT_DEFINED=YES
TRUTH_VERIFIED=YES
IMPLEMENTATION_MINIMAL=YES
AUTOMATED_QA=GREEN
BROWSER_QA=GREEN
ACCESSIBILITY=GREEN
RESPONSIVE=GREEN
HUMAN_COMPREHENSION=GREEN
NO_INFINITE_EXCEL=GREEN
CHECKPOINT=PERSISTED
```

Si una categoría no pudo verificarse:

```text
BLOCKED / NOT_CERTIFIED
```

No llamarla GREEN silenciosamente.

---

# 81. UI CHANGE RISK TIERS

## T0 — Cosmetic

Spacing/type/color/icon alignment.

Requiere visual QA + accessibility regression check.

## T1 — Interaction

Modal/form/focus/navigation.

Requiere interaction tests + keyboard/browser QA.

## T2 — Truth presentation

Money/inventory/status/reconciliation/KPI.

Requiere truth verification + UNKNOWN/CONFLICT/0 tests + factual browser fixture.

## T3 — Security/Authorization

Role visibility/destructive/secrets/company switching.

Requiere authorization + negative-role + security + browser role QA.

Un tier superior incluye requisitos inferiores relevantes.

---

# 82. GITHUB PR GATE

Cualquier PR que cambie UI debe revisar:

```markdown
- [ ] Human-First Master Skill leída
- [ ] Project design MASTER leído
- [ ] Page override leído o confirmado ausente
- [ ] Truth owner verificado
- [ ] Permisos verificados
- [ ] Primary job/action definido
- [ ] Raw language no dominante
- [ ] No unnecessary/empty tables
- [ ] UNKNOWN != 0
- [ ] Responsive definido
- [ ] Keyboard/focus verificado
- [ ] Accessibility revisada
- [ ] No page horizontal scroll móvil
- [ ] Automated QA GREEN
- [ ] Real-browser QA GREEN
- [ ] Evidencia referenciada
- [ ] Checkpoint persistido
```

UI PR con gate incompleto:

```text
NOT READY FOR MERGE
```

---

# 83. EXCEPTION POLICY

Excepciones al Master viven en:

```text
design-system/<project>/pages/<page>.md
```

Y documentan:

```text
EXCEPTION_ID
LAW_OVERRIDDEN
REASON
USER_BENEFIT
RISK
EVIDENCE
OWNER
DATE
REVIEW_DATE
```

No excepciones silenciosas.

---

# 84. ANTI-DRIFT

Todo agente/chat debe comparar:

```text
Master version
Project design MASTER
Page override
Current checkpoint
Branch/HEAD
```

Si el chat contiene contexto viejo:

```text
REPOSITORY AUTHORITY WINS
```

Si un prompt usa SHA viejo:

```text
STOP
RECOVER AUTHORITY
```

---

# 85. AGENT ADAPTERS

Canónico:

```text
docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md
```

Adaptadores finos:

| Entorno | Adaptador |
|---|---|
| Codex/agents | `AGENTS.md` |
| GitHub Copilot Skill Mode | `.github/skills/erp-human-first-ui-ux/SKILL.md` |
| GitHub Copilot instructions | `.github/copilot-instructions.md` opcional |
| ChatGPT con repo | boot prompt + lectura del Master |
| ChatGPT sin repo | adjuntar Master |
| Otros | mecanismo equivalente |

No duplicar cinco Masters independientes.

---

# 86. NEW CHAT BOOT PROMPT

```text
Antes de trabajar en UI/UX:

1. Lee docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md.
2. Lee design-system/<project>/MASTER.md.
3. Lee override de página si existe.
4. Lee docs/CURRENT_CHECKPOINT.md.
5. Contrasta backend/rutas/capabilities/roles reales.
6. No inventes datos ni rutas.
7. Audita Human-First: decision-first, progressive disclosure, no infinite Excel.
8. Trabaja RED → GREEN → QA → browser → checkpoint.
9. No abras siguiente bloque sin checkpoint.
10. Truth/security/accessibility ganan ante conflicto visual.
```

---

# 87. HUMAN COMPREHENSION TEST

Sin explicación previa, una persona debe poder responder:

```text
1. ¿Qué página es?
2. ¿Qué está pasando?
3. ¿Qué requiere atención?
4. ¿Qué harías ahora?
5. ¿Dónde verías el detalle?
```

Si falla 2 o más:

```text
NOT HUMAN-FIRST
```

---

# 88. INFINITE EXCEL TEST

Señales de FAIL:

```text
>10 columnas primarias
varias tablas grandes sin jerarquía
sin resumen
sin filtros claros
sin drill-down
raw IDs dominantes
actions repetidas fila por fila
mobile horizontal scroll obligatorio
single record as table
empty tables
```

Remediar con:

```text
summary
filter
column reduction
group
card
detail page
disclosure
pagination
export
```

---

# 89. DENSITY / MOTION / VARIANCE DIALS

Definir por proyecto/página cuando ayude:

```text
DENSITY=1..10
MOTION=1..10
VARIANCE=1..10
```

ERP baseline orientativo:

```text
DENSITY dashboard <=6
DENSITY forms <=6
DENSITY admin <=7
DENSITY desktop tables <=8
DENSITY mobile <=5
MOTION=2..4
VARIANCE=2..5
```

No reducir fuente para fingir densidad.

---

# 90. DESIGN DEBT REGISTER

Opcional:

```text
docs/UX_DESIGN_DEBT.md
```

Campos:

```text
ID
PAGE
DEBT
SEVERITY
WHY_DEFERRED
OWNER
REVIEW_DATE
```

DEFERRED != FORGOTTEN.

---

# 91. FINAL DELIVERY REPORT

Cada fase UI cerrada reporta:

```text
PHASE
BRANCH
HEAD
CI_RUN
AUTOMATED_QA
BROWSER_QA
VIEWPORTS
HUMAN_FIRST
ACCESSIBILITY
TRUTH
ROLE_VISIBILITY
FINDINGS
KNOWN_LIMITATIONS
NEXT
CHECKPOINT
```

---

# 92. UPSTREAM REFRESH POLICY

Cuando UI UX Pro Max cambie:

```text
1. Fetch official upstream
2. Compare SKILL.md
3. Compare quick-reference.md
4. Compare pro-rules.md
5. Audit changes
6. Adopt only compatible improvements
7. Preserve ERP-specific laws
8. Increment MASTER_VERSION
9. Commit law
10. Update adapters only if activation contract changes
```

Registrar:

```text
MASTER_VERSION=
UPSTREAM_REVIEWED_SHA=
UPSTREAM_REVIEWED_DATE=
CHANGES_ADOPTED=
CHANGES_REJECTED=
WHY=
```

---

# 93. FAIL-CLOSED DESIGN GOVERNANCE

Si una UI task llega sin:

```text
truth owner
role model
page purpose
state model
```

no inventar.

Marcar:

```text
BLOCKED_BY_MISSING_PRODUCT_AUTHORITY
```

---

# 94. MANDATORY END RULE

Si el agente va a modificar UI y no puede demostrar que esta ley fue cargada:

```text
STOP
READ THE MASTER
THEN CONTINUE
```

Si el proyecto no contiene esta ley:

```text
PERSIST IT FIRST
```

Si no existe design system de proyecto:

```text
CREATE/AUDIT DESIGN CONTRACT BEFORE BROAD UI IMPLEMENTATION
```

Si una página contradice este Master:

```text
AUDIT
CLASSIFY
FIX ROOT CAUSE
OR DOCUMENT JUSTIFIED PAGE EXCEPTION
```

Esta ley no desaparece porque empezó un chat nuevo, cambió el agente o el backend devuelve más campos.

**Human comprehension is part of product correctness.**

---

# 95. MANTRA

```text
VERDAD
→ COMPRENSIÓN
→ DECISIÓN
→ ACCIÓN
→ DETALLE
```

Nunca:

```text
BASE DE DATOS
→ TABLA
→ MÁS TABLA
→ CÓDIGOS
→ CONFUSIÓN
```

**El ERP no existe para mostrar todo lo que la máquina sabe. Existe para ayudar a una persona a hacer bien su trabajo.**
