# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=UI COVERAGE AUDIT COMPLETE / NEXT MELI ACCOUNTS ONLY / STOP**

| Campo | Estado |
|---|---|
| Fecha | 2026-10-15 |
| Stable | `impl/v3-b-sales-audit-20261010` — INTACTO |
| Rama UI | `ui/operator-shell-v1-20261014` |
| Último source visual certificado | `6ed576283e6e9c4dbcd9d3bacd0ccd9e4d57934f` |
| Hostinger | ERP2 instalado; SIN cambios en este bloque |
| Mercado Libre real | NO abierto |
| Remote writes | OFF |
| UI-1 | EN PROGRESO |

## Microbloque cerrado

Se hizo únicamente una auditoría de cobertura visual del PR #18. No se modificó código de producto.

### Cubierto por el shell UI actual

```text
- navegación principal compartida
- login
- ventas — listado
- catálogo
- facturación
- empresas
- usuarios
- configuración del sistema
- selector de empresa
- instalador
```

### Huecos visuales confirmados

```text
1. /mercadolibre/accounts
   owner: app/Modules/Settings/views/meli-accounts.php
   estado: usa navegación nueva pero contenido principal sigue HTML crudo

2. /settings/mercadolibre
   owner: app/Modules/Settings/views/mercadolibre.php
   estado: conserva navegación/CSS inline antiguos y no usa el shell compartido

3. /sales/{id}
   owner: app/Modules/Sales/ViewSales/views/detail.php
   estado: usa navegación nueva pero detalle, tablas y secciones siguen sin estructura visual del shell
```

## Prioridad

Orden KISS recomendado:

```text
1. Cuentas Mercado Libre
2. Configuración Mercado Libre
3. Detalle de venta
```

Razón: Cuentas Mercado Libre será la siguiente pantalla operativa crítica cuando se abra el flujo MELI real; conviene dejarla clara antes de OAuth externo.

## Ya certificado

```text
Actions #788:
PHP 8.3=PASS
PHP 8.4=PASS
PHP 8.5=PASS
PHPStan=PASS
Syntax=PASS

Instalador:
DB_HOST=localhost
DB_PORT=3306
APP_URL=autodetectado cuando está vacío
shell visual=certificado
```

## 3 reglas

```text
KISS: modificar el owner visual existente, sin framework nuevo
ANTI-RUIDO: reutilizar public/assets/app.css y navigation.php
ANTI-PARCHE: no crear CSS/página paralela ni duplicar navegación
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

**Modernizar únicamente `app/Modules/Settings/views/meli-accounts.php` reutilizando el shell/CSS compartido → RED visual mínimo → GREEN → QA → checkpoint → STOP.**

No tocar `/settings/mercadolibre`, detalle de venta ni OAuth real en ese mismo bloque.
