# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=MASTER PRODUCT DESIGN DISCOVERY COMPLETE / UI-1 FINAL QA FROZEN / AWAITING DESIGN APPROVAL / STOP**

> Autoridad operativa para recuperar el proyecto si el chat termina. No reconstruir por intuición. Retomar desde `NEXT`.

## 1. REPOSITORY AUTHORITY

```text
DATE=2026-10-15
REPO=sierraglobalcompany-rgb/ErpMeli2.0
STABLE_BRANCH=impl/v3-b-sales-audit-20261010
STABLE_SHA=d56183584840187e215e225942831f40ec9a04ae
UI_BRANCH=ui/operator-shell-v1-20261014
PR=18 feat(ui): modernize operator workspace
PR_STATE=OPEN / DRAFT / NOT MERGED / MERGEABLE
PR_HEAD_BEFORE_THIS_CHECKPOINT=34c2f4e69de3584134f7dd60d03e42fbc02023bc
LATEST_CERTIFIED_PRODUCT_UI_SOURCE=f9c7c0b00f17727dea749068d2a43e486fb1df91
LATEST_PRODUCT_UI_QA=Actions #814 PASS PHP 8.3/8.4/8.5
HOSTINGER_UI_DEPLOYED=NO
REAL_MELI=CLOSED
REMOTE_WRITES=OFF
```

No mergear ni desplegar PR #18 todavía.

## 2. LAWS

```text
KISS=A→B, simple, eficiente, optimizado, arquitectura mínima; diseño puede y debe ser premium.
ANTI_NOISE=eliminar sólo con evidencia; si no, unir, simplificar, optimizar o conservar.
ANTI_PATCH=corregir owner/causa raíz; no apilar workarounds.
```

Metodología:

```text
microbloque → QA/auditoría → checkpoint → STOP
```

## 3. PRODUCT / PHASE STATE

```text
LOCAL_PRODUCT_READY=YES
L1 Installer=CLOSED
L2 Companies=CLOSED
L3 Users/Roles/Memberships=CLOSED
L4 Meli App Settings=CLOSED funcionalmente
L5A Seller Identity=CLOSED
L5B Account Management=CLOSED
L6 OAuth/MLSIM=CLOSED local
L7 Account Lifecycle=CLOSED
L8 Human Local E2E=CLOSED
L9 RC/package=CLOSED
L10=GO sólo certificación externa READ-only por microbloques
F15 Cutover=PAUSED
F16 Remote Writes=UNAUTHORIZED
```

Historical functional RC:

```text
SOURCE=f05377cb17afc3971997f686102ddf29dfbe6bc0
FILE=ERP_MELI_2_0_RC_L9_f05377cb.zip
SHA256=0167DB7634FAD882DA8E4C5673C6E2C221FCC99B4B3A8BDC0DC544419B20A587
```

## 4. HOSTINGER / EXTERNAL

ERP2 ya fue instalado live; smoke previo reportado OK en login, Companies, Users, Configuration y Sales. La UI de PR #18 NO está desplegada.

G8 HOSTINGER_REALITY sigue NO CERTIFIED hasta evidencia de cuotas, cron, PHP web/CLI, HTTPS y document root.

```text
REAL_MELI OAuth/API=CLOSED
remote writes=OFF
F15=PAUSED
F16=UNAUTHORIZED
```

## 5. ERP1 → ERP2 DESIGN AUDIT FINDING

ERP1 (`sierraglobalcompany-rgb/ErpMeli`) posee una arquitectura de navegación mucho más amplia que la UI actual de ERP2. Su shell incluye sidebar, topbar, empresa, cuenta, rango de fechas, notificaciones, estado/safety, tabs contextuales y dashboard. Su navegación agrupa Ventas, Productos, Finanzas, Operación y Administración, con subflujos como sincronización, logística, atención al cliente, inventario, vinculación, reportes, conciliación, alertas e integraciones.

ERP2 UI actual expone principalmente:

```text
Sales
Catalog
Billing
MELI Accounts
Companies
Users
Settings
MELI App Settings
```

ERP2 ya contiene módulos backend adicionales como Financial, Inventory e Invoicing, pero la navegación actual no representa todavía un producto/UX completo.

### DECISION

PR #18 deja de considerarse `FINAL UI` y pasa a clasificarse como:

```text
UI-1 = VISUAL FOUNDATION / TECHNICAL PROTOTYPE
```

Se conserva como trabajo útil, pero no se certifica visualmente como diseño final ni se despliega antes de aprobar el Design Master.

El prompt Codex de browser QA generado anteriormente queda **FROZEN / DO NOT RUN YET**, porque certificar visualmente UI-1 ahora puede producir trabajo descartable.

## 6. MASTER DESIGN DIRECTION — PROPOSED, NOT YET APPROVED

La opción recomendada es NO copiar ERP1 y NO seguir parchando PR #18. Se propone diseñar primero una arquitectura de producto única y responsive, y luego adaptar lo ya construido.

### Target information architecture

```text
HOME / RESUMEN
- atención prioritaria
- ventas del periodo
- salud de sincronización
- cuentas MELI
- tareas/problemas reales

OPERACIÓN
- Ventas
- Detalle de venta
- Logística / packs / envíos (cuando el backend correspondiente esté disponible)
- Postventa / atención (cuando corresponda)

PRODUCTOS
- Catálogo
- Inventario
- Vinculación / consistencia (cuando corresponda)

FINANZAS
- Facturación
- Conciliación
- Rentabilidad/reportes cuando estén soportados

MERCADO LIBRE
- Centro de conexión
- Cuentas
- Estado OAuth
- Webhook
- Sincronización
- Salud API / problemas que requieren atención

ADMINISTRACIÓN
- Empresas
- Usuarios y permisos
- Configuración
- Sistema/diagnóstico sólo para administradores
```

### Device model

```text
DESKTOP: sidebar persistente + topbar contextual + tablas completas + drawers/panels.
TABLET: sidebar colapsable/rail + 1-2 columnas + tablas reducidas + touch-first.
MOBILE: composición propia; listas/cards operativas, bottom navigation de áreas principales, filtros en sheet/drawer; no tablas desktop comprimidas.
```

### Design system target

```text
one shell
one page header
one filter/action bar
one table/list family
one form family
one status system
one alert system
one drawer/modal pattern
one empty/loading/error pattern
one responsive rule-set
```

No framework frontend nuevo salvo evidencia fuerte de necesidad.

## 7. THREE APPROACHES CONSIDERED

```text
A. Seguir evolucionando PR #18 pantalla por pantalla
   + rápido a corto plazo
   - alto riesgo de volver a parchado/inconsistencia
   VERDICT=NO RECOMMENDED

B. Congelar PR #18 como foundation + aprobar Design Master + implementar por módulos
   + KISS, reutiliza trabajo, evita rehacer pantalla por pantalla
   + permite desktop/tablet/mobile coherentes
   VERDICT=RECOMMENDED

C. Portar ERP1 visualmente y luego limpiar
   + parte de una UI conocida
   - arrastra precisamente complejidad, ruido y navegación excesiva de ERP1
   VERDICT=REJECT
```

## 8. UI-1 CERTIFIED CODE HISTORY

```text
#780 PASS — installer DB defaults
#783 PASS — APP_URL autodetect
#788 PASS — installer visual shell
#794 PASS — MELI accounts UI
#799 PASS — MELI global settings UI
#807 PASS — sales detail UI
#814 PASS — stylesheet dedup
```

Estos PASS certifican comportamiento/código del bloque, no convierten el diseño en UX final.

## 9. ERP1 FAILURE MODES ERP2 MUST STILL AVOID

```text
429 storms
FIFO/HOL blocking
gRPC/batches/jobs desync
child/page job explosion
inline retries
```

Safeguards ERP2 ya cerrados no pueden debilitarse por el rediseño.

## 10. GUARDRAILS CURRENT

```text
NO merge PR #18
NO deploy UI
NO Hostinger mutation
NO real MELI OAuth/API yet
REAL_MELI_HTTP=0 in local work
remote writes=OFF
NO F15/F16
NO UI implementation until Design Master approved
NO browser visual certification of provisional UI yet
```

## 11. NEXT — EXACTLY ONE STAGE

Esperar aprobación/corrección del usuario sobre el **Master Design Direction**.

Si el usuario aprueba:

```text
1. escribir Design Master spec formal en docs/superpowers/specs/
2. incluir mapa completo de pantallas, IA, responsive PC/tablet/mobile, design system, flujo Centro Mercado Libre y criterios de aceptación
3. self-review de la spec
4. presentar spec al usuario
5. STOP para aprobación escrita
```

Sólo después de aprobar la spec se crea el implementation plan. No tocar código antes.
