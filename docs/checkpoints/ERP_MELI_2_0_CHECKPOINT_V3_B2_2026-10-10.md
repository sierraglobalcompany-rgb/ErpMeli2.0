# ERP MELI 2.0 — CHECKPOINT V3-B2

**Fecha:** 2026-10-10  
**Repositorio:** `sierraglobalcompany-rgb/ErpMeli2.0`  
**Rama:** `impl/v3-b-sales-audit-20261010`  
**HEAD antes de este checkpoint:** `9122700a86cecccb9a1a1cd85c9cbd7e56a870a8`  
**Base V3-A cerrada:** `fdfd1aad0ac099bb824e3cd8563eef9d5b7dff10`  
**Autoridad:** `docs/ERP2_AUTHORITY.md`  
**Plan V3-B:** `docs/superpowers/plans/2026-10-10-v3-b-sales-audit.md`  
**Estado de ML writes:** OFF  
**QA real Mercado Libre HTTP:** OFF (`REAL_MELI_HTTP=0`)

## Motivo del checkpoint

Se crea este checkpoint preventivo porque el hilo está llegando a riesgo de bloqueo/contexto. Debe permitir reanudar sin volver a reconstruir el estado ni preguntar dónde se quedó el trabajo.

## Estado exacto

V3-A quedó cerrada y verde. Antes de continuar se auditó el cierre de V3-A para detectar huecos causados por el bloqueo anterior.

Se encontró **un solo hueco concreto**:

- la autoridad V3.2 requería `orders.search` con decode nativo + `JSON_BIGINT_AS_STRING`;
- el `MeliClient` final de V3-A hacía decode nativo sin esa bandera;
- un RED con entero literal mayor que `PHP_INT_MAX` demostró coerción a `float`;
- B0 lo corrigió sin activar `LosslessJsonDecoder` para `orders.search`.

No se detectó otro salto de A1-A8 ni regresión contractual en los dueños auditados.

## V3-B cerrado hasta ahora

### B0 — bigint seguro en `orders.search` — CLOSED

RED:

```text
6be2cb8060bbeb2cd3103722c4ea3b29d5c8d3e7
```

GREEN:

```text
b05bb8bd3a4f0eb67dc821cd8878b84e4e89931f
```

Cambio mínimo:

```text
json_decode(..., JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING)
```

QA B0:

```text
GitHub Actions run 38012299105
job 114094780325
PHP 8.5.11
PHPStan 0
160/160 tests PASS
1058 assertions
20 MB
REAL_MELI_HTTP=0
```

### B1 — schema durable de Sales Audit — CLOSED

RED:

```text
e100e51af4fb4c9b7659be47b742c3f250e0236b
```

GREEN:

```text
424111bfd3642e8de66c56ce805eba19e7707b82
```

Se editó `database/migrations/004_sales.sql` en sitio porque ERP2 sigue pre-deploy.

Añadido exactamente:

```text
sales_audit_runs
sales_audit_orders
```

`sales_audit_runs`:

```text
id
company_id
account_id
period_key
contract_version
status
remote_total
canonical_count
set_hash
started_at
completed_at
updated_at
```

Estados:

```text
capturing
repairing
confirming
valid
attention
unavailable
```

`sales_audit_orders`:

```text
audit_run_id
external_order_id
remote_date_created
PRIMARY KEY(audit_run_id, external_order_id)
```

No se añadió:

```text
surrogate id
in_period
page table
repair table
history table
raw JSON
snapshot JSON
migration 006
handler
Work type
```

QA B1:

```text
run 38012530735
job 114095498948
PHPStan 0
161/161 tests PASS
1064 assertions
20 MB
REAL_MELI_HTTP=0
```

Checkpoint intermedio B0+B1:

```text
bc1102a3000882375fa9bf04591fc64244264201
```

También verificado green.

### B2 — contrato temporal + run durable — CLOSED

RED:

```text
2602ce1d88fe56e2ea1be16cdb161c8e8a323ded
```

El RED tuvo sólo los 5 fallos nuevos esperados porque aún no existían:

```text
SalesAuditWindow
SalesAuditRepository
```

PHPStan seguía en 0; no hubo regresiones colaterales.

GREEN:

```text
f47e9fc1c8da4be1ee416d69189a41b6a31acbd4  SalesAuditWindow
62954301183f487382d79ca119e965d2165c6229  SalesAuditRepository
```

#### Contrato temporal

Sólo soporte histórico actual:

```text
site_id=MCO -> America/Bogota
otro site -> fail closed
```

Mes canónico:

```text
[first day 00:00 local, first day next month 00:00 local)
```

Ejemplo `2026-10-01`:

```text
canonical UTC start = 2026-10-01T05:00:00Z
canonical UTC end   = 2026-11-01T05:00:00Z
remote guard start  = 2026-10-01T04:00:00Z
remote guard end    = 2026-11-01T06:00:00Z
```

`period_key` debe ser un `YYYY-MM-01` real.

#### `SalesAuditRepository`

Implementado solamente:

- resolver `site_id` por scope exacto company/account;
- fallar si account no pertenece a company;
- contrato fijo `seller-search-v1`;
- crear run durable `capturing`;
- normalizar `started_at` a UTC;
- validar site/period por `SalesAuditWindow` antes de insertar.

No existe todavía:

```text
SalesAuditHandler
sales.audit Work wiring
HTTP remoto de CAPTURE
multipage capture
order.sync fan-out
REPAIR
VERIFY
CONFIRM
```

QA B2:

```text
run 38012892267
job 114096660065
PHP 8.5.11
PHPStan 0
166/166 tests PASS
1089 assertions
20 MB
REAL_MELI_HTTP=0
```

El checkpoint documental `9122700a86cecccb9a1a1cd85c9cbd7e56a870a8` también fue verificado en su propio HEAD:

```text
run 38012997766
job 114096995008
166/166 tests PASS
1089 assertions
PHPStan 0
REAL_MELI_HTTP=0
```

## Auditoría de regresión V3-A confirmada

Permanece correcto:

- `MeliClient` fail-closed: sólo `READ|AUTH|WRITE`;
- unknown/missing classification bloqueada;
- exact-money lossless sólo donde aplica;
- timestamps remotos zoned estrictos;
- float en frontera monetaria rechazado;
- Work max attempts automático = 5;
- 429 usa defer sin quemar intento;
- 5xx/transport usa retry bounded;
- crash recovery falla terminal al cap;
- admin no puede habilitar `meli_writes_enabled`;
- settings fail-closed conservadas;
- cleanup de Work sólo `done/failed` antiguos por `finished_at`;
- API usage retention 90 días;
- no apareció queue/scheduler/retry/maintenance engine paralelo.

## Gates actuales

| Gate | Estado |
|---|---|
| G1 REMOTE_TRUTH | PASS para frontera implementada |
| G2 WORK_SAFETY | PASS |
| G3 RATE_SAFETY | PASS para Sales implementado |
| G4 SALES_AUDIT_TRUTH | IN PROGRESS |
| G5 BILLING_CURSOR_TRUTH | BLOCKED por C0 real sanitized MCO smoke |
| G6 FINANCIAL_NO_DOUBLE_COUNT | NOT STARTED |
| G7 WRITE_FAIL_CLOSED | PASS |
| G8 HOSTING_REALITY | NOT CERTIFIED |

## Punto exacto de reanudación

**No se ha escrito aún implementación B3a.**

La última acción antes de este checkpoint fue leer `tests/Integration/SalesAuditFoundationTest.php` para preparar el siguiente RED.

### V3-B3a — siguiente microbloque

Objetivo único: persistencia de evidencia observada, **sin HTTP y sin Work**.

RED debe exigir:

1. `SalesAuditRepository` pueda guardar una observación:

```text
(audit_run_id, external_order_id, remote_date_created)
```

2. `remote_date_created` se persista en UTC.
3. Un mismo `external_order_id` repetido dentro del mismo run **no debe crear una segunda fila**.
4. El duplicado debe ser detectable por la capa de captura; no debe ocultarse sobrescribiendo silenciosamente la primera evidencia.
5. La evidencia original no se debe modificar por el duplicado.
6. Ningún HTTP remoto.
7. Ningún `sales.audit` Work type todavía.
8. Full QA antes de B3b.

### B3b — después de B3a green

Sólo entonces:

- un `sales.audit` Work consume una sola página `orders.search`;
- usa la ventana B2;
- requiere ID + `date_created` con offset explícito;
- persiste observaciones;
- CAPTURE no encola `order.sync`;
- 429 mantiene defer;
- 5xx/transport bounded retry;
- todavía no REPAIR/VERIFY/CONFIRM.

## Prohibiciones / stop conditions

No hacer todavía:

```text
F6A Task 2
Billing handler
C0 inventado
Financial
merge
deploy
real ML HTTP no autorizado
remote writes
meli_writes_enabled=1
REPAIR
VERIFY
CONFIRM
```

## Instrucción de recuperación para otro hilo

1. Leer `docs/ERP2_AUTHORITY.md`.
2. Leer `docs/superpowers/plans/2026-10-10-v3-b-sales-audit.md`.
3. Leer `docs/CURRENT_CHECKPOINT.md`.
4. Leer este archivo.
5. Confirmar branch `impl/v3-b-sales-audit-20261010` y HEAD de este checkpoint.
6. No reconstruir B0-B2; están cerrados y verificados.
7. Reanudar exactamente en **V3-B3a RED**.
8. Mantener bloques pequeños RED -> minimal GREEN -> QA -> checkpoint.
