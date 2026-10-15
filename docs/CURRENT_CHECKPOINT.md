# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=INSTALLER VISUAL SHELL RED CREATED / QA #786 IN PROGRESS / STOP**

| Campo | Estado |
|---|---|
| Fecha | 2026-10-15 |
| Stable | `impl/v3-b-sales-audit-20261010` — INTACTO |
| Rama UI | `ui/operator-shell-v1-20261014` |
| Último source certificado | `b345566f1a242a1aaa9546571f103a8895cecb95` |
| RED visual source | `05ded1c18d9ad6b3520f8e1141b598c2c9a285da` |
| Hostinger | ERP2 instalado; SIN cambios en este bloque |
| Mercado Libre real | NO abierto |
| Remote writes | OFF |
| UI-1 | EN PROGRESO |
| NEXT | Revisar únicamente Actions #786; confirmar RED visual esperado y luego aplicar GREEN mínimo sólo en `app/Core/Install/view.php` → QA → checkpoint → STOP |

## Estado exacto del microbloque

Se abrió únicamente el microbloque **diseño visual del instalador**. No se ha modificado todavía código de producto ni `view.php`.

Se añadió una prueba visual mínima:

`tests/Integration/WebInstallerVisualShellTest.php`

Contrato RED:

```text
GET /install debe renderizar:
class="install-page"
class="install-shell"
class="install-brand"
class="install-card"
```

La intención es mejorar sólo la estructura visual del instalador, conservando su CSS inline para que funcione antes de existir configuración o assets externos.

## RED actual

Commit test-only:

```text
05ded1c18d9ad6b3520f8e1141b598c2c9a285da
```

Actions:

```text
#786 = EN EJECUCIÓN al crear este checkpoint
```

No declarar RED confirmado hasta revisar el resultado final de #786.

## No realizado todavía

```text
NO GREEN visual aplicado
NO cambios en app/Core/Install/view.php
NO cambios de lógica del instalador
NO cambios DB
NO cambios APP_URL
NO cambios login
NO cambios Mercado Libre
NO deploy a Hostinger
NO merge a stable
```

## Ya certificado antes de este bloque

```text
DB_HOST=localhost
DB_PORT=3306
APP_URL=autodetectado desde request cuando está vacío
```

Actions `#783`:

```text
PHP 8.3=PASS
PHP 8.4=PASS
PHP 8.5=PASS
PHPStan=PASS
Syntax=PASS
```

## 3 reglas

```text
KISS: camino mínimo y arquitectura simple
ANTI-RUIDO: reutilizar/unir/simplificar; no duplicar soluciones
ANTI-PARCHE: corregir owner/causa raíz; no workaround sobre workaround
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

**Revisar exclusivamente Actions #786. Si confirma el RED visual esperado, aplicar el GREEN mínimo únicamente en `app/Core/Install/view.php`, ejecutar QA y crear checkpoint. STOP.**
