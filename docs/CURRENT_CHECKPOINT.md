# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=INSTALLER APP_URL GREEN APPLIED / QA #783 IN PROGRESS**

| Campo | Estado |
|---|---|
| Fecha | 2026-10-15 |
| Stable | `impl/v3-b-sales-audit-20261010` — INTACTO |
| Rama UI | `ui/operator-shell-v1-20261014` |
| Source UI previo certificado | `423ee4d57f09afe1966fa371feae1130745c33de` |
| Source APP_URL GREEN pendiente de certificar | `b345566f1a242a1aaa9546571f103a8895cecb95` |
| Hostinger | ERP2 instalado; SIN cambios en este bloque |
| Mercado Libre real | NO abierto |
| Remote writes | OFF |
| UI-1 | EN PROGRESO |
| NEXT | Revisar únicamente resultado de Actions #783; cerrar si GREEN o diagnosticar su único fallo; checkpoint → STOP |

## Microbloque actual

Objetivo único: autodetectar `APP_URL` desde la URL real donde se ejecuta el instalador, manteniendo el campo editable y sin persistir el valor hasta que el usuario continúe.

## TDD — RED confirmado

Se añadió `WebInstallerApplicationUrlDefaultTest` con dos contratos:

```text
1. GET https://erp.example.test:8443/install en paso application
   EXPECTED APP_URL=https://erp.example.test:8443

2. Si APP_URL ya existe en sesión
   EXPECTED conservar valor guardado y no sobrescribirlo
```

Actions `#782` confirmó RED esperado:

```text
EXPECTED value="https://erp.example.test:8443"
ACTUAL   value=""
RESULT   FAIL esperado
```

## GREEN mínimo aplicado

Source:

```text
b345566f1a242a1aaa9546571f103a8895cecb95
```

Cambio único de producto en `app/Core/Install/WebInstaller.php`:

```text
si step=application y APP_URL está vacío:
    APP_URL = request.scheme + "://" + request.authority
```

Características:

```text
- usa el URI del request ya normalizado por PSR-7
- conserva puerto no estándar cuando existe
- NO usa headers externos como fuente manual
- NO sobrescribe APP_URL ya guardado
- NO persiste el valor hasta continuar el formulario
- campo sigue editable
- no toca validación APP_URL
- no toca OAuth ni Mercado Libre
```

## QA pendiente

Actions `#783` fue disparado para PHP 8.3 / 8.4 / 8.5 y estaba EN EJECUCIÓN al crear este checkpoint.

No declarar GREEN hasta revisar su conclusión.

## Ya certificado antes de este bloque

```text
DB_HOST=localhost
DB_PORT=3306
```

Actions `#780`:

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

**Revisar exclusivamente Actions #783. Si PHP 8.3/8.4/8.5 están GREEN, certificar este source y actualizar checkpoint. Si existe un fallo, diagnosticar únicamente ese fallo sin abrir otro frente. Luego STOP.**
