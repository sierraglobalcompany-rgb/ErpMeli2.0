# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=SALES DETAIL UI AUDITED / DESIGN PENDING APPROVAL / RECOVERY CHECKPOINT / STOP**

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
| Source UI certificado | `aed0668fa99226917bbab918e63b3c8ddeb2166f` |
| Checkpoint previo | `eb59ba2444bec228dd1650db933d9b68cec2b93f` |
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

## 4. UI-1 — CERTIFIED SO FAR

PR #18 ya cubre:

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
- configuración global aplicación Mercado Libre
```

También contiene guest root redirect previamente certificado:

```text
GET / guest → 303 /login
sesión integer inválida/deshabilitada conserva authority validation → 403
```

Installer ya certificado:

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
```

Último bloque cerrado:

```text
/settings/mercadolibre = GREEN CERTIFIED
source = aed0668fa99226917bbab918e63b3c8ddeb2166f
Actions #799 / run_id 38756645337
PHP 8.3=PASS
PHP 8.4=PASS
PHP 8.5=PASS
```

## 5. CURRENT AUDIT — /sales/{id}

Owner:

```text
app/Modules/Sales/ViewSales/views/detail.php
```

Test funcional existente:

```text
tests/Integration/SalesDetailHttpRouteTest.php
```

Hallazgo principal:

```text
detail.php YA incluye navigation.php
navigation.php YA carga /assets/app.css
por tanto NO hace falta otro shell, stylesheet ni navegación paralela
el hueco es únicamente estructural/presentacional dentro de <main>
```

Datos/contratos visibles que deben preservarse exactamente:

```text
- ID de venta
- cuenta seller
- estado
- total + moneda
- fecha creada / actualizada
- productos: título, SKU, cantidad, precio
- reconciliación: scope, pack, order_ids
- comisión operativa y cobertura
- item_count / currency
- billing identity
- receiver shipping / seller shipping
- official reference
- hechos facturados Billing
- evidencia envío receptor
- factura/postventa readiness
- factura registrada
- nota crédito registrada
- post_sale_status
```

El test actual ya protege:

```text
- venta de empresa seleccionada = 200
- ID / producto / SKU visibles
- datos de otra empresa ausentes
- venta de otra empresa = 404
```

No existe todavía contrato visual específico para detail.php.

## 6. DESIGN BOUNDED PROPUESTO — PENDIENTE DE APROBACIÓN

Sin cambiar lógica ni datos:

```text
1. page-header
   - link ← Ventas
   - título Venta {external_order_id}
   - estado como status-chip neutral

2. panel Resumen de venta
   - Cuenta
   - Estado
   - Total
   - Creada
   - Actualizada
   - reutilizar lista clave/valor existente; no crear tarjetas complejas

3. panel Productos
   - table-wrap alrededor de la tabla existente
   - mismas columnas y valores

4. panel Reconciliación
   - conservar id="reconciliation"
   - agrupar scope/cobertura/operación legiblemente
   - hechos Billing dentro de table-wrap
   - evidencia shipping conservada sin cambiar semántica

5. panel Factura / Postventa
   - conservar id="invoice-review"
   - readiness / factura / nota crédito / postventa como lista clave/valor
```

Preferencia KISS:

```text
reutilizar page-header, panel, table-wrap, status-chip y primitivas clave/valor existentes
no añadir framework
no tocar controller
no tocar reconciliación
no tocar Billing
no tocar invoice logic
no crear navegación nueva
CSS nuevo sólo si una necesidad visual real no puede resolverse con primitivas existentes
```

## 7. TDD PROPUESTO — NO EJECUTADO AÚN

Con aprobación, añadir a `SalesDetailHttpRouteTest.php` un contrato visual mínimo que exija únicamente estructura estable, por ejemplo:

```text
class="page-header"
class="panel"
class="table-wrap"
id="reconciliation"
id="invoice-review"
status-chip
```

Después:

```text
RED esperado
→ GREEN sólo detail.php y CSS genérico mínimo si indispensable
→ QA PHP 8.3/8.4/8.5
→ checkpoint
→ STOP
```

## 8. NO TOCADO EN ESTE MICROBLOQUE

```text
NO cambios de producto
NO cambios de tests
NO CSS
NO controller
NO OAuth
NO MELI real
NO Hostinger
NO deploy
NO merge
```

## 9. GUARDRAILS

```text
no merge a stable todavía
no deploy UI todavía
no tocar Hostinger en el próximo microbloque
REAL_MELI_HTTP=0
no OAuth/API MELI real
remote writes=OFF
no F15/F16
no secretos en chat/Git/logs
Codex sólo para trabajo que ChatGPT no pueda ejecutar: Windows/runtime/DB/browser/hPanel/portal/evidencia
```

## 10. NEXT — EXACTLY ONE MICROBLOCK

**Esperar aprobación del diseño.**

Si el usuario responde `continua`, eso autoriza únicamente:

```text
/sales/{id}
→ crear RED visual mínimo en SalesDetailHttpRouteTest.php
→ confirmar RED esperado
→ GREEN mínimo en detail.php (y CSS sólo si indispensable)
→ QA PHP 8.3/8.4/8.5
→ actualizar este checkpoint
→ STOP
```

No abrir merge/deploy/Hostinger/F14/F15/F16 en el mismo bloque.
