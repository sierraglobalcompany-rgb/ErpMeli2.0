# ERP MELI 2.0 — V3 ADDENDUM 01

**Fecha:** 2026-10-09  
**Estado:** NORMATIVO PARA EL DRAFT V3; no autoriza código productivo.  
**Aplica sobre:** `ERP_MELI_2_0_ESPECIFICACION_MAESTRA_V3.md` y `ERP_MELI_2_0_PLAN_MAESTRO_V3.md`.  
**Freeze:** F6A Task 2 y todo código V3 continúan congelados.

Este addendum incorpora hallazgos de la revisión de consistencia posterior al checkpoint `65c870ed256a8f8fae6111416696ea5b6ebf8855`. En caso de conflicto, este documento prevalece hasta que los cambios se consoliden en Spec/Plan V3.

---

## 1. Mes actual: cobertura verificada no significa histórico cerrado

### Hallazgo

Un mes en curso cambia legítimamente porque siguen entrando ventas. Por tanto, aplicar `RECONCILE_REPEATABLE` como si fuera un período histórico cerrado produciría falsos fallos y etiquetas engañosas.

### Regla

Para Sales:

```text
período cerrado = local_to <= now en America/Bogota
período actual  = local_from <= now < local_to
```

Un run del mes actual puede demostrar que la captura fue coherente **hasta su instante de finalización**, pero no puede presentarse como `histórico completo/final`.

No añadir columna ni estado sólo para esto. Derivar la presentación desde `period_key`, el final canónico del mes y `completed_at`.

UI mínima:

```text
mes cerrado + captura verificada → Verificado
mes actual + captura verificada  → Al día hasta <timestamp>
```

Un cambio posterior del conjunto del mes actual es esperado y no viola `RECONCILE_REPEATABLE`.

### Gate ajustado

`RECONCILE_REPEATABLE` aplica a períodos cerrados y a capturas comparables de la misma fuente/ventana. El mes en curso se evalúa como snapshot, no como conjunto final.

---

## 2. Orders HTTP 206: no crear infraestructura nueva

La documentación oficial vigente de Orders indica que `GET /orders/{id}` puede devolver HTTP 206 cuando faltan datos complementarios y enumera en `X-Content-Missing`:

```text
buyer
feedback
mediations
seller
shipping
```

Los campos núcleo de Sales del ejemplo oficial —incluido `date_created`, `status`, `order_items`, `total_amount` y `currency_id`— siguen presentes.

### Regla V3

```text
206 Orders ≠ error global
206 Orders + contrato Sales núcleo utilizable → persistir
206 Orders + contrato Sales núcleo inválido    → fail closed
```

No añadir por ahora:

- tabla de headers 206;
- historial de `X-Content-Missing`;
- retry engine 206;
- estado 206 dedicado.

### Endurecimiento mínimo requerido

`date_created` pasa a ser **campo obligatorio para nuevas sincronizaciones API de Sales**, porque V3 usa ese instante como verdad de ocurrencia y pertenencia mensual.

La columna DB puede seguir nullable por compatibilidad/migración legacy; el contrato remoto nuevo no debe persistir una orden recién sincronizada sin `date_created` válido.

Tests requeridos después de aprobación:

1. HTTP 206 con `buyer` ausente/vacío y núcleo Sales completo → sync exitoso.
2. HTTP 206/200 con `date_created` inválido o ausente → `meli_order_contract`, sin persistencia parcial.
3. HTTP 206/200 con money/items/status críticos inválidos → fail closed.

---

## 3. Separar estrictamente CAPTURE de REPAIR

### Problema detectado en Plan V3 original

Task B4 proponía registrar IDs esperados y encolar `order.sync` durante cada página de la captura. Task B6 volvía a calcular `missing` y reparar después de cerrar la captura.

Eso duplica responsabilidades y puede generar child work desde una captura que posteriormente resulte inválida por:

- `paging.total` cambiante;
- página repetida;
- offset incoherente;
- 429/5xx no resuelto;
- malformed result.

### Regla corregida

```text
CAPTURE
  sólo observa y persiste evidencia de la fuente
  NO encola order.sync

VALIDATE CAPTURE
  prueba cobertura remota

REPAIR
  expected canónico - local canónico
  encola sólo missing

VERIFY
  comprueba reparación

CONFIRM
  segunda captura cuando corresponda
```

Consecuencia KISS: menos fan-out, menos API exacta innecesaria y una única ubicación para la lógica de repair.

---

## 4. Guard-band: conservar evidencia remota sin contaminar el mes

### Problema

El request remoto usa guard-band ±1 hora para no depender de la inclusividad/granularidad exacta del filtro remoto. Por ello `paging.total` incluye potencialmente órdenes vecinas que no pertenecen al mes canónico.

Si `sales_audit_orders` guardara sólo expected canónicos, no podríamos comparar de forma limpia:

```text
remote paging.total
vs
IDs únicos realmente observados durante el recorrido
```

### Diseño mínimo corregido

`sales_audit_orders` representa **órdenes observadas durante la captura**, no sólo expected canónicos.

Añadir únicamente:

```text
in_period TINYINT(1) NOT NULL
```

Cada fila conserva:

```text
audit_run_id
external_order_id
remote_date_created UTC
in_period
```

Unique:

```text
(audit_run_id, external_order_id)
```

### Derivaciones

```text
raw_observed_count = COUNT(*)
expected_count     = COUNT(*) WHERE in_period=1
canonical_set_hash = hash(ORDER BY external_order_id WHERE in_period=1)
missing            = canonical observed IDs - local canonical valid orders
```

Así:

- IDs del guard-band ayudan a probar el recorrido remoto;
- no cuentan como ventas del mes;
- duplicados de cualquier parte de la captura quedan detectables;
- no hace falta tabla de páginas ni raw JSON.

### Cobertura válida

Al terminar una captura, como mínimo:

```text
paging.total estable
raw_observed_count == initial_remote_total
no duplicate remote IDs
recorrido de offsets coherente
sin página remota inválida
```

`expected_count` puede ser menor que `initial_remote_total` por el guard-band. Esa diferencia es correcta.

---

## 5. Definición de local válido para Historical Repair

No basta con que `external_order_id` exista localmente.

Para resolver un expected canónico como presente, la orden local debe tener:

```text
company/account correctos
external_order_id correcto
date_created válido
proyección America/Bogota dentro del período canónico
```

Por tanto:

```text
missing = expected canonical IDs - valid local canonical IDs
```

Una row legacy con ID pero `date_created=NULL` no satisface cobertura histórica y debe pasar por repair/attention según su fuente disponible.

Esto elimina un falso positivo posible en el diseño inicial.

---

## 6. Decoder lossless: compatibilidad con Orders search

La revisión de `ReconcileOrdersHandler` actual confirma que ya acepta:

```text
paging.total  → int o string de dígitos
paging.offset → int o string de dígitos
paging.limit  → int o string de dígitos
result.id     → int o string
```

Por tanto, preservar JSON NUMBER como lexema string en `orders.search` no exige una capa de reconversión global.

Añadir test de regresión explícito:

- `paging.total/offset/limit` como strings numéricos siguen siendo aceptados;
- `result.id` como string sigue siendo aceptado;
- money nunca vuelve a float.

No introducir un sistema de tipos remotos adicional.

---

## 7. Ajustes normativos al Plan V3

### B1 — frontera mensual

Añadir tests:

- período cerrado detectado de forma determinística;
- mes actual no se etiqueta histórico final;
- timezone PHP default no altera la clasificación.

No requiere columna nueva.

### B2 — schema

`sales_audit_orders` queda:

```text
id
audit_run_id
external_order_id VARCHAR(32)
remote_date_created DATETIME(6)
in_period TINYINT(1) NOT NULL
created_at
UNIQUE(audit_run_id, external_order_id)
```

No tabla de páginas.

### B3 — repository

Las operaciones mínimas deben distinguir:

```text
recordObservedOrder(runId, orderId, dateCreatedUtc, inPeriod)
rawObservedCount(runId)
canonicalExpectedCount(runId)
canonicalSetHash(runId)
missingCanonicalLocalOrderIds(runId)
```

`missingCanonicalLocalOrderIds()` exige local `date_created` válido dentro del mismo mes.

### B4 — capture page

Eliminar de B4:

```text
enqueue order.sync si falta localmente
```

B4 sólo registra evidencia remota y agenda la siguiente página de capture.

### B5 — validación

Antes de pasar a repair:

```text
rawObservedCount == initial_remote_total
```

además de las otras reglas de paging.

### B6 — único punto de repair

Sólo después de `finishCapture()` válido:

```text
missing = missingCanonicalLocalOrderIds(run)
→ enqueue order.sync sólo missing
```

### B7/B8 — mes actual

- closed month verified → `Verificado`;
- current month verified snapshot → `Al día hasta ...`;
- no presentar current month como histórico completo.

### A3/A4 — core Sales contract + 206

Después del decoder lossless:

- `date_created` requerido para sync API nuevo;
- 206 con buyer/feedback/etc. faltantes y núcleo completo es válido;
- núcleo crítico inválido falla cerrado.

---

## 8. Gates V3 modificados

Añadir/precisar:

```text
CURRENT_MONTH_NOT_FINAL
REMOTE_CAPTURE_COUNT_PROVEN
ORDER_DATE_CREATED_REQUIRED
ORDERS_206_CORE_USABLE
```

Semántica:

- `CURRENT_MONTH_NOT_FINAL`: el mes en curso nunca se muestra como histórico final.
- `REMOTE_CAPTURE_COUNT_PROVEN`: IDs remotos únicos observados cubren el `paging.total` estable de la captura, incluyendo guard-band.
- `ORDER_DATE_CREATED_REQUIRED`: sync API nuevo no persiste Sales sin `date_created` válido.
- `ORDERS_206_CORE_USABLE`: 206 con sólo contenido complementario ausente no rompe Sales; núcleo inválido sí falla.

---

## 9. Resultado de reducción de ruido

Con estas correcciones V3 evita añadir:

- tabla de páginas de auditoría;
- state especial para mes actual;
- engine 206;
- almacenamiento de headers 206;
- child work durante captura no validada;
- repair duplicado;
- lógica de cobertura basada sólo en existencia de ID.

Se añade sólo una propiedad demostrada necesaria:

```text
sales_audit_orders.in_period
```

La arquitectura base permanece:

```text
1 Work
1 WorkRunner
1 MeliClient
2 tablas Sales audit como máximo
```

---

## 10. Punto de reanudación

Este addendum debe incorporarse a la revisión final de Spec/Plan V3 antes de aprobación.

**Sigue prohibido iniciar A1/Billing/product code hasta aprobación explícita del usuario.**
