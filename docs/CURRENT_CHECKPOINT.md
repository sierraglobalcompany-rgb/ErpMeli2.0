# CURRENT CHECKPOINT — ERP MELI 2.0

**CURRENT=INSTALLER DB DEFAULTS GREEN / FULL QA BLOCKED BY TEST FLAKE**

| Campo | Estado |
|---|---|
| Fecha | 2026-10-15 |
| Stable | `impl/v3-b-sales-audit-20261010` — INTACTO |
| Rama UI | `ui/operator-shell-v1-20261014` |
| Source DB-defaults | `b51084126488004e77c85c9e5478a6685dc7a9d7` |
| Hostinger | ERP2 instalado; sin cambios en este bloque |
| Mercado Libre real | NO abierto |
| Remote writes | OFF |
| UI-1 | EN PROGRESO |
| NEXT | Corregir únicamente flake test-only en `MeliAccountManagementTest` → QA completo → checkpoint → STOP |

## Cerrado funcionalmente en este microbloque

El instalador conserva los campos editables y ahora prellena los valores estándar cuando todavía no existe un valor guardado:

```text
DB_HOST=localhost
DB_PORT=3306
```

`DB_PORT=3306` ya existía. El GREEN mínimo añadió únicamente el default faltante de `DB_HOST=localhost` en `app/Core/Install/view.php`. No se modificó conexión, validación, credenciales, APP_URL ni diseño general del instalador.

## TDD

RED — Actions `#777`:

```text
EXPECTED DB_HOST=localhost
ACTUAL DB_HOST=""
DB_PORT=3306 ya PASS
RESULT=FAIL esperado
```

GREEN source:

```text
b51084126488004e77c85c9e5478a6685dc7a9d7
```

Actions `#778`:

```text
PHP 8.3=PASS
PHP 8.5=PASS
PHP 8.4=FAIL en prueba ajena al cambio
PHPStan=PASS antes de PHPUnit
Syntax=PASS
```

## Bloqueo QA identificado

El único fallo de PHP 8.4 fue:

```text
Tests\Integration\MeliAccountManagementTest::testCompanyAdminSeesOnlySelectedCompanyAccountsAndNeverTokens
assertStringNotContainsString('2001', $html)
```

No es un defecto del producto ni del instalador. El token CSRF aleatorio renderizado en esa ejecución contenía accidentalmente la secuencia `2001` (`...f200139...`), por lo que una aserción sobre todo el HTML produjo un falso positivo.

La prueba debe endurecerse en un microbloque separado para verificar el seller/row ajeno de forma estructurada o con un marcador no contaminable por CSRF. No tocar producto para hacerla pasar.

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

**Corregir exclusivamente la aserción flaky test-only de `MeliAccountManagementTest`, ejecutar QA completo y crear checkpoint. STOP.**

No tocar APP_URL, rediseño del instalador ni producto dentro de ese bloque.
