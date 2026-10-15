# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=INSTALLER APP_URL AUTODETECTION CERTIFIED / CHECKPOINT**

| Campo | Estado |
|---|---|
| Fecha | 2026-10-15 |
| Stable | `impl/v3-b-sales-audit-20261010` — INTACTO |
| Rama UI | `ui/operator-shell-v1-20261014` |
| Source APP_URL certificado | `b345566f1a242a1aaa9546571f103a8895cecb95` |
| Hostinger | ERP2 instalado; SIN cambios en este bloque |
| Mercado Libre real | NO abierto |
| Remote writes | OFF |
| UI-1 | EN PROGRESO |
| NEXT | Definir siguiente microbloque UI/instalador; no abrirlo hasta nuevo `continua` |

## Cerrado en este microbloque

Se certificó la autodetección de `APP_URL` desde la URL real donde se ejecuta el instalador.

Contrato:

```text
si step=application y APP_URL está vacío:
    APP_URL = request.scheme + "://" + request.authority
```

Características:

```text
- conserva HTTPS/HTTP según request real
- conserva puerto no estándar
- no sobrescribe APP_URL ya guardado
- el campo sigue editable
- no persiste el valor hasta continuar
- no cambia validación APP_URL
- no toca OAuth ni Mercado Libre
```

## TDD

RED — Actions `#782`:

```text
EXPECTED value="https://erp.example.test:8443"
ACTUAL   value=""
RESULT   FAIL esperado
```

GREEN source:

```text
b345566f1a242a1aaa9546571f103a8895cecb95
```

## Certificación

Actions `#783`:

```text
PHP 8.3=PASS
PHP 8.4=PASS
PHP 8.5=PASS
PHPStan=PASS
Syntax=PASS
```

## Instalador ya certificado en bloques previos

```text
DB_HOST=localhost
DB_PORT=3306
APP_URL=autodetectado desde request cuando está vacío
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

## NEXT

**Esperar nuevo `continua` y abrir un único microbloque. STOP.**
