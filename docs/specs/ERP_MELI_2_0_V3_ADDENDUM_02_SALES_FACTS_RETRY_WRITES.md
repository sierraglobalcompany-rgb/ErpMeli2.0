# ERP MELI 2.0 — V3 ADDENDUM 02

**Fecha:** 2026-10-09  
**Estado:** NORMATIVO PARA EL DRAFT V3; no autoriza código productivo.  
**Aplica sobre:** Spec V3, Plan V3 y Addendum 01.  
**Freeze:** todo código V3/F6A Task 2 continúa congelado.

Este documento cierra tres huecos detectados en la revisión de última milla: hechos comerciales de Order que ERP2 descarta hoy, semántica real de retries de Work y seguridad futura de clasificaciones de remote writes.

---

## 1. Sales pierde hoy `sale_fee` y `gross_price`

### Evidencia oficial vigente

La documentación actual de Mercado Libre Orders (`gestiona-ventas`, actualización publicada 21/09/2026) expone dentro de `order_items`:

```text
quantity
unit_price
sale_fee
gross_price
discounts
currency_id
```

Semántica documentada:

- `unit_price`: precio unitario después de descuentos;
- `gross_price`: monto bruto original para todas las unidades del ítem antes de descuentos;
- `sale_fee`: comisión de venta observada en el ítem;
- `gross_price` puede no existir en órdenes antiguas;
- la comisión se calcula cuando se acredita el pago, por lo que puede aparecer/actualizarse después de la creación inicial.

### Gap ERP2 actual

`004_sales.sql` sólo conserva en `order_items`:

```text
quantity
unit_price
currency_id
```

`SyncOrderHandler` descarta `sale_fee` y `gross_price` aunque estén presentes en la respuesta remota.

Esto pierde evidencia útil antes de llegar a Financial/Invoicing.

---

## 2. Decisión mínima: conservar dos escalares, no el árbol completo de descuentos

Después de `REMOTE_NUMBERS_LOSSLESS`, añadir a `order_items`:

```text
sale_fee DECIMAL(18,4) NULL
gross_price DECIMAL(18,4) NULL
```

Reglas:

- ambos valores se normalizan sin `float`;
- `sale_fee` nullable porque depende del estado/acreditación y puede no existir todavía;
- `gross_price` nullable por compatibilidad con órdenes antiguas;
- una respuesta más nueva de la orden puede actualizar ambos mediante el replace/upsert de items ya existente;
- no etiquetar `sale_fee` de Orders como sustituto de Billing; es un hecho observado de Orders que Financial puede contrastar.

### Lo que NO se añade ahora

No crear todavía:

- tabla `order_discounts`;
- tabla de campañas/cupones;
- JSON raw de descuentos;
- `FinancialOrderItem` separado;
- una columna para cada tipo de promoción.

El endpoint dedicado `/orders/{id}/discounts` y la porción seller-funded se incorporarán sólo cuando F6B/F10 demuestren una consulta o regla de negocio que `gross_price`, `unit_price`, `sale_fee` y Billing no puedan resolver.

### Gate

```text
ORDER_ITEM_COMMERCIAL_FACTS_PRESERVED
```

Debe probar al menos:

```text
quantity exacta
unit_price exacto
sale_fee exacto nullable
gross_price exacto nullable
```

con números JSON reales atravesando el decoder lossless.

---

## 3. Orden de migraciones V3 corregido antes de crear código

El Plan V3 original reservaba `006_sales_audit.sql` y `007_billing_context.sql`.

Como Sales item facts deben endurecerse antes del histórico, el orden normativo queda:

```text
006_sales_commercial_facts.sql
007_sales_audit.sql
008_billing_context.sql
```

No existen todavía estas migraciones; por tanto no hay compatibilidad que romper.

### V3-A4 — Sales item facts

Después de A1-A3:

**Crear:**

```text
database/migrations/006_sales_commercial_facts.sql
```

**Modificar:**

```text
app/Modules/Sales/SyncOrder/SyncOrderHandler.php
tests/Integration/SalesSchemaTest.php
tests/Integration/SyncOrderHandlerPersistenceTest.php
tests/Integration/SyncOrderDecimalPrecisionTest.php
```

RED:

- JSON NUMBER `sale_fee`/`gross_price` se conserva exactamente;
- null/ausencia permitida;
- float inyectado artificialmente falla contrato después del hardening lossless;
- replay más nuevo actualiza items sin duplicar.

No añadir UI todavía.

### Renumeración documental

- Plan B2 `006_sales_audit.sql` → `007_sales_audit.sql`.
- Plan C1 `007_billing_context.sql` → `008_billing_context.sql`.

---

## 4. Work retries: corregir una afirmación del Plan

### Evidencia de código

`work_items.attempts` incrementa al hacer claim.

Pero actualmente:

- `WorkRunner` no aplica máximo de attempts;
- `WorkRepository::retryCurrentClaim()` simplemente devuelve el mismo work a `pending`;
- `SyncOrderHandler` y `ReconcileOrdersHandler` reprograman ciertos 5xx/transport;
- MeliClient aplica cooldown para 429.

Por tanto, el estado real es:

```text
retry durable/no-inline-loop
≠
retry attempt-bounded
```

### Decisión KISS

No añadir ahora:

- retry budget engine;
- retry_streak column;
- backoff framework;
- contadores por error;
- DLQ adicional.

El contador `attempts` mezcla clases de error distintas (por ejemplo 429 y 5xx), por lo que usar un límite global ciego podría terminalizar un work válido después de varios rate limits.

### Semántica V3 corregida

Errores terminales conocidos sí deben terminar:

```text
malformed/unsupported
403 permission
404 histórico clasificado terminal/unavailable
contrato remoto inválido
cursor no progresivo
```

Errores transitorios:

```text
429 → cooldown + same work
5xx/transport → durable retry, sin retry inline
```

El gate `NO_ENDLESS_MISSING_RETRY` se demuestra evitando que 404/unavailable se vuelva a encadenar automáticamente, **no** inventando un máximo global de attempts.

La frase `retry bounded` del Plan V3 debe interpretarse/corregirse como:

```text
retry durable, paced, no inline loop, con terminalización semántica cuando el error deja de ser transitorio
```

Si QA/operación real demuestra work transitorio eternamente atascado, se abre un microtask separado y sólo entonces se evalúa una política de budget/streak mínima.

---

## 5. Evitar 404 histórico repetido entre auditorías automáticas

Historical Repair debe distinguir:

```text
missing local reparable
vs
exact GET terminal/unavailable
```

Cuando un `order.sync` exacto termina permanentemente por 404/not-owned/unavailable durante un run:

- el run pasa a `attention` o `unavailable` según semántica;
- no se autoencadena una nueva consulta exacta al mismo ID dentro del mismo run;
- no se crea un loop de replacement work;
- una reauditoría futura explícita puede volver a probar si existe razón operativa, pero no existe polling automático del ID terminal.

No crear caché global de 404 por ahora; el run histórico ya es evidencia suficiente para evitar el loop automático actual.

---

## 6. Remote writes: separar seguridad de transporte y efecto de negocio

### Gap detectado

`MeliClient` hoy protege writes sólo cuando:

```php
$operation['classification'] === 'WRITE'
```

El mapa V3, en cambio, usa semántica de negocio:

```text
READ
MUTATION
ACTION
FINANCIAL
DESTRUCTIVE
```

Registrar en el futuro una operación con `classification=MUTATION` podría saltarse accidentalmente el guard actual.

### Contrato V3 corregido

Mantener dos conceptos distintos:

```text
classification = AUTH | READ | WRITE
effect         = null | MUTATION | ACTION | FINANCIAL | DESTRUCTIVE
```

Reglas:

- toda operación que modifica estado remoto usa `classification=WRITE`;
- todo `WRITE` debe declarar `effect`;
- `READ`/`AUTH` no declaran efecto mutante;
- el switch global existente continúa bloqueando `WRITE`;
- F16 usa `effect` para decidir confirmación, RBAC, idempotencia y riesgo.

Esto preserva la compatibilidad del MeliClient actual y satisface el mapa de efecto real sin sobrecargar un solo campo.

### Defensa fail-closed antes del primer write registrado

Antes de agregar en F7/F8/F10 cualquier operación `WRITE`, añadir un test de registry/MeliClient que pruebe:

1. clasificación desconocida no puede convertirse silenciosamente en operación segura;
2. `WRITE` con `meli_writes_enabled=false` nunca cruza transport;
3. todo `WRITE` exige `effect` permitido;
4. `READ` no puede declarar efecto mutante.

Si el cambio mínimo más seguro resulta ser que MeliClient sólo considere seguras `AUTH|READ` y trate cualquier otra clasificación como write/bloqueada cuando el switch está OFF, preferir esa defensa adicional.

No crear RemoteWriteEngine.

---

## 7. Ajustes normativos al Plan V3

### V3-A

Orden actualizado:

```text
A1 RED JSON NUMBER real
A2 decoder lossless
A3 eliminar float money fallback + core Sales/date_created/206 tests
A4 persistir sale_fee + gross_price
Gate A
```

Gate A añade:

```text
ORDER_ITEM_COMMERCIAL_FACTS_PRESERVED
ORDER_DATE_CREATED_REQUIRED
ORDERS_206_CORE_USABLE
```

### V3-B

Usar Addendum 01 y migration `007_sales_audit.sql`.

No emitir child `order.sync` hasta que CAPTURE sea válida.

### V3-C

Usar migration `008_billing_context.sql`.

Reemplazar toda frase `retry bounded` por la semántica de sección 4 de este addendum.

404/403/contract/cursor errors son terminal/attention; 429 y 5xx/transport son transitorios sin loop inline.

### F7/F8/F10/F16

Antes del primer operation registry write:

```text
classification=WRITE
effect=<MUTATION|ACTION|FINANCIAL|DESTRUCTIVE>
writes switch OFF
registry invariant tests GREEN
```

---

## 8. Gates agregados/precisados

```text
ORDER_ITEM_COMMERCIAL_FACTS_PRESERVED
RETRY_SEMANTICS_TRUTHFUL
TERMINAL_HISTORICAL_NO_AUTO_LOOP
WRITE_CLASSIFICATION_FAIL_CLOSED
```

Semántica:

- `ORDER_ITEM_COMMERCIAL_FACTS_PRESERVED`: sale_fee/gross_price no se pierden y nunca pasan por float.
- `RETRY_SEMANTICS_TRUTHFUL`: QA/docs no llaman bounded a un retry que no tiene budget; errores terminales y transitorios están explícitamente separados.
- `TERMINAL_HISTORICAL_NO_AUTO_LOOP`: 404/unavailable no reencadena exact GET dentro del run.
- `WRITE_CLASSIFICATION_FAIL_CLOSED`: ninguna etiqueta de efecto puede saltarse `meli_writes_enabled=false`.

---

## 9. Resultado KISS

Se añaden sólo dos columnas de negocio ya demostradas:

```text
order_items.sale_fee
order_items.gross_price
```

Y un metadato futuro pequeño para writes:

```text
effect
```

No se añade:

- tabla discounts;
- retry engine;
- retry budget genérico;
- DLQ;
- remote-write framework;
- tabla Financial prematura.

---

## 10. Punto exacto de continuidad

Antes de pedir aprobación final del V3 draft, revisar:

1. Spec V3;
2. Plan V3;
3. Addendum 01;
4. este Addendum 02;
5. checkpoint maestro.

Después de aprobación explícita, el primer código sigue siendo **V3-A1 RED**. No Billing directo.
