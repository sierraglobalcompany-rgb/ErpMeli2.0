# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=UI-1 CONTENT SWEEP GREEN CERTIFIED / SALES DETAIL CLOSED / RECOVERY CHECKPOINT / STOP**

> Autoridad operativa para recuperar el hilo si el chat se corta. Retomar desde `NEXT` y no reconstruir estado por intuición.

## 1. REPOSITORY / BRANCH AUTHORITY

| Campo | Estado |
|---|---|
| Fecha | 2026-10-15 |
| Repo | `sierraglobalcompany-rgb/ErpMeli2.0` |
| Stable | `impl/v3-b-sales-audit-20261010` |
| Stable SHA | `d56183584840187e215e225942831f40ec9a04ae` |
| Rama UI | `ui/operator-shell-v1-20261014` |
| PR UI | `#18 feat(ui): modernize operator workspace` |
| PR #18 | OPEN / DRAFT / NOT MERGED |
| Source UI certificado actual | `60ba4013a4ff83c07012746b94b740e09b43ecce` |
| Hostinger | ERP2 instalado; rama UI NO desplegada |
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

Metodología:

```text
un solo microbloque
→ RED esperado
→ GREEN mínimo
→ QA
→ checkpoint
→ STOP
```

## 3. PRODUCT / EXTERNAL GATES

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
```

Hostinger / externos:

```text
ERP2 instalado live
smoke funcional previo: login, Companies, Users, Configuration, Sales sin error reportado
rama UI nueva NO desplegada
G8 HOSTINGER_REALITY=NO CERTIFIED hasta evidencia real de cuotas/cron/PHP/HTTPS/document root
F15 Cutover=PAUSED
F16 Remote Writes=UNAUTHORIZED
real MELI OAuth/API=CLOSED
```

## 4. UI-1 — CODE COVERAGE CURRENTLY CLOSED

PR #18 cubre ahora el barrido visual identificado de:

```text
- navegación operador
- login
- ventas listado
- detalle de venta
- catálogo
- facturación
- empresas
- usuarios
- configuración del sistema
- selector de empresa
- instalador
- cuentas Mercado Libre
- configuración global aplicación Mercado Libre
```

También contiene guest root redirect previamente certificado:

```text
GET / guest → 303 /login
sesión integer inválida/deshabilitada conserva authority validation → 403
```

Installer certificado:

```text
DB_HOST default=localhost
DB_PORT default=3306
APP_URL autodetect scheme://authority si vacío
valores guardados conservan precedencia
installer visual modernizado
```

QA relevantes:

```text
#780 PASS PHP 8.3/8.4/8.5
#783 PASS PHP 8.3/8.4/8.5
#788 PASS PHP 8.3/8.4/8.5
#794 PASS PHP 8.3/8.4/8.5
#799 PASS PHP 8.3/8.4/8.5
#807 PASS PHP 8.3/8.4/8.5
```

No se conocen huecos de contenido UI pendientes dentro del barrido estático actual. **Aún falta QA visual real en navegador antes de declarar UI-1 final y antes de merge/deploy.**

## 5. CLOSED MICROBLOCK — /sales/{id}

Owner:

```text
app/Modules/Sales/ViewSales/views/detail.php
```

Test usado:

```text
tests/Integration/SalesDetailHttpRouteTest.php
```

### Auditoría previa

Se comprobó que `detail.php` ya incluía `navigation.php` y, por esa vía, `/assets/app.css`.

Conclusión KISS:

```text
NO nuevo shell
NO nueva navegación
NO nuevo stylesheet
NO framework
sólo reestructurar el contenido existente con primitivas compartidas
```

### RED

Commit test-only:

```text
fc379760fd75641c88310d95601daf482ec4eb20
```

Actions:

```text
#804 / run_id 38759036418
PHP 8.3 confirmó RED exacto
776 tests / 1 failure
único fallo=SalesDetailHttpRouteTest::testOrderDetailIsVisibleOnlyInsideSelectedCompany
causa esperada=ausencia de class="page-header"
PHPStan=PASS
Syntax=PASS
```

Contrato visual útil:

```text
page-header
panel sales-summary
table-wrap
```

### GREEN inicial y regresión detectada

Commit inicial de presentación:

```text
35b385eacbbb0d0ba35dccb5e7f4fb9218c75b39
```

Actions `#805` NO certificó GREEN.

PHP 8.3 y 8.5 detectaron un único fallo funcional preexistente:

```text
InvoicePostSaleConsumerHttpRouteTest::testReviewIsPureReadAndDoesNotExposeArtifactDetailsOrAddControls
```

Causa raíz:

```text
el test funcional protege la forma exacta:
<section id="invoice-review">...</section>

el GREEN inicial añadió class="panel" al mismo <section>,
rompiendo el selector contractual aunque la lógica de invoice siguiera intacta.
```

No se modificó el test funcional de factura para hacerlo pasar.

### Corrección KISS / anti-parche

Test visual saneado para no exigir clases redundantes sobre los `section id` protegidos:

```text
53409b0c1f37392f60814ca8311883e36d20f017
```

Producto corregido preservando exactamente:

```text
<section id="reconciliation">
<section id="invoice-review">
```

Commit final de producto:

```text
60ba4013a4ff83c07012746b94b740e09b43ecce
```

No hizo falta CSS nuevo porque `app.css` ya estiliza `main > section`.

### Resultado visual final

```text
- page-header con retorno a Ventas + ID + explicación
- panel Resumen de venta
- estado como status-chip neutral
- lista clave/valor para cuenta/estado/total/fechas
- Productos dentro de table-wrap
- Reconciliación conserva scope, pack, cobertura, Billing y shipping
- tabla Billing dentro de table-wrap
- Factura/Postventa conserva readiness y sus cuatro campos protegidos
- aislamiento por empresa sigue intacto
```

No se tocaron:

```text
controllers
queries
reconciliación de negocio
Billing logic
invoice/post-sale logic
OAuth
MELI real
tenancy
rutas
CSS compartido
```

## 6. FINAL CERTIFICATION — SALES DETAIL

Source certificado:

```text
60ba4013a4ff83c07012746b94b740e09b43ecce
```

Actions:

```text
#807 / run_id 38760172783
PHP 8.3=PASS
PHP 8.4=PASS
PHP 8.5=PASS
```

El step completo `QA` terminó SUCCESS en las tres versiones, incluyendo sintaxis, PHPStan y PHPUnit.

`/sales/{id}` queda **GREEN CERTIFIED**.

## 7. GUARDRAILS

```text
no merge a stable todavía
no deploy UI todavía
REAL_MELI_HTTP=0 para QA local/CI
no OAuth/API MELI real
remote writes=OFF
no F15/F16
no secretos en chat/Git/logs
no tocar Hostinger sin microbloque explícito
Codex sólo para trabajo que ChatGPT no pueda ejecutar: Windows/runtime/DB/browser/hPanel/portal/evidencia
```

## 8. NEXT — EXACTLY ONE MICROBLOCK

Esperar nuevo `continua`.

Siguiente prioridad recomendada:

```text
AUDITORÍA ESTÁTICA FINAL PR #18
- revisar diff/archivos del PR completo contra stable
- buscar ruido, duplicación, parches, huecos o cambios funcionales accidentales
- verificar que el barrido UI está realmente completo
- NO merge
- NO deploy
- NO Hostinger
- NO MELI real
- actualizar checkpoint
- STOP
```

Después de esa auditoría, si queda limpia, el siguiente bloque separado será **QA visual real en navegador** antes de solicitar autorización de merge/deploy.
