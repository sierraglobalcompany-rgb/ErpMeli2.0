# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=INSTALLER DB DEFAULTS CERTIFIED / FLAKE TEST FIXED**

| Campo | Estado |
|---|---|
| Fecha | 2026-10-15 |
| Stable | `impl/v3-b-sales-audit-20261010` — INTACTO |
| Rama UI | `ui/operator-shell-v1-20261014` |
| Source UI actual certificado | `423ee4d57f09afe1966fa371feae1130745c33de` |
| Source DB-defaults funcional | `b51084126488004e77c85c9e5478a6685dc7a9d7` |
| Hostinger | ERP2 instalado; sin cambios en este bloque |
| Mercado Libre real | NO abierto |
| Remote writes | OFF |
| UI-1 | EN PROGRESO |
| NEXT | Autodetectar únicamente `APP_URL` en el instalador → QA → checkpoint → STOP |

## Cerrado en este microbloque

Se corrigió exclusivamente la prueba flaky `MeliAccountManagementTest::testCompanyAdminSeesOnlySelectedCompanyAccountsAndNeverTokens`.

Antes buscaba la secuencia `2001` en todo el HTML, por lo que un token CSRF aleatorio podía producir un falso positivo. Ahora el contrato comprueba el Seller ID dentro de su celda HTML:

```text
<td>1001</td> = PRESENTE
<td>1003</td> = PRESENTE
<td>2001</td> = AUSENTE
OTRA EMPRESA = AUSENTE
TOKENS = AUSENTES
```

No se modificó código de producto en este microbloque.

## Instalador DB — ya certificado

El instalador mantiene los campos editables y prellena:

```text
DB_HOST=localhost
DB_PORT=3306
```

El cambio funcional permanece en `app/Core/Install/view.php` y no altera conexión, validación ni credenciales.

## Certificación

Actions `#780` sobre source `423ee4d57f09afe1966fa371feae1130745c33de`:

```text
PHP 8.3=PASS
PHP 8.4=PASS
PHP 8.5=PASS
PHPStan=PASS
Syntax=PASS
```

Con esto queda cerrada también la recertificación completa del cambio DB-defaults.

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

**Autodetectar únicamente `APP_URL` desde la URL real donde se está ejecutando el instalador, mantenerla editable, ejecutar QA y crear checkpoint. STOP.**

No rediseñar todavía el instalador ni tocar Mercado Libre real dentro de ese mismo bloque.
