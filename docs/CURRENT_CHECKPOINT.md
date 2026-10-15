# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=UI-1 — LOGIN VISUAL GREEN / CHECKPOINT**

| Campo | Estado |
|---|---|
| Fecha | 2026-10-15 |
| Stable | `impl/v3-b-sales-audit-20261010` — INTACTO |
| Rama UI | `ui/operator-shell-v1-20261014` |
| Source login/UI certificado | `9d161b145aab57fbc2db457f7f21950e06adc8f7` |
| Hostinger | ERP2 instalado; smoke login/empresas/usuarios/configuración/ventas PASS |
| Mercado Libre real | NO abierto |
| Remote writes | OFF |
| UI-1 | EN PROGRESO |
| NEXT | Instalador: defaults DB `localhost:3306` únicamente → QA → checkpoint → STOP |

## Cerrado en este microbloque

Se mantuvo intacto el contrato funcional de autenticación (`POST /login`, `AuthService`, usuario, contraseña y CSRF) y se cambió únicamente la presentación de `GET /login`.

El RED TDD quedó demostrado por Actions `#774`:

```text
TEST=testLoginUsesSharedAuthShellAndKeepsCredentialsContract
EXPECTED=/assets/app.css + auth-page + auth-card
ACTUAL=login HTML básico sin shell visual
RESULT=FAIL esperado
```

GREEN aplicado reutilizando el único CSS compartido ya existente:

```text
GET /login=200
CSS=/assets/app.css
auth-page=YES
auth-card=YES
username contract=PRESERVED
password contract=PRESERVED
csrf contract=PRESERVED
POST /login=UNCHANGED
AuthService=UNCHANGED
```

No se añadió framework frontend, controller nuevo, sistema de plantillas nuevo ni dependencia adicional.

## Certificación

Actions `#775`:

```text
PHP 8.3=PASS
PHP 8.4=PASS
PHP 8.5=PASS
PHPStan=PASS
Syntax=PASS
```

Diff GREEN frente al RED: sólo `app/Core/Http/Routes.php`, 10 adiciones / 5 eliminaciones. UI-1 continúa aislado en PR #18. Stable y Hostinger no recibieron este cambio.

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
no OAuth real
no API Mercado Libre real
remote writes=OFF
no F15/F16
```

## NEXT — un solo microbloque

**Mejorar únicamente los defaults de conexión DB del instalador: host `localhost` y puerto `3306`, manteniendo ambos editables; ejecutar QA y crear checkpoint. STOP.**

No tocar todavía autodetección de APP_URL, rediseño del instalador ni otras pantallas dentro de ese mismo bloque.
