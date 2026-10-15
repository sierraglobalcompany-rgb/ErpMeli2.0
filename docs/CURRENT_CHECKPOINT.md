# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=MELI APP SETTINGS UI GREEN CERTIFIED / RECOVERY CHECKPOINT / STOP**

> Este archivo es la autoridad operativa para recuperar el hilo si el chat se corta. Retomar desde `NEXT` y no reconstruir estado por intuición.

## 1. REPOSITORY / BRANCH AUTHORITY

| Campo | Estado |
|---|---|
| Fecha | 2026-10-15 |
| Repo | `sierraglobalcompany-rgb/ErpMeli2.0` |
| Stable | `impl/v3-b-sales-audit-20261010` |
| Stable SHA verificado por PR #18 | `d56183584840187e215e225942831f40ec9a04ae` |
| Rama UI | `ui/operator-shell-v1-20261014` |
| PR UI | `#18 feat(ui): modernize operator workspace` |
| PR #18 | OPEN / DRAFT / NOT MERGED / mergeable |
| Head antes de este checkpoint | `09e3b06c42d143b5369cfa76e3d3b99a55d636ce` |
| Source UI actualmente certificado | `aed0668fa99226917bbab918e63b3c8ddeb2166f` |
| Main histórico | NO tocar |
| Hostinger | ERP2 instalado; esta rama UI NO desplegada |
| Mercado Libre real | CERRADO |
| Remote writes | OFF |

No mergear PR #18 a stable ni desplegar sin autorización explícita del usuario.

## 2. LEYES OBLIGATORIAS

```text
KISS
- camino A→B
- simple, eficiente, optimizado
- sin capas, frameworks ni abstracciones innecesarias

ANTI-RUIDO
- un artefacto estándar por lote
- no duplicar CSS, navegación, handoffs o contratos
- eliminar sólo con evidencia; si no, unir/simplificar/optimizar

ANTI-PARCHE
- corregir el owner real / causa raíz
- no encadenar workarounds
```

Metodología por microbloque:

```text
un solo objetivo pequeño
→ RED esperado
→ GREEN mínimo
→ QA
→ checkpoint
→ STOP
```

## 3. PRODUCT / EXTERNAL GATES

Estado funcional previo preservado:

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
L10=GO sólo para certificación externa READ-only por microbloques
```

Hostinger / externos:

```text
ERP2 ya está instalado en Hostinger
live smoke funcional previo: login, Companies, Users, Configuration, Sales sin error reportado
la nueva rama UI NO está desplegada
F14-A pre-deploy BLOCKED_NOT_DEPLOYED quedó obsoleto porque ya existe instalación live
pero G8 HOSTINGER_REALITY sigue NO CERTIFIED hasta obtener evidencia real de:
- HOST_DISK_QUOTA
- HOST_DB_QUOTA
- HOST_INODE_QUOTA
- HOST_CRON_CAPACITY
- HOST_MIN_CRON_INTERVAL
- WEB_PHP_VERSION
- CLI_PHP_VERSION
- HTTPS_STATUS
- SUBDOMAIN_DOCUMENT_ROOT
F15 Cutover=PAUSED
F16 Remote Writes=UNAUTHORIZED
real MELI OAuth/API=CLOSED
```

## 4. UI-1 — WHAT IS ALREADY DONE

PR #18 incluye el shell visual compartido y presentación para:

```text
- navegación operador
- login
- ventas listado
- catálogo
- facturación
- empresas
- usuarios
- configuración del sistema
- selector de empresa
- instalador
- cuentas Mercado Libre
- configuración global de aplicación Mercado Libre
```

Además la rama UI parte del fix de guest root redirect previamente certificado:

```text
GET / para guest → 303 /login
sesión integer inválida/deshabilitada conserva validación de autoridad → 403
```

Ese fix tampoco está integrado todavía a stable/live.

## 5. INSTALLER IMPROVEMENTS ALREADY CERTIFIED

```text
DB_HOST default=localhost
DB_PORT default=3306
valores guardados por usuario conservan precedencia
APP_URL se autodetecta desde scheme://authority del request si está vacío
APP_URL sigue editable y no se persiste hasta continuar
installer visual shell modernizado sin cambiar lógica
```

QA relevantes:

```text
Actions #780=PASS PHP 8.3/8.4/8.5 (flake MELI test saneado)
Actions #783=PASS PHP 8.3/8.4/8.5 (APP_URL autodetect)
Actions #788=PASS PHP 8.3/8.4/8.5 (installer visual)
Actions #794=PASS PHP 8.3/8.4/8.5 (Cuentas Mercado Libre UI)
Actions #799=PASS PHP 8.3/8.4/8.5 (Configuración Mercado Libre UI)
```

## 6. CLOSED MICROBLOCK — /settings/mercadolibre

Objetivo bounded: modernizar únicamente `/settings/mercadolibre` sin cambiar comportamiento, OAuth, CSRF, secretos, persistencia ni autorización.

Owners visuales tocados:

```text
app/Modules/Settings/views/mercadolibre.php
public/assets/app.css
```

Test tocado:

```text
tests/Integration/MeliAppSettingsTest.php
```

### RED inicial

```text
87af4dbb28cf761e87e04b1461340a95380dc756
Actions #796
```

PHP 8.3 confirmó:

```text
776 tests / 1 failure
único fallo=MeliAppSettingsTest::testMeliSettingsUsesSharedOperatorVisualStructure
causa esperada=la vista antigua no cargaba /assets/app.css ni shell compartido
PHPStan=PASS
Syntax=PASS
```

Durante la revisión se detectó que exigir `app-nav` era arquitectónicamente incorrecto: `navigation.php` necesita contexto de empresa mientras `/settings/mercadolibre` es global de system admin. Forzar `app-nav` habría ampliado tenancy sólo por presentación.

### RED corregido KISS / anti-parche

```text
e317aac6053c753154bc3002bc4c0ce07cfbb64e
Actions #797
```

Contrato visual correcto:

```text
/assets/app.css
simple-page
simple-nav
page-header
settings-grid
panel
```

PHP 8.3 y 8.4 confirmaron FAIL esperado antes del GREEN.

### GREEN

```text
15c481b74d1631e8977d7f236b8e134a52c08d08  feat(ui): modernize MELI application settings
aed0668fa99226917bbab918e63b3c8ddeb2166f  style(ui): add shared settings layout primitives
```

Resultado:

```text
- elimina CSS inline legado de mercadolibre.php
- reutiliza /assets/app.css
- usa simple-page + simple-nav para contexto global
- page-header para explicación
- settings-grid para Estado / Guardar / Limpiar
- paneles compartidos
- settings-list para estado y callbacks
- settings-form para credenciales
- responsive móvil
```

NO se tocaron:

```text
MeliAppSettingsController
OAuth
CSRF
Client Secret behavior
runtime persistence
system-admin authorization
callbacks
rutas
API Mercado Libre real
```

## 7. CERTIFICATION

Commit certificado:

```text
aed0668fa99226917bbab918e63b3c8ddeb2166f
```

Actions:

```text
#799 / run_id 38756645337
PHP 8.3=PASS
PHP 8.4=PASS
PHP 8.5=PASS
```

El step completo `QA` terminó SUCCESS en las tres versiones, incluyendo sintaxis, PHPStan y PHPUnit.

`/settings/mercadolibre` queda **GREEN CERTIFIED**.

## 8. ONLY UI GAP REMAINING

```text
/sales/{id}
owner: app/Modules/Sales/ViewSales/views/detail.php
```

Ese es el único hueco visual identificado pendiente dentro del barrido UI actual.

## 9. GUARDRAILS

```text
no merge a stable todavía
no deploy UI todavía
no tocar Hostinger en el próximo microbloque
no OAuth real
no API Mercado Libre real
REAL_MELI_HTTP=0 para QA local/CI
remote writes=OFF
no F15/F16
no secretos en chat/Git/logs
no cambios de producto para resolver fallos de test claramente falsos/flaky
Codex sólo cuando haga falta Windows/runtime/DB/browser/hPanel/portal/evidencia que ChatGPT no pueda ejecutar
```

## 10. NEXT — EXACTLY ONE MICROBLOCK

Esperar un nuevo `continua`.

Después:

```text
1. Auditar únicamente `/sales/{id}` y su test existente.
2. Presentar diseño bounded corto.
3. No tocar código hasta aprobación.
4. Con aprobación: RED visual mínimo → GREEN → QA PHP 8.3/8.4/8.5 → checkpoint → STOP.
```

No abrir merge/deploy/Hostinger/F14/F15/F16 en el mismo bloque.
