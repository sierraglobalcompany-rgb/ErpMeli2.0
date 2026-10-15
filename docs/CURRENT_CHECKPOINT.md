# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=MELI ACCOUNTS UI RED CREATED / RED CONFIRMED PHP 8.3 / GREEN NOT APPLIED / STOP**

| Campo | Estado |
|---|---|
| Fecha | 2026-10-15 |
| Stable | `impl/v3-b-sales-audit-20261010` — INTACTO |
| Rama UI | `ui/operator-shell-v1-20261014` |
| Último source visual certificado | `6ed576283e6e9c4dbcd9d3bacd0ccd9e4d57934f` |
| RED actual | `9cb97be5f22be286a4fb73dae59fca6afe7b93a4` |
| Hostinger | ERP2 instalado; SIN cambios en este bloque |
| Mercado Libre real | NO abierto |
| Remote writes | OFF |
| UI-1 | EN PROGRESO |

## Microbloque actual

Alcance aprobado: modernizar únicamente **Cuentas Mercado Libre** en:

`app/Modules/Settings/views/meli-accounts.php`

Sin cambiar rutas, OAuth, CSRF, permisos, estados ni lógica de cuentas. Se reutilizará el shell existente y `public/assets/app.css`; no se añadirá framework ni arquitectura paralela.

## Diseño aprobado

```text
- encabezado de página
- acción principal dentro del shell existente
- ayuda/estado claramente agrupados
- tabla responsive usando table-wrap
- estados como status-chip
- acciones de fila agrupadas con row-actions
```

## TDD — RED

Commit test-only:

```text
9cb97be5f22be286a4fb73dae59fca6afe7b93a4
```

Se añadió únicamente:

`MeliAccountManagementTest::testAccountsPageUsesOperatorVisualStructure`

Contrato visual exigido:

```text
class="page-header"
class="toolbar"
class="table-wrap"
class="status-chip status-connected"
class="row-actions"
```

Actions:

```text
#791
PHP 8.3 = FAIL esperado confirmado
775 tests / 1 failure
único fallo = MeliAccountManagementTest::testAccountsPageUsesOperatorVisualStructure
primera ausencia confirmada = class="page-header"
PHPStan = PASS
Syntax = PASS
PHP 8.4 / 8.5 = estaban aún en ejecución al congelar este checkpoint; no declarar resultado final todavía
```

El fallo observado es exactamente el esperado: la vista actual sigue sin la estructura visual aprobada.

## NO realizado todavía

```text
NO GREEN aplicado
NO cambios en app/Modules/Settings/views/meli-accounts.php
NO cambios adicionales en public/assets/app.css
NO cambios OAuth
NO llamadas reales a Mercado Libre
NO deploy Hostinger
NO merge a stable
```

## Huecos UI que quedan después de este bloque

No tocar aún:

```text
/settings/mercadolibre
/sales/{id}
```

## 3 reglas

```text
KISS: owner visual existente, cambios mínimos
ANTI-RUIDO: reutilizar navigation.php + app.css; no duplicar estilos
ANTI-PARCHE: no alterar lógica para resolver presentación
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

**Revisar el resultado final de Actions #791 sólo para confirmar que el RED sigue siendo limpio en la matriz; luego aplicar GREEN mínimo exclusivamente a `meli-accounts.php` y, únicamente si resulta indispensable, clases genéricas mínimas en `public/assets/app.css` → QA → checkpoint → STOP.**
