# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=MELI APP SETTINGS UI GREEN APPLIED / QA #799 RUNNING / CHECKPOINT / STOP**

| Campo | Estado |
|---|---|
| Fecha | 2026-10-15 |
| Stable | `impl/v3-b-sales-audit-20261010` — INTACTO |
| Rama UI | `ui/operator-shell-v1-20261014` |
| Último source UI previamente certificado | `81f65e9b5d2db4cecc851ca2f02031e480d2eb09` |
| GREEN actual pendiente de certificación | `aed0668fa99226917bbab918e63b3c8ddeb2166f` |
| Hostinger | ERP2 instalado; SIN cambios en este bloque |
| Mercado Libre real | NO abierto |
| Remote writes | OFF |
| UI-1 | EN PROGRESO |

## Microbloque actual

Objetivo bounded: modernizar únicamente `/settings/mercadolibre` sin cambiar comportamiento, OAuth, CSRF, secretos, persistencia ni autorización.

Owners de producto tocados:

```text
app/Modules/Settings/views/mercadolibre.php
public/assets/app.css
```

Test tocado:

```text
tests/Integration/MeliAppSettingsTest.php
```

## TDD — RED

Primer contrato visual test-only:

```text
87af4dbb28cf761e87e04b1461340a95380dc756
Actions #796
```

PHP 8.3 confirmó RED exacto:

```text
776 tests / 1 failure
único fallo=MeliAppSettingsTest::testMeliSettingsUsesSharedOperatorVisualStructure
causa=la vista antigua no cargaba /assets/app.css ni shell compartido
PHPStan=PASS
Syntax=PASS
```

Durante la revisión del owner se detectó que exigir `app-nav` era incorrecto: `navigation.php` requiere contexto de empresa, mientras `/settings/mercadolibre` es una pantalla global de system admin. Forzarlo habría ampliado tenancy sólo por presentación.

Contrato RED corregido KISS/anti-parche:

```text
e317aac6053c753154bc3002bc4c0ce07cfbb64e
Actions #797
```

Exige el shell global ya existente:

```text
/assets/app.css
simple-page
simple-nav
page-header
settings-grid
panel
```

Al congelar este checkpoint, PHP 8.3 y PHP 8.4 de #797 habían confirmado FAIL esperado. No declarar aquí el resultado final de PHP 8.5 sin evidencia posterior.

## GREEN aplicado

Commits de producto:

```text
15c481b74d1631e8977d7f236b8e134a52c08d08  feat(ui): modernize MELI application settings
aed0668fa99226917bbab918e63b3c8ddeb2166f  style(ui): add shared settings layout primitives
```

Resultado estructural:

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

No se tocaron:

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

## QA actual

Commit bajo certificación:

```text
aed0668fa99226917bbab918e63b3c8ddeb2166f
```

Actions:

```text
#799
estado al congelar=IN PROGRESS
PHP 8.3=IN PROGRESS
PHP 8.4=IN PROGRESS
PHP 8.5=IN PROGRESS
```

**NO declarar GREEN certificado todavía.**

## Hueco UI restante después de cerrar este bloque

```text
/sales/{id}
owner: app/Modules/Sales/ViewSales/views/detail.php
```

No tocar aún.

## 3 reglas

```text
KISS: reutilizar shell global existente; sin introducir contexto de empresa artificial
ANTI-RUIDO: se elimina CSS inline legado y se usan primitivas compartidas
ANTI-PARCHE: presentación resuelta en la vista/CSS; no se altera tenancy ni controlador
```

## Guardrails

```text
no merge a stable todavía
no deploy UI todavía
no tocar Hostinger
no OAuth real
no API Mercado Libre real
remote writes=OFF
no F15/F16
```

## NEXT — un solo microbloque

**Revisar exclusivamente Actions #799. Si PHP 8.3/8.4/8.5 terminan PASS, cerrar `/settings/mercadolibre` como GREEN certificado → checkpoint → STOP. Si falla, diagnosticar únicamente ese fallo → checkpoint → STOP.**
