# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=MELI ACCOUNTS UI GREEN CERTIFIED / CHECKPOINT / STOP**

| Campo | Estado |
|---|---|
| Fecha | 2026-10-15 |
| Stable | `impl/v3-b-sales-audit-20261010` — INTACTO |
| Rama UI | `ui/operator-shell-v1-20261014` |
| Source UI certificado | `81f65e9b5d2db4cecc851ca2f02031e480d2eb09` |
| RED | `9cb97be5f22be286a4fb73dae59fca6afe7b93a4` |
| Hostinger | ERP2 instalado; SIN cambios en este bloque |
| Mercado Libre real | NO abierto |
| Remote writes | OFF |
| UI-1 | EN PROGRESO |

## Microbloque cerrado

Se modernizó exclusivamente **Cuentas Mercado Libre**.

Archivos de producto tocados:

```text
app/Modules/Settings/views/meli-accounts.php
public/assets/app.css
```

No se cambiaron rutas, OAuth, CSRF, permisos, estados de negocio, controllers ni lógica de cuentas.

## TDD — RED

Commit test-only:

```text
9cb97be5f22be286a4fb73dae59fca6afe7b93a4
```

Contrato visual:

```text
class="page-header"
class="toolbar"
class="table-wrap"
class="status-chip status-connected"
class="row-actions"
```

Actions `#791`:

```text
PHP 8.3=FAIL
PHP 8.4=FAIL
PHP 8.5=FAIL
```

En PHP 8.3 se verificó el RED exacto:

```text
775 tests / 1 failure
único fallo=MeliAccountManagementTest::testAccountsPageUsesOperatorVisualStructure
causa esperada=ausencia de class="page-header"
PHPStan=PASS
Syntax=PASS
```

## GREEN aplicado

Commits de producto del microbloque:

```text
5f4ec3fce30b8534271d29081b9955e3d5e65f2f  feat(ui): polish Mercado Libre accounts
81f65e9b5d2db4cecc851ca2f02031e480d2eb09  style(ui): add shared account status primitives
```

Resultado visual estructural:

```text
- encabezado de página
- bloque explicativo de vinculación
- toolbar para conectar/configurar aplicación
- tabla responsive
- estados con chips semánticos
- acciones de fila agrupadas
- acción Eliminar diferenciada como destructiva
```

Los textos contractuales previos sobre relación empresa/cuenta se conservaron.

## Certificación GREEN

Actions `#794`:

```text
PHP 8.3=PASS
PHP 8.4=PASS
PHP 8.5=PASS
```

El step completo `QA` terminó SUCCESS en las tres versiones, incluyendo sintaxis, PHPStan y PHPUnit.

## Huecos UI restantes

No tocar en este checkpoint:

```text
1. /settings/mercadolibre
   owner: app/Modules/Settings/views/mercadolibre.php

2. /sales/{id}
   owner: app/Modules/Sales/ViewSales/views/detail.php
```

## 3 reglas

```text
KISS: vista existente + CSS compartido; sin framework nuevo
ANTI-RUIDO: status/table/action primitives quedan reutilizables, no CSS paralelo
ANTI-PARCHE: presentación corregida en el owner visual sin alterar lógica MELI
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

**Esperar nuevo `continua`. Siguiente prioridad recomendada: modernizar únicamente `/settings/mercadolibre` → diseño bounded corto → RED → GREEN → QA → checkpoint → STOP.**
